<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client Gemini partagé : bascule automatiquement sur un modèle de repli quand le modèle
 * principal est saturé (HTTP 503 « high demand »), limité (429) ou retiré (404), avec une
 * courte attente. La liste des modèles disponibles est lue depuis l'API (cache 6 h) pour
 * suivre les renommages de Google sans redéploiement.
 */
class Gemini
{
    /** Alias stables essayés en premier, puis repli sur la liste publiée par l'API. */
    public const PREFERRED = ['gemini-flash-latest', 'gemini-flash-lite-latest'];

    public static function enabled(): bool
    {
        return (string) config('services.gemini.key') !== '';
    }

    /**
     * Appelle generateContent sur le premier modèle disponible.
     *
     * @param  array  $body  contents + generationConfig
     * @return Response|null  dernière réponse (réussie ou non), null si aucune requête n'a abouti
     */
    public static function generate(array $body, int $timeout = 60, string $context = 'gemini', bool $fast = false, bool $pro = false): ?Response
    {
        $key = (string) config('services.gemini.key');
        $last = null;
        // Tâches créatives exigeantes : modèles « Pro » d'abord, repli sur les Flash
        $models = $pro ? array_values(array_unique(array_merge(self::proCandidates($key), self::candidates($key)))) : self::candidates($key);
        if ($fast) {
            // Tâches simples (classification, lecture de photos) : les modèles « lite », plus rapides, d'abord
            usort($models, fn ($a, $b) => (int) str_contains($b, 'lite') <=> (int) str_contains($a, 'lite'));
        }
        // Un modèle saturé il y a moins de 2 minutes est passé en fin de liste (on évite d'attendre son 503)
        $models = array_merge(
            array_values(array_filter($models, fn ($m) => ! Cache::has("joow:gemini:down:$m"))),
            array_values(array_filter($models, fn ($m) => Cache::has("joow:gemini:down:$m")))
        );
        foreach ($models as $i => $model) {
            $res = self::call($key, $model, $body, $timeout, $context);
            // 400 « invalid argument » : certains modèles refusent thinkingConfig → on réessaie sans
            if ($res && $res->status() === 400 && isset($body['generationConfig']['thinkingConfig'])) {
                $slim = $body;
                unset($slim['generationConfig']['thinkingConfig']);
                $res = self::call($key, $model, $slim, $timeout, $context);
            }
            if (! $res) {
                continue;
            }
            $last = $res;
            if ($res->successful()) {
                if ($i > 0) {
                    Log::info("$context : réponse obtenue via le modèle de repli $model");
                }

                return $res;
            }
            Log::warning("$context : $model HTTP {$res->status()} ".str($res->json('error.message') ?? '')->limit(160));
            if (in_array($res->status(), [429, 500, 503], true)) {
                Cache::put("joow:gemini:down:$model", true, now()->addMinutes(2));
                usleep(300_000); // saturation / quota : courte pause avant le modèle suivant
            }
            if ($res->status() === 403 || $res->status() === 401) {
                return $res; // clé invalide : inutile d'insister
            }
        }

        return $last;
    }

    private static function call(string $key, string $model, array $body, int $timeout, string $context): ?Response
    {
        try {
            return Http::timeout($timeout)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", $body);
        } catch (\Throwable $e) {
            Log::warning("$context : $model exception ".$e->getMessage());

            return null;
        }
    }

    /** Alias préférés + modèles « flash » publiés par l'API (les plus récents d'abord), sans doublon. */
    public static function candidates(string $key): array
    {
        $published = Cache::remember('joow:gemini:models', now()->addHours(6), function () use ($key) {
            try {
                $res = Http::timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models', ['key' => $key, 'pageSize' => 200]);
                if (! $res->successful()) {
                    return [];
                }
                $names = collect($res->json('models') ?? [])
                    ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
                    ->map(fn ($m) => str_replace('models/', '', $m['name']))
                    ->filter(fn ($n) => str_contains($n, 'flash') && ! preg_match('/preview|exp|image|tts|audio|live|thinking|latest|8b/', $n))
                    ->values();
                // Les plus récents d'abord (numéro de version décroissant), les « lite » après les complets
                $ver = fn ($n) => (float) (preg_match('/(\d+(?:\.\d+)?)/', $n, $m) ? $m[1] : 0);

                return $names->sortBy([fn ($a, $b) => (int) str_contains($a, 'lite') <=> (int) str_contains($b, 'lite'), fn ($a, $b) => $ver($b) <=> $ver($a)])->values()->all();
            } catch (\Throwable) {
                return [];
            }
        });

        return array_values(array_unique(array_merge(self::PREFERRED, $published ?: ['gemini-3.8-flash', 'gemini-2.5-flash-lite'])));
    }

    /** Dernier modèle ayant produit une image (journal / suivi des coûts). */
    public static ?string $lastImageModel = null;

    /** Raisons d'échec de la dernière génération d'image (diagnostic). */
    public static array $lastImageErrors = [];

    private static function mime(string $bytes): string
    {
        return str_starts_with($bytes, "\x89PNG") ? 'image/png' : (str_starts_with($bytes, 'RIFF') ? 'image/webp' : 'image/jpeg');
    }

