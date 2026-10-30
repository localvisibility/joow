<?php

namespace App\Services\Domains;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Nom de domaine disponible au nom de l'établissement, montré AVANT le paiement
 * (« provencedrive.fr est disponible, inclus dans votre formule »). Résultat mis en cache 12 h.
 */
class DomainSuggest
{
    public function __construct(private HostingerClient $hostinger) {}

    /** Noms candidats : partie principale du nom (avant « | », « - », « ( »…), collée puis avec tirets. */
    public static function candidates(string $name, ?string $city = null): array
    {
        $main = trim(preg_split('/\s+[|–—\-(:,·]\s*|\s*\|\s*/u', $name)[0] ?? $name);
        $slug = Str::slug($main);
        $words = array_values(array_filter(explode('-', $slug), fn ($w) => ! in_array($w, ['le', 'la', 'les', 'l', 'de', 'du', 'des', 'et', 'a', 'au', 'aux', 'chez', 'sarl', 'sas', 'eurl'], true)));
        $short = implode('', array_slice($words, 0, 4));
        $out = [];
        foreach ([str_replace('-', '', $slug), $short, $slug, $city ? $short.'-'.Str::slug($city) : null] as $c) {
            if ($c && strlen($c) >= 3 && strlen($c) <= 40 && ! in_array($c, $out, true)) {
                $out[] = $c;
            }
        }

        return array_slice($out, 0, 3);
    }

    /** ['domain' => 'provencedrive.fr', 'available' => true] ou null si on ne sait pas. */
    public function forSite(Site $site): ?array
    {
        if (! $this->hostinger->enabled() || ! $site->name) {
            return null;
        }
        $names = self::candidates((string) $site->name, $site->city);
        if (! $names) {
            return null;
        }

        return Cache::remember('joow:domain-suggest:'.md5(implode('|', $names)), now()->addHours(12), function () use ($names) {
            try {
                foreach ($names as $n) {
                    $res = collect($this->hostinger->search($n, ['fr', 'com']))->where('available', true)->sortBy(fn ($r) => $r['tld'] === 'fr' ? 0 : 1)->first();
                    if ($res) {
                        return ['domain' => $res['domain'], 'available' => true];
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Suggestion de domaine : '.$e->getMessage());

                return null;
            }

            return ['domain' => null, 'available' => false];
        });
    }
}
