<?php

namespace App\Services\Ads;

use App\Models\Site;
use App\Services\Gemini;
use App\Services\ImageStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pub finale créée par l'IA image « Pro » : visuel + typographie + offre + marque dans une seule image,
 * à partir du brief créatif (objectif, insight, concept, direction artistique) et des vraies photos.
 * Chaque rendu passe un contrôle qualité (orthographe exacte du texte, lisibilité mobile, impact) ;
 * en cas de défaut, une seconde tentative corrige les points relevés.
 */
class AdCreative
{
    public const ASPECTS = ['portrait' => '4:5', 'square' => '1:1', 'story' => '9:16'];

    public function __construct(private AdVisuals $visuals) {}

    /** @return array{url: string, format: string, qa: array}|array{error: string, status: int} */
    public function render(Site $site, array $c, string $format = 'portrait', bool $admin = false, ?string $from = null): array
    {
        $pay = $this->visuals->payment($site, $admin);
        if (isset($pay['error'])) {
            return $pay;
        }
        $aspect = self::ASPECTS[$format] ?? '4:5';
        $refs = [];
        if ($from) {
            $refs[] = $this->fetch($from); // déclinaison : on part de la pub déjà validée
        }
        foreach (array_slice((array) ($c['refs'] ?? []), 0, $from ? 1 : 2) as $u) {
            $refs[] = $this->fetch($u);
        }
        $refs = array_values(array_filter($refs));

        $prompt = $this->prompt($site, $c, $format, (bool) $from, (bool) count($refs));
        $bytes = null;
        $qa = ['score' => null, 'text_ok' => null, 'issues' => []];
        $t0 = microtime(true);
        for ($try = 0; $try < 2; $try++) {
            if ($try && microtime(true) - $t0 > 90) {
                break; // pas le temps d'une seconde version : on garde la première
            }
            $bytes = Gemini::image($prompt.($try && $qa['issues'] ? "\n\nCORRIGE IMPÉRATIVEMENT ces défauts de la version précédente : ".implode(' ; ', $qa['issues']) : ''), $aspect, $refs, 'AdCreative');
            if (! $bytes && $refs && ! $from && microtime(true) - $t0 < 90) {
                // Photo de référence refusée par le modèle : on retente sans elle
                Log::warning("AdCreative {$site->slug} {$c['key']} sans photo de référence : ".implode(' | ', Gemini::$lastImageErrors));
                $refs = [];
                $prompt = $this->prompt($site, $c, $format, false, false);
                $bytes = Gemini::image($prompt, $aspect, null, 'AdCreative');
            }
            if (! $bytes) {
                break;
            }
            $qa = $this->review($bytes, $c);
            if ($qa['text_ok'] !== false && ($qa['score'] ?? 10) >= 7) {
                break;
            }
            Log::info("AdCreative {$site->slug} {$c['key']} : contrôle qualité {$qa['score']}/10, nouvelle tentative (".implode(' ; ', $qa['issues']).')');
        }
        if (! $bytes) {
            $why = implode(' | ', Gemini::$lastImageErrors) ?: (config('services.gemini.key') ? 'aucun modèle image disponible' : 'clé Gemini absente');
            Log::warning("AdCreative {$site->slug} {$c['key']} échec : $why");

            return ['error' => 'La création de la pub n\'a pas abouti. Réessayez dans un instant (rien n\'a été décompté).', 'status' => 502, 'detail' => $why];
        }
        $name = app(ImageStore::class)->storeBytes($bytes, $site->slug);
        if (! $name) {
            return ['error' => 'Image illisible, réessayez.', 'status' => 502];
        }
        $url = rtrim(config('app.url'), '/')."/media/{$site->slug}/$name";
        $this->visuals->commit($site, $pay['pay_with'], ['url' => $url, 'format' => $format, 'brief' => 'Pub « '.($c['headline'] ?? $c['hook'] ?? '').' »', 'model' => Gemini::$lastImageModel]);
        Log::info("AdCreative {$site->slug} {$c['key']} $aspect via ".Gemini::$lastImageModel." · QA {$qa['score']}/10");

        return ['url' => $url, 'format' => $format, 'qa' => $qa, 'model' => Gemini::$lastImageModel];
    }

