<?php

namespace App\Services\Seo;

use App\Models\Site;

/**
 * Applique les réglages SEO du client au HTML rendu : titre et description par page,
 * textes alternatifs des images, données structurées (entreprise locale, fil d'Ariane).
 * Clé de page : « index » pour l'accueil, sinon le slug de la page.
 */
class SeoApply
{
    public static function key(?string $pageSlug): string
    {
        return $pageSlug ?: 'index';
    }

    public static function baseUrl(Site $site): string
    {
        return ($site->domain_ssl_active && $site->custom_domain) ? 'https://'.$site->custom_domain : 'https://'.$site->slug.'.joow.fr';
    }

    public static function apply(string $html, Site $site, ?string $pageSlug, bool $editMode = false): string
    {
        try {
            return self::transform($html, $site, $pageSlug, $editMode);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SeoApply {$site->slug} : ".$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());

            return $html; // jamais de page cassée à cause du SEO
        }
    }

    private static function transform(string $html, Site $site, ?string $pageSlug, bool $editMode): string
    {
        $orig = $html;
        $seo = (array) ($site->site_data['seo'] ?? []);
        $meta = (array) ($seo['meta'][self::key($pageSlug)] ?? []);
        $attr = fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($t = trim((string) ($meta['title'] ?? ''))) {
            $html = preg_replace('#<title>.*?</title>#s', '<title>'.$attr($t).'</title>', $html, 1);
            $html = preg_replace('#<meta property="og:title" content="[^"]*">#', '<meta property="og:title" content="'.$attr($t).'">', $html, 1);
        }
        if ($d = trim((string) ($meta['description'] ?? ''))) {
            $html = preg_replace('#<meta name="description" content="[^"]*">#', '<meta name="description" content="'.$attr($d).'">', $html, 1);
            $html = preg_replace('#<meta property="og:description" content="[^"]*">#', '<meta property="og:description" content="'.$attr($d).'">', $html, 1);
        }

        $alts = (array) ($seo['alts'] ?? []);
        if ($alts) {
            $html = preg_replace_callback('#<img\b[^>]*>#i', function ($m) use ($alts, $attr) {
                $tag = $m[0];
                if (! preg_match('#\bsrc="([^"]*)"#i', $tag, $s)) {
                    return $tag;
                }
                $alt = $alts[html_entity_decode($s[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')] ?? null;
                if (! is_string($alt) || trim($alt) === '') {
                    return $tag;
                }
                $a = 'alt="'.$attr(trim($alt)).'"';

                return preg_match('#\balt="[^"]*"#i', $tag) ? preg_replace('#\balt="[^"]*"#i', $a, $tag, 1) : preg_replace('#^<img\b#i', '<img '.$a, $tag, 1);
            }, $html);
        }

        if (! $editMode && ! str_contains($html, 'application/ld+json')) {
            $json = json_encode(self::schema($site, $pageSlug), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
            $html = preg_replace('#</head>#i', '<script type="application/ld+json">'.$json."</script>\n</head>", (string) $html, 1);
        }

        return is_string($html) && $html !== '' ? $html : $orig;
    }

    /** Données structurées : uniquement des informations réelles de l'établissement (aucun avis ni prix inventé). */
    public static function schema(Site $site, ?string $pageSlug): array
    {
        $b = (array) ($site->site_data['business'] ?? []);
        $base = self::baseUrl($site);
        $biz = array_filter([
            '@type' => self::type((string) $site->sector),
            '@id' => $base.'/#entreprise',
            'name' => $b['name'] ?? $site->name,
            'url' => $base.'/',
            'telephone' => $b['phone'] ?? $site->phone ?? null,
            'image' => $site->site_data['images']['hero'] ?? ($b['logo'] ?? null),
            'logo' => $b['logo'] ?? null,
            'address' => ($b['address'] ?? null) || $site->city ? array_filter(['@type' => 'PostalAddress', 'streetAddress' => $b['address'] ?? null, 'addressLocality' => $b['city'] ?? $site->city, 'addressCountry' => 'FR']) : null,
            'areaServed' => ($b['city'] ?? $site->city) ?: null,
            'hasMap' => $b['maps_url'] ?? null,
        ]);
        $graph = [$biz, ['@type' => 'WebSite', '@id' => $base.'/#site', 'url' => $base.'/', 'name' => $biz['name'], 'inLanguage' => 'fr-FR']];
        if ($pageSlug) {
            $title = collect($site->site_data['pages'] ?? [])->firstWhere('slug', $pageSlug)['title'] ?? $pageSlug;
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $base.'/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $base.'/'.$pageSlug.'/'],
            ]];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    private static function type(string $sector): string
    {
        $map = ['plomb' => 'Plumber', 'electric' => 'Electrician', 'serrur' => 'Locksmith', 'restaurant' => 'Restaurant', 'pizz' => 'Restaurant', 'boulang' => 'Bakery',
            'coiff' => 'HairSalon', 'barb' => 'HairSalon', 'beaute' => 'BeautySalon', 'esthet' => 'BeautySalon', 'hotel' => 'Hotel', 'gite' => 'LodgingBusiness', 'chambre' => 'BedAndBreakfast',
            'garage' => 'AutoRepair', 'auto' => 'AutoRepair', 'dent' => 'Dentist', 'kine' => 'Physiotherapist', 'medecin' => 'Physician', 'avocat' => 'LegalService',
            'immobil' => 'RealEstateAgent', 'peint' => 'HousePainter', 'couvr' => 'RoofingContractor', 'chauff' => 'HVACBusiness', 'climat' => 'HVACBusiness', 'jardin' => 'LandscapingBusiness',
            'menuis' => 'GeneralContractor', 'macon' => 'GeneralContractor', 'renov' => 'GeneralContractor', 'sport' => 'SportsActivityLocation', 'fitness' => 'ExerciseGym',
            'cafe' => 'CafeOrCoffeeShop', 'bar' => 'BarOrPub', 'fleur' => 'Florist', 'photo' => 'ProfessionalService', 'veto' => 'VeterinaryCare', 'pharma' => 'Pharmacy'];
        $s = strtolower($sector);
        foreach ($map as $k => $t) {
            if (str_contains($s, $k)) {
                return $t;
            }
        }

        return 'LocalBusiness';
    }
}
