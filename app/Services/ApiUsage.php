<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Compteur des appels aux API payantes (Google Places, Gemini, OpenAI), branché sur tous les appels HTTP sortants.
 * Le code de facturation (SKU) Places dépend des champs demandés (X-Goog-FieldMask), comme chez Google.
 */
class ApiUsage
{
    /** Tarifs publics indicatifs en USD pour 1000 appels (ou par image), et gratuité mensuelle. Modifiables dans config/joow.php. */
    public const LABELS = [
        'places.autocomplete' => 'Places · Autocomplétion',
        'places.text_ids' => 'Places · Recherche texte (identifiants seuls)',
        'places.text_pro' => 'Places · Recherche texte Pro',
        'places.text_enterprise' => 'Places · Recherche texte Enterprise',
        'places.details_essentials' => 'Places · Fiche Essentials',
        'places.details_pro' => 'Places · Fiche Pro',
        'places.details_enterprise' => 'Places · Fiche Enterprise',
        'places.details_atmosphere' => 'Places · Fiche Enterprise + Atmosphere',
        'places.photo' => 'Places · Photos',
        'gemini.text' => 'Gemini · Texte',
        'gemini.image_pro' => 'Gemini · Image Pro',
        'gemini.image_flash' => 'Gemini · Image Flash',
        'openai.image' => 'OpenAI · Image',
    ];

    private const ENTERPRISE = ['rating', 'userRatingCount', 'regularOpeningHours', 'currentOpeningHours', 'websiteUri', 'nationalPhoneNumber', 'internationalPhoneNumber', 'priceLevel'];

    private const ATMOSPHERE = ['reviews', 'editorialSummary', 'generativeSummary', 'servesBreakfast', 'servesLunch', 'servesDinner', 'takeout', 'delivery', 'dineIn', 'reservable', 'outdoorSeating', 'goodForChildren', 'allowsDogs'];

    public static function sku(RequestInterface $req): ?string
    {
        $host = $req->getUri()->getHost();
        $path = $req->getUri()->getPath();
        if ($host === 'places.googleapis.com') {
            if (str_contains($path, ':autocomplete')) {
                return 'places.autocomplete';
            }
            if (str_ends_with($path, '/media')) {
                return 'places.photo';
            }
            $mask = $req->getHeaderLine('X-Goog-FieldMask');
            $fields = array_map(fn ($f) => preg_replace('/^places\./', '', trim($f)), explode(',', $mask));
            $tier = match (true) {
                (bool) array_intersect($fields, self::ATMOSPHERE) => 'atmosphere',
                (bool) array_intersect($fields, self::ENTERPRISE) => 'enterprise',
                $fields === ['id'] || $fields === ['name'] || $fields === ['id', 'name'] => 'ids',
                default => 'pro',
            };
            if (str_contains($path, ':searchText')) {
                return 'places.text_'.($tier === 'atmosphere' ? 'enterprise' : $tier);
            }
            if (str_contains($path, '/places/')) {
                return 'places.details_'.($tier === 'ids' ? 'essentials' : $tier);
            }

            return null;
        }
        if ($host === 'generativelanguage.googleapis.com' && str_contains($path, ':generateContent')) {
            return str_contains($path, '-image') ? (str_contains($path, 'pro') ? 'gemini.image_pro' : 'gemini.image_flash') : 'gemini.text';
        }
        if ($host === 'api.openai.com' && str_contains($path, '/images/')) {
            return 'openai.image';
        }

        return null;
    }

    /** Appelé pour chaque réponse HTTP sortante : seuls les appels réussis sont comptés (comme la facturation). */
    public static function record(RequestInterface $req, ResponseInterface $res): void
    {
        try {
            $sku = self::sku($req);
            if (! $sku || $res->getStatusCode() >= 400) {
                return;
            }
            $units = 0;
            if ($sku === 'gemini.text') {
                $body = (string) $res->getBody();
                $res->getBody()->rewind();
                $d = json_decode($body, true);
                $units = (int) data_get($d, 'usageMetadata.totalTokenCount', 0);
            }
            self::add($sku, 1, $units);
        } catch (\Throwable) {
            // le comptage ne doit jamais gêner l'appel lui-même
        }
    }

    public static function add(string $sku, int $count = 1, int $units = 0): void
    {
        $day = now()->toDateString();
        $q = DB::table('api_usage')->where('day', $day)->where('sku', $sku);
        if ($q->increment('count', $count, ['units' => DB::raw('units + '.$units)])) {
            return;
        }
        try {
            DB::table('api_usage')->insert(['day' => $day, 'sku' => $sku, 'count' => $count, 'units' => $units]);
        } catch (\Throwable) {
            DB::table('api_usage')->where('day', $day)->where('sku', $sku)->increment('count', $count, ['units' => DB::raw('units + '.$units)]);
        }
    }

    /** Synthèse pour le back-office : par SKU, aujourd'hui / 7 j / mois en cours, coût estimé du mois (gratuités déduites), et 14 derniers jours. */
    public static function report(): array
    {
        $prices = (array) config('joow.api_prices', []);
        $rate = (float) config('joow.usd_eur', 0.88);
        $from = now()->subDays(13)->toDateString();
        $month = now()->startOfMonth()->toDateString();
        $rows = DB::table('api_usage')->where('day', '>=', min($from, $month))->get();
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString())->all();
        $out = [];
        foreach ($rows->groupBy('sku') as $sku => $g) {
            $p = $prices[$sku] ?? ['per' => 1000, 'usd' => 0, 'free' => 0];
            $m = (int) $g->where('day', '>=', $month)->sum('count');
            $billable = max(0, $m - (int) ($p['free'] ?? 0));
            $out[] = [
                'sku' => $sku,
                'label' => self::LABELS[$sku] ?? $sku,
                'today' => (int) $g->where('day', now()->toDateString())->sum('count'),
                'week' => (int) $g->where('day', '>=', now()->subDays(6)->toDateString())->sum('count'),
                'month' => $m,
                'free' => (int) ($p['free'] ?? 0),
                'tokens' => (int) $g->where('day', '>=', $month)->sum('units'),
                'unit_eur' => round(($p['usd'] ?? 0) * $rate / max(1, $p['per'] ?? 1000) * 1000, 2),
                'per' => (int) ($p['per'] ?? 1000),
                'cost_eur' => round($billable * ($p['usd'] ?? 0) / max(1, $p['per'] ?? 1000) * $rate, 2),
                'daily' => array_map(fn ($d) => (int) $g->where('day', $d)->sum('count'), $days),
            ];
        }
        usort($out, fn ($a, $b) => $b['cost_eur'] <=> $a['cost_eur'] ?: $b['month'] <=> $a['month']);

        return ['rows' => $out, 'days' => $days, 'total_eur' => round(array_sum(array_column($out, 'cost_eur')), 2)];
    }
}