    public function prompt(Site $site, array $c, string $format, bool $decline, bool $hasRefs): string
    {
        $label = config("sectors.{$site->sector}.label") ?? $site->sector;
        $color = $site->site_data['accent'] ?? (config("sectors.{$site->sector}.color") ?? '#4f46e5');
        $aspect = self::ASPECTS[$format] ?? '4:5';
        $rating = $site->rating && $site->reviews_count ? '★ '.number_format((float) $site->rating, 1, ',', '').'/5 · '.$site->reviews_count.' avis Google' : null;
        $texts = array_filter([
            'Titre (très grand, lisible en miniature sur un téléphone)' => $c['headline'] ?? $c['hook'] ?? null,
            'Sous-titre' => $c['sub'] ?? null,
            'Badge d\'offre (pastille ou bandeau très visible)' => $c['badge'] ?? null,
            'Bouton d\'appel à l\'action' => $c['cta'] ?? null,
            'Nom de l\'établissement (discret, comme une signature de marque)' => $site->name,
            'Preuve (petite, près du nom)' => in_array($c['axis'] ?? '', ['preuve sociale'], true) || $rating ? $rating : null,
        ]);
        $textBlock = collect($texts)->map(fn ($t, $k) => "- $k : « $t »")->implode("\n");

        if ($decline) {
            return "Décline la publicité jointe (première image) au format $aspect.\n"
                ."Garde EXACTEMENT le même design, la même photo, les mêmes couleurs, la même typographie et les mêmes textes, mot pour mot. "
                ."Recompose seulement pour ce format".($format === 'story' ? ' : laisse vides les 14 % du haut et les 20 % du bas (zones masquées par l\'interface Stories).' : '.')
                ."\nTextes à reprendre à l'identique :\n$textBlock";
        }

        return implode("\n", array_filter([
            "Crée une publicité Facebook / Instagram finie, prête à publier, au format $aspect, de niveau agence haut de gamme.",
            "Annonceur : « {$site->name} », {$label}".($site->city ? " à {$site->city}" : '').'.',
            'Objectif : '.($c['why'] ?? 'obtenir des contacts qualifiés').'.',
            'Insight client : '.($c['insight'] ?? '').'.',
            'Idée créative : '.($c['concept'] ?? '').'.',
            'Direction artistique : '.($c['art'] ?? $c['scene'] ?? '').'.',
            $hasRefs ? "Utilise les photos jointes de l'établissement comme base réelle (même lieu, même matériel, mêmes personnes) : sublime-les (lumière, cadrage, retouche premium) sans les déformer ni inventer de logo." : 'Image photographique réaliste et crédible pour un professionnel local, pas un rendu 3D ni une illustration générique.',
            "Textes à écrire sur l'image, À LA LETTRE (orthographe et accents français exacts), et AUCUN autre texte :\n$textBlock",
            "Couleur principale de la marque : $color (badge, bouton, accents), avec un contraste fort et lisible.",
            'Règles : un seul message, hiérarchie évidente (le titre se lit en premier), typographie sans-serif moderne et grasse, texte sur moins de 25 % de la surface, '
                .'aucun faux logo, aucune marque inventée, aucun filigrane, aucune mention « IA ». Le visuel doit arrêter le défilement en moins d\'une seconde.',
            $format === 'story' ? "Format Stories : laisse vides les 14 % du haut et les 20 % du bas de l'image." : null,
        ]));
    }

    /** Contrôle qualité par lecture de l'image : texte exact, lisibilité, impact publicitaire. */
    public function review(string $bytes, array $c): array
    {
        $expected = array_values(array_filter([$c['headline'] ?? $c['hook'] ?? null, $c['sub'] ?? null, $c['badge'] ?? null, $c['cta'] ?? null]));
        $prompt = "Tu contrôles une publicité Meta avant diffusion. Textes attendus (à l'identique) :\n- ".implode("\n- ", $expected)
            ."\nRéponds UNIQUEMENT en JSON : {\"text_ok\": true si tous les textes attendus sont présents et sans faute (accents compris) et sans texte parasite ou charabia, "
            ."\"score\": note de 1 à 10 de l'efficacité publicitaire (impact, lisibilité sur mobile, professionnalisme), \"issues\": [\"défauts précis à corriger\"]}";
        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt], ['inline_data' => ['mime_type' => str_starts_with($bytes, "\x89PNG") ? 'image/png' : 'image/jpeg', 'data' => base64_encode($bytes)]]]]],
                'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 600, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 45, 'AdCreative.qa');
            $d = json_decode((string) Gemini::text($res), true);
            if (is_array($d)) {
                return ['score' => isset($d['score']) ? (int) $d['score'] : null, 'text_ok' => isset($d['text_ok']) ? (bool) $d['text_ok'] : null, 'issues' => array_slice(array_map('strval', (array) ($d['issues'] ?? [])), 0, 5)];
            }
        } catch (\Throwable $e) {
            Log::info('AdCreative QA : '.$e->getMessage());
        }

        return ['score' => null, 'text_ok' => null, 'issues' => []];
    }

    private function fetch(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        try {
            // Nos propres médias : lus directement sur le disque (pas d'aller-retour par le proxy)
            if (preg_match('#/media/([^/]+)/([^/?]+)#', $url, $m) && \Illuminate\Support\Facades\Storage::disk('local')->exists("uploads/{$m[1]}/{$m[2]}")) {
                $raw = \Illuminate\Support\Facades\Storage::disk('local')->get("uploads/{$m[1]}/{$m[2]}");
            } elseif (str_starts_with($url, 'http')) {
                $r = Http::timeout(15)->get($url);
                $raw = $r->successful() ? $r->body() : null;
            } else {
                return null;
            }

            // Toujours un vrai JPEG de taille raisonnable (un fichier HTML, AVIF ou géant fait échouer tous les modèles)
            return $raw ? app(ImageStore::class)->toJpeg($raw) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
