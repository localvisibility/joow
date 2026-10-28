<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Banque d'images à la demande : quand l'établissement n'a pas de photo pour un sujet (haut de page,
 * équipe, une prestation précise, galerie), on cherche LA bonne image plutôt qu'un visuel générique.
 *
 *  1. l'IA traduit chaque sujet en requêtes de banque d'images précises (anglais, vocabulaire photo) ;
 *  2. recherche chez les fournisseurs disponibles (Pexels, Pixabay, Unsplash selon les clés, Openverse sans clé) ;
 *  3. pour les emplacements très visibles, l'IA regarde les candidates et choisit la plus juste
 *     (sujet exact, lumière, cadrage, pas de texte ni de cliché) ;
 *  4. sans aucun résultat, repli sur les visuels du métier (config/sectors.php).
 */
class StockPhotos
{
    private const UA = 'Joow/1.0 (+https://joow.fr)';

    public function enabled(): bool
    {
        return (bool) (config('services.stock.pexels') || config('services.stock.pixabay') || config('services.stock.unsplash') || config('services.stock.openverse', true));
    }

    /**
     * Remplit des emplacements sans photo.
     *
     * @param  array  $slots  [key => ['subject' => "sujet en français", 'orientation' => 'landscape'|'portrait', 'pick' => bool (choix par vision)]]
     * @param  array  $exclude  URLs déjà utilisées
     * @return array [key => ['url' => …, 'credit' => …, 'provider' => …, 'query' => …]]
     */
    public function fill(array $slots, string $sectorLabel, string $sectorBrief = '', array $exclude = [], array $fallback = []): array
    {
        if (! $slots) {
            return [];
        }
        $t0 = microtime(true);
        $queries = $this->queriesFor($slots, $sectorLabel, $sectorBrief);
        $tQueries = round(microtime(true) - $t0, 1);
        $used = array_fill_keys($exclude, true);
        $out = [];
        $toNotify = [];

        // Toutes les recherches en parallèle : d'abord la requête précise de chaque emplacement,
        // puis la requête large pour ceux qui ont moins de 2 candidates.
        $results = [];
        foreach ([0, 1] as $round) {
            $batch = [];
            foreach ($slots as $key => $slot) {
                $qs = $queries[$key] ?? [$this->naiveQuery($slot['subject'] ?? $sectorLabel)];
                $q = $qs[$round] ?? null;
                if ($q && ($round === 0 || count($results[$key] ?? []) < 2)) {
                    $batch[$key] = [$q, $slot['orientation'] ?? 'landscape'];
                }
            }
            foreach ($this->searchMany($batch) as $key => $found) {
                foreach ($found as $c) {
                    $results[$key][] = $c;
                }
            }
        }
        $tSearch = round(microtime(true) - $t0 - $tQueries, 1);

        foreach ($slots as $key => $slot) {
            $cands = [];
            foreach ($results[$key] ?? [] as $c) {
                if (! isset($used[$c['url']]) && ! collect($cands)->contains('url', $c['url'])) {
                    $cands[] = $c;
                }
            }
            if (! $cands) {
                // Repli : visuels du métier non encore utilisés
                foreach ($fallback as $u) {
                    if (! isset($used[$u])) {
                        $out[$key] = ['url' => $u, 'credit' => null, 'provider' => 'sector', 'query' => null];
                        $used[$u] = true;
                        break;
                    }
                }

                continue;
            }
            $best = ($slot['pick'] ?? false) && count($cands) > 1 ? $this->pick($cands, $slot['subject'] ?? '', $sectorLabel) : $cands[0];
            $out[$key] = ['url' => $best['url'], 'credit' => $best['credit'], 'provider' => $best['provider'], 'query' => $best['query'] ?? null];
            $used[$best['url']] = true;
            if (($best['provider'] ?? '') === 'unsplash' && ! empty($best['download'])) {
                $toNotify[] = $best['download'];
            }
        }
        $tPick = round(microtime(true) - $t0 - $tQueries - $tSearch, 1);
        $this->notifyUses($toNotify);

        Log::info(sprintf('StockPhotos : %d/%d emplacement(s) comblé(s) — %s · requêtes %ss, recherche %ss, choix %ss, total %ss', count($out), count($slots), collect($out)->map(fn ($v, $k) => "$k:{$v['provider']}")->implode(', '), $tQueries, $tSearch, $tPick, round(microtime(true) - $t0, 1)));

        return $out;
    }