    /** Modèles « Pro » (raisonnement) : alias stables puis modèles pro publiés, les plus récents d'abord. */
    public static function proCandidates(string $key): array
    {
        $published = Cache::remember('joow:gemini:pro-models', now()->addHours(6), function () use ($key) {
            try {
                $res = Http::timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models', ['key' => $key, 'pageSize' => 200]);
                $ver = fn ($n) => (float) (preg_match('/(\d+(?:\.\d+)?)/', $n, $m) ? $m[1] : 0);

                return $res->successful() ? collect($res->json('models') ?? [])
                    ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
                    ->map(fn ($m) => str_replace('models/', '', $m['name']))
                    ->filter(fn ($n) => str_contains($n, '-pro') && ! preg_match('/image|tts|audio|live|vision|exp/', $n))
                    ->sortByDesc($ver)->values()->all() : [];
            } catch (\Throwable) {
                return [];
            }
        });

        return array_values(array_unique(array_merge(['gemini-pro-latest'], $published)));
    }

    /** Modèles capables de produire des images (alias préférés + modèles « image » publiés). */
    public static function imageModels(string $key): array
    {
        $published = Cache::remember('joow:gemini:image-models', now()->addHours(6), function () use ($key) {
            try {
                $res = Http::timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models', ['key' => $key, 'pageSize' => 200]);

                return $res->successful() ? collect($res->json('models') ?? [])
                    ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
                    ->map(fn ($m) => str_replace('models/', '', $m['name']))
                    ->filter(fn ($n) => str_contains($n, 'image') && ! str_contains($n, 'imagen'))->values()->all() : [];
            } catch (\Throwable) {
                return [];
            }
        });

        // « Nano Banana Pro » d'abord (texte lisible dans l'image, fidélité aux photos de référence), puis Flash Image
        $pro = array_values(array_filter($published, fn ($n) => str_contains($n, 'pro')));
        $flash = array_values(array_filter($published, fn ($n) => ! str_contains($n, 'pro')));

        return array_values(array_unique(array_merge(['gemini-3-pro-image-preview'], $pro, ['gemini-2.5-flash-image'], $flash, ['gemini-2.5-flash-image-preview'])));
    }

    /**
     * Génère une image (JPEG/PNG en octets) au format demandé, éventuellement à partir d'une photo de référence.
     * Retourne null si aucun modèle n'a produit d'image.
     */
    public static function image(string $prompt, string $aspect = '1:1', string|array|null $refBytes = null, string $context = 'image', bool $flashOnly = false): ?string
    {
        $key = (string) config('services.gemini.key');
        if ($key === '') {
            return null;
        }
        $parts = [['text' => $prompt]];
        foreach (array_filter((array) $refBytes) as $ref) {
            $parts[] = ['inline_data' => ['mime_type' => self::mime($ref), 'data' => base64_encode($ref)]];
        }
        self::$lastImageErrors = [];
        $models = self::imageModels($key);
        if ($flashOnly) {
            $models = array_values(array_filter($models, fn ($m) => ! str_contains($m, 'pro')));
        }
        $tried = false;
        $deadline = microtime(true) + 130; // reste sous le délai de la tâche de fond
        foreach (array_merge($models, ['@retry']) as $model) {
            if (microtime(true) > $deadline) {
                self::$lastImageErrors[] = 'temps total dépassé';
                break;
            }
            if ($model === '@retry') {
                if ($tried) {
                    break;
                }
                $model = $models[0] ?? null; // tous écartés : on retente quand même le premier plutôt que d'échouer sans essayer
                if (! $model) {
                    break;
                }
            } elseif (Cache::has("joow:gemini:down:$model")) {
                self::$lastImageErrors[] = "$model : écarté temporairement";

                continue;
            }
            $tried = true;
            foreach ([['responseModalities' => ['IMAGE'], 'imageConfig' => ['aspectRatio' => $aspect]], ['responseModalities' => ['TEXT', 'IMAGE']]] as $cfg) {
                $res = self::call($key, $model, ['contents' => [['parts' => $parts]], 'generationConfig' => $cfg], (int) max(20, min(120, $deadline - microtime(true))), $context);
                if (! $res) {
                    self::$lastImageErrors[] = "$model : délai dépassé ou réseau";

                    continue;
                }
                if ($res->successful()) {
                    foreach ((array) data_get($res->json(), 'candidates.0.content.parts', []) as $p) {
                        $data = $p['inlineData']['data'] ?? $p['inline_data']['data'] ?? null;
                        if ($data) {
                            self::$lastImageModel = $model;

                            return base64_decode($data);
                        }
                    }
                    self::$lastImageErrors[] = "$model : réponse sans image (".(data_get($res->json(), 'candidates.0.finishReason') ?? data_get($res->json(), 'promptFeedback.blockReason') ?? '?').')';
                    break; // réponse sans image : modèle suivant
                }
                Log::warning("$context : $model HTTP {$res->status()} ".str($res->json('error.message') ?? '')->limit(160));
                self::$lastImageErrors[] = "$model : HTTP {$res->status()} ".str($res->json('error.message') ?? '')->limit(140);
                if (in_array($res->status(), [403, 404], true)) {
                    Cache::put("joow:gemini:down:$model", true, now()->addHour()); // modèle indisponible pour cette clé (ex. facturation Google requise)
                    break;
                }
                if (in_array($res->status(), [429, 500, 503], true)) {
                    Cache::put("joow:gemini:down:$model", true, now()->addMinutes(2));
                    break;
                }
            }
        }

        return null;
    }

    /** Texte de la première candidate, ou chaîne vide. */
    public static function text(?Response $res): string
    {
        return $res ? trim((string) data_get($res->json(), 'candidates.0.content.parts.0.text')) : '';
    }
}