    /** Requêtes de banque d'images (anglais) par emplacement, produites par l'IA à partir du sujet et du métier. */
    public function queriesFor(array $slots, string $sectorLabel, string $sectorBrief = ''): array
    {
        $naive = collect($slots)->map(fn ($s) => [$this->naiveQuery($s['subject'] ?? $sectorLabel)])->all();
        if (! Gemini::enabled()) {
            return $naive;
        }
        $key = 'joow:stock:q:'.md5($sectorLabel.'|'.json_encode(collect($slots)->map(fn ($s) => $s['subject'] ?? '')->values()));

        return Cache::remember($key, now()->addDays(30), function () use ($slots, $sectorLabel, $sectorBrief, $naive) {
            $list = collect($slots)->map(fn ($s, $k) => "- $k : ".($s['subject'] ?? $sectorLabel).(! empty($s['role']) ? " (rôle : {$s['role']})" : ''))->implode("\n");
            $brief = $sectorBrief !== '' ? "\nContexte métier : ".Str::limit($sectorBrief, 300) : '';
            $prompt = <<<TXT
Tu cherches des photos de banque d'images (Pexels/Unsplash) pour le site d'un établissement : {$sectorLabel}.{$brief}
Pour chaque emplacement ci-dessous, donne 2 requêtes de recherche EN ANGLAIS, de 2 à 5 mots, très concrètes et photographiques (sujet + action ou lieu + ambiance), qui donneront une photo réaliste et crédible pour une petite entreprise française : pas de cliché « business » (poignées de main, graphiques), pas de visage souriant posé, pas d'objet isolé sur fond blanc sauf si le sujet est un produit.
La 1re requête est la plus précise, la 2e plus large (le métier en général). Emplacement « hero » = plan large d'ambiance du métier ; « about » = artisan/équipe au travail, de dos ou de profil, ou lieu de travail ; prestation = le geste ou l'objet de cette prestation.

Emplacements :
{$list}

Réponds UNIQUEMENT en JSON : {"<clé>": ["requête 1", "requête 2"], …}
TXT;
            try {
                $res = Gemini::generate([
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.3, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 800, 'thinkingConfig' => ['thinkingBudget' => 0]],
                ], 20, 'StockPhotos', fast: true);
                $d = json_decode(Gemini::text($res), true);
                if (! is_array($d)) {
                    return $naive;
                }
                $out = [];
                foreach ($slots as $k => $s) {
                    $qs = collect((array) ($d[$k] ?? []))->filter(fn ($q) => is_string($q) && strlen(trim($q)) >= 3)->map(fn ($q) => Str::limit(trim($q), 60, ''))->values()->all();
                    $out[$k] = $qs ?: $naive[$k];
                }

                return $out;
            } catch (\Throwable $e) {
                Log::info('StockPhotos requêtes : '.$e->getMessage());

                return $naive;
            }
        });
    }

    /**
     * Plusieurs recherches en une fois, en parallèle, chez le fournisseur prioritaire ; les requêtes sans
     * résultat retombent sur les fournisseurs suivants via search(). Retourne [clé => candidates (avec 'query')].
     *
     * @param  array  $batch  [clé => [requête, orientation]]
     */
    public function searchMany(array $batch): array
    {
        $out = [];
        $primary = $this->providers()[0] ?? null;
        $pending = [];
        foreach ($batch as $key => [$q, $orientation]) {
            $cacheKey = $primary ? "joow:stock:$primary:".md5("$q|6|$orientation") : null;
            if ($cacheKey && Cache::has($cacheKey)) {
                $out[$key] = array_map(fn ($c) => $c + ['query' => $q], Cache::get($cacheKey));
            } else {
                $pending[$key] = [$q, $orientation];
            }
        }
        if ($pending && $primary && method_exists($this, $primary.'Request')) {
            try {
                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($key) => $this->{$primary.'Request'}($pool->as((string) $key), $pending[$key][0], 6, $pending[$key][1]),
                    array_keys($pending)
                ));
            } catch (\Throwable $e) {
                Log::info("StockPhotos pool $primary : ".$e->getMessage());
                $responses = [];
            }
            foreach ($pending as $key => [$q, $orientation]) {
                $r = $responses[(string) $key] ?? null;
                $found = $r instanceof \Illuminate\Http\Client\Response ? $this->{$primary.'Parse'}($r) : [];
                if ($found) {
                    Cache::put("joow:stock:$primary:".md5("$q|6|$orientation"), $found, now()->addDays(7));
                    $out[$key] = array_map(fn ($c) => $c + ['query' => $q], $found);
                    unset($pending[$key]);
                }
            }
        }
        // Reste : fournisseurs suivants, un par un
        foreach ($pending as $key => [$q, $orientation]) {
            $out[$key] = array_map(fn ($c) => $c + ['query' => $q], $this->search($q, 6, $orientation));
        }

        return $out;
    }

    private function providers(): array
    {
        return array_values(array_filter([
            config('services.stock.pexels') ? 'pexels' : null,
            config('services.stock.pixabay') ? 'pixabay' : null,
            config('services.stock.unsplash') ? 'unsplash' : null,
            config('services.stock.openverse', true) ? 'openverse' : null,
        ]));
    }

    /** Recherche chez le premier fournisseur qui répond (résultats mis en cache 7 jours). */
    public function search(string $query, int $n = 6, string $orientation = 'landscape'): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        foreach ($this->providers() as $p) {
            $res = Cache::remember("joow:stock:$p:".md5("$query|$n|$orientation"), now()->addDays(7), function () use ($p, $query, $n, $orientation) {
                try {
                    return $this->$p($query, $n, $orientation);
                } catch (\Throwable $e) {
                    Log::info("StockPhotos $p : ".$e->getMessage());

                    return [];
                }
            });
            if ($res) {
                return $res;
            }
        }

        return [];
    }

    /** Banque d'images du Studio : plusieurs requêtes fusionnées, sans doublon. */
    public function library(array $queries, int $per = 12, string $orientation = 'landscape'): array
    {
        $out = [];
        foreach (array_slice(array_values(array_filter(array_map('trim', $queries))), 0, 3) as $q) {
            foreach ($this->search($q, $per, $orientation) as $r) {
                $out[$r['url']] ??= $r + ['query' => $q];
            }
        }

        return array_values($out);
    }

    /**
     * Recherche intelligente : la demande (en français, souvent vague) et le contexte (métier, ville,
     * emplacement de la photo, prestation concernée) deviennent 3 requêtes précises en anglais,
     * plus des suggestions en français. Sans demande : propose d'emblée la photo idéale pour l'emplacement.
     *
     * @return array{queries: string[], query_fr: string, suggestions: string[]}
     */
    public function smartQueries(string $sectorLabel, string $place, string $subject, string $userQuery): array
    {
        $fallback = ['queries' => array_values(array_filter([$userQuery ?: null, $subject ?: null, $sectorLabel])), 'query_fr' => $userQuery ?: $subject, 'suggestions' => []];
        if (! Gemini::enabled()) {
            return $fallback;
        }
        $key = 'joow:stock:smart:'.md5("$sectorLabel|$place|$subject|$userQuery");

        return Cache::remember($key, now()->addDays(7), function () use ($sectorLabel, $place, $subject, $userQuery, $fallback) {
            $ask = $userQuery !== '' ? "Demande du client : « {$userQuery} »." : 'Le client n\'a encore rien tapé : propose la photo idéale pour cet emplacement.';
            $prompt = <<<TXT
Tu aides un commerçant à choisir une photo de banque d'images (Unsplash, Pexels) pour son site.
Métier : {$sectorLabel}. Emplacement de la photo : {$place}. Sujet de la section : {$subject}.
{$ask}
Réponds UNIQUEMENT en JSON :
{"queries": ["3 requêtes de recherche EN ANGLAIS, 2 à 5 mots, concrètes et visuelles (ce qu'on voit sur la photo), de la plus fidèle à la plus large"],
 "query_fr": "la demande reformulée en 2 à 6 mots en français",
 "suggestions": ["5 idées de photos alternatives en français, 2 à 4 mots chacune, adaptées au métier et à l'emplacement"]}
TXT;
            try {
                $res = Gemini::generate(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.4, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 300, 'thinkingConfig' => ['thinkingBudget' => 0]]], 15, 'StockPhotos smart', fast: true);
                $d = json_decode(Gemini::text($res), true);
                $clean = fn ($a, $n) => array_slice(array_values(array_filter(array_map(fn ($x) => Str::limit(trim((string) $x), 60, ''), (array) $a))), 0, $n);
                $q = $clean($d['queries'] ?? [], 3);

                return $q ? ['queries' => $q, 'query_fr' => Str::limit(trim((string) ($d['query_fr'] ?? $userQuery)), 60, ''), 'suggestions' => $clean($d['suggestions'] ?? [], 5)] : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        });
    }

    /** Signale l'utilisation d'une photo Unsplash (obligation de l'API). */
    public function trackUse(?string $downloadLocation): void
    {
        if ($downloadLocation && Str::startsWith($downloadLocation, 'https://api.unsplash.com/') && config('services.stock.unsplash')) {
            $this->notifyUses([$downloadLocation]);
        }
    }

    /** L'IA regarde les candidates (vignettes) et choisit la plus juste pour le sujet. */
    private function pick(array $cands, string $subject, string $sectorLabel): array
    {
        if (! Gemini::enabled()) {
            return $cands[0];
        }
        $cands = array_slice($cands, 0, 5);
        $parts = [];
        $shown = [];
        try {
            $responses = Http::pool(fn ($pool) => array_map(fn ($c, $i) => $pool->as((string) $i)->timeout(10)->withHeaders(['User-Agent' => self::UA])->get($c['thumb']), $cands, array_keys($cands)));
        } catch (\Throwable) {
            return $cands[0];
        }
        foreach ($cands as $i => $c) {
            $r = $responses[(string) $i] ?? null;
            if ($r instanceof \Illuminate\Http\Client\Response && $r->successful() && strlen($r->body()) > 2000 && strlen($r->body()) < 3_000_000) {
                $shown[] = $i;
                $parts[] = ['text' => 'Candidate #'.count($shown)];
                $parts[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($r->body())]];
            }
        }
        if (count($shown) < 2) {
            return $cands[$shown[0] ?? 0];
        }
        $n = count($shown);
        $parts[] = ['text' => <<<TXT
Tu es directeur artistique pour le site d'un établissement : {$sectorLabel}. Sujet de l'emplacement : « {$subject} ».
Parmi les {$n} candidates ci-dessus, choisis celle qui illustre le plus justement ce sujet, en photo réaliste et crédible pour une petite entreprise française : sujet exact et reconnaissable, lumière naturelle, cadrage large exploitable, aucun texte ni logo ni filigrane, pas de cliché posé, pas d'ambiance « corporate » américaine. Élimine toute image hors sujet.
Réponds UNIQUEMENT en JSON : {"best": <numéro>, "reason": "5 mots"}
TXT];
        try {
            $res = Gemini::generate([
                'contents' => [['parts' => $parts]],
                'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 64, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 25, 'StockPhotos pick', fast: true);
            $best = (int) (json_decode(Gemini::text($res), true)['best'] ?? 1);
            $idx = $shown[max(0, min($n, $best) - 1)] ?? $shown[0];

            return $cands[$idx];
        } catch (\Throwable) {
            return $cands[$shown[0]];
        }
    }

    /** Unsplash exige un appel à download_location quand une photo est réellement utilisée (en parallèle, non bloquant). */
    private function notifyUses(array $urls): void
    {
        if (! $urls) {
            return;
        }
        try {
            Http::pool(fn ($pool) => array_map(fn ($u) => $pool->timeout(4)->withHeaders(['Authorization' => 'Client-ID '.config('services.stock.unsplash'), 'Accept-Version' => 'v1', 'User-Agent' => self::UA])->get($u), $urls));
        } catch (\Throwable) {
            // non bloquant
        }
    }

    // ───────────────────────────── fournisseurs ─────────────────────────────

    private function pexels(string $q, int $n, string $orientation): array
    {
        return $this->pexelsParse($this->pexelsRequest(Http::timeout(12), $q, $n, $orientation));
    }

    private function pexelsRequest($http, string $q, int $n, string $orientation)
    {
        return $http->withHeaders(['Authorization' => config('services.stock.pexels'), 'User-Agent' => self::UA])
            ->get('https://api.pexels.com/v1/search', ['query' => $q, 'per_page' => $n, 'orientation' => $orientation, 'size' => 'large', 'locale' => 'fr-FR']);
    }

    private function pexelsParse($r): array
    {
        if (! $r instanceof \Illuminate\Http\Client\Response || ! $r->successful()) {
            return [];
        }

        return collect($r->json('photos') ?? [])->map(fn ($p) => [
            'url'      => ($p['src']['large2x'] ?? $p['src']['large'] ?? $p['src']['original'] ?? null),
            'thumb'    => $p['src']['medium'] ?? ($p['src']['small'] ?? null),
            'alt'      => $p['alt'] ?? '',
            'credit'   => 'Photo : '.($p['photographer'] ?? 'Pexels').' / Pexels',
            'provider' => 'pexels',
        ])->filter(fn ($p) => $p['url'] && $p['thumb'])->values()->all();
    }

    private function pixabay(string $q, int $n, string $orientation): array
    {
        $r = Http::timeout(12)->withHeaders(['User-Agent' => self::UA])->get('https://pixabay.com/api/', [
            'key' => config('services.stock.pixabay'), 'q' => $q, 'image_type' => 'photo', 'orientation' => $orientation === 'portrait' ? 'vertical' : 'horizontal',
            'per_page' => max(3, $n), 'safesearch' => 'true', 'min_width' => 1200, 'editors_choice' => 'false',
        ]);
        if (! $r->successful()) {
            return [];
        }

        return collect($r->json('hits') ?? [])->map(fn ($p) => [
            'url'      => $p['largeImageURL'] ?? ($p['webformatURL'] ?? null),
            'thumb'    => $p['webformatURL'] ?? ($p['previewURL'] ?? null),
            'alt'      => $p['tags'] ?? '',
            'credit'   => 'Photo : '.($p['user'] ?? 'Pixabay').' / Pixabay',
            'provider' => 'pixabay',
        ])->filter(fn ($p) => $p['url'] && $p['thumb'])->values()->all();
    }

    private function unsplash(string $q, int $n, string $orientation): array
    {
        return $this->unsplashParse($this->unsplashRequest(Http::timeout(12), $q, $n, $orientation));
    }

    private function unsplashRequest($http, string $q, int $n, string $orientation)
    {
        return $http->withHeaders(['Authorization' => 'Client-ID '.config('services.stock.unsplash'), 'Accept-Version' => 'v1', 'User-Agent' => self::UA])
            ->get('https://api.unsplash.com/search/photos', ['query' => $q, 'per_page' => $n, 'orientation' => $orientation, 'content_filter' => 'high']);
    }

    private function unsplashParse($r): array
    {
        if (! $r instanceof \Illuminate\Http\Client\Response || ! $r->successful()) {
            return [];
        }

        return collect($r->json('results') ?? [])->map(fn ($p) => [
            'url'      => isset($p['urls']['raw']) ? $p['urls']['raw'].'&w=1920&q=82&auto=format&fit=crop' : ($p['urls']['regular'] ?? null),
            'thumb'    => $p['urls']['small'] ?? null,
            'alt'      => $p['alt_description'] ?? '',
            'credit'   => 'Photo : '.($p['user']['name'] ?? 'Unsplash').' / Unsplash'.(! empty($p['user']['links']['html']) ? ' — '.$p['user']['links']['html'] : ''),
            'provider' => 'unsplash',
            // Règle de l'API Unsplash : signaler l'utilisation d'une photo (comptabilisée comme téléchargement)
            'download' => $p['links']['download_location'] ?? null,
        ])->filter(fn ($p) => $p['url'] && $p['thumb'])->values()->all();
    }

    /** Sans clé : Openverse (images sous licence commerciale), qualité variable → le choix par vision est important. */
    private function openverse(string $q, int $n, string $orientation): array
    {
        $r = Http::timeout(12)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])->get('https://api.openverse.org/v1/images/', [
            'q' => $q, 'license_type' => 'commercial', 'size' => 'large', 'page_size' => max(5, $n), 'mature' => 'false',
            'aspect_ratio' => $orientation === 'portrait' ? 'tall' : 'wide', 'source' => 'flickr,wikimedia,stocksnap,rawpixel',
        ]);
        if (! $r->successful()) {
            return [];
        }

        return collect($r->json('results') ?? [])
            ->filter(fn ($p) => (int) ($p['width'] ?? 0) >= 1200 && ! empty($p['url']))
            ->map(fn ($p) => [
                'url'      => $p['url'],
                'thumb'    => $p['thumbnail'] ?? $p['url'],
                'alt'      => $p['title'] ?? '',
                'credit'   => 'Photo : '.($p['creator'] ?? 'Openverse').' ('.($p['license'] ?? 'cc').')',
                'provider' => 'openverse',
            ])->values()->take($n)->all();
    }

    private function naiveQuery(string $subject): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', Str::ascii($subject))), 50, '');
    }
}
