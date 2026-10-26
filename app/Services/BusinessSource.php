<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Construit la fiche d'entreprise normalisée qui alimente la génération, quelle que soit la source :
 *  - fiche Google (place_id) : lookup Places, enrichi des infos éventuellement saisies par le propriétaire ;
 *  - questionnaire (site_data.intake) : réponses du propriétaire, photos téléversées ou visuels du métier ;
 *  - refonte (site_data.import) : contenu extrait du site existant, complété par la fiche Google si trouvée.
 *
 * Clés garanties : place_id, name, address, city, phone, email, rating, reviews_count, website, maps_url,
 * lat, lng, types, primary_type, opening_hours, reviews, photos, source, owner (indices du propriétaire).
 */
class BusinessSource
{
    public function __construct(private GooglePlaces $places, private SiteImporter $importer, private SectorDetector $detector, private ImageStore $images) {}

    /** @param  callable|null  $progress  fn(array $more) : publie l'avancement sur la page d'attente */
    public function resolve(Site $site, ?callable $progress = null): array
    {
        $d = $site->site_data ?? [];
        $intake = is_array($d['intake'] ?? null) ? $d['intake'] : [];
        $import = is_array($d['import'] ?? null) ? $d['import'] : [];

        // Refonte : lecture du site existant (une seule fois), puis recherche de la fiche Google correspondante
        if ($import && empty($import['pages']) && ! empty($import['url'])) {
            $progress && $progress(['step' => 'lookup', 'source' => 'refonte', 'name' => $site->name]);
            $import = $this->readSite($import, $site);
            if (! $site->place_id && ! empty($import['name'])) {
                $g = $this->findGoogle($import['name'], $import['city'] ?? ($import['address'] ?? ''), $import['phone'] ?? null);
                if ($g) {
                    $site->place_id = $g['place_id'];
                    $import['google_place_id'] = $g['place_id'];
                }
            }
            $site->site_data = array_merge($site->site_data ?? [], ['import' => $import]);
            $site->save();
        }

        $b = null;
        if ($site->place_id) {
            $b = $this->places->lookup($site->place_id);
            if (! $b) {
                throw new \RuntimeException('Fiche Google introuvable pour place_id '.$site->place_id);
            }

            // Combo : la fiche Google indique un site → on le lit aussi (textes, pages, photos, logo)
            if (! $import && ! empty($b['website']) && $this->crawlable($b['website']) && empty($d['import_skipped'])) {
                // Sans les paramètres de suivi ajoutés par Google (utm_…)
                $siteUrl = preg_replace('/[?#].*$/', '', $b['website']);
                $b['website'] = $siteUrl;
                $progress && $progress(['step' => 'lookup', 'source' => 'google', 'name' => $b['name'], 'city' => $b['city'] ?? null, 'website' => preg_replace('/^www\./', '', (string) parse_url($siteUrl, PHP_URL_HOST))]);
                try {
                    $import = $this->readSite(['url' => $siteUrl, 'host' => parse_url($siteUrl, PHP_URL_HOST), 'auto' => true], $site);
                    $progress && $progress(['site_pages' => count($import['pages'] ?? []), 'site_images' => count($import['images'] ?? [])]);
                    $site->site_data = array_merge($site->site_data ?? [], ['import' => $import]);
                    $site->save();
                } catch (\Throwable $e) {
                    // Site illisible : la fiche Google suffit
                    \Illuminate\Support\Facades\Log::info('Combo Google + site : '.$e->getMessage());
                    $import = [];
                    $site->site_data = array_merge($site->site_data ?? [], ['import_skipped' => true]);
                    $site->save();
                }
            }
        }

        // Refonte : métier détecté à partir du contenu du site (et des types Google si trouvés)
        if ($import && (! $site->sector || $site->sector === 'service')) {
            $excerpt = Str::limit(implode(' ', array_map(fn ($p) => $p['text'] ?? '', array_slice((array) ($import['pages'] ?? []), 0, 2))), 1500, '');
            $sector = $this->detector->detect($b['name'] ?? ($import['name'] ?? $site->name), $b['types'] ?? [], ['primary_type' => $b['primary_type'] ?? null, 'address' => $b['address'] ?? ($import['address'] ?? null), 'description' => ($import['description'] ?? '').' '.$excerpt, 'reviews' => $b['reviews'] ?? []]);
            if ($sector && $sector !== $site->sector) {
                $site->update(['sector' => $sector]);
            }
        }

        if ($import) {
            $b = $this->mergeImport($b, $import, $site);
        }
        if (! $b) {
            $b = $this->fromIntake($intake, $site);
        }

        // Indices du propriétaire (questionnaire ou champs manquants d'une refonte) : toujours transmis au rédacteur
        $b['owner'] = $this->ownerHints($intake);
        if (! empty($intake['phone']) && empty($b['phone'])) {
            $b['phone'] = $intake['phone'];
        }
        if (! empty($intake['email'])) {
            $b['email'] = $intake['email'];
        }
        if (empty($b['email']) && $site->owner_email) {
            $b['email'] = $site->owner_email;
        }
        // Photos téléversées : prioritaires sur celles de Google / du site existant
        $uploaded = $this->uploadedPhotos($intake, $site);
        if ($uploaded) {
            $b['photos'] = array_values(array_unique(array_merge($uploaded, $b['photos'] ?? [])));
        }
        // Origine : refonte (on est parti du site), google (fiche, éventuellement enrichie du site), questionnaire
        $b['source'] = $site->source === 'refonte' ? 'refonte' : ($site->place_id ? 'google' : 'questionnaire');
        $b['combo'] = (bool) ($site->place_id && $import);

        return $this->normalize($b);
    }

    /** Lecture du site + rapatriement des images chez Joow. */
    private function readSite(array $import, Site $site): array
    {
        $import = array_merge($import, $this->importer->crawl($import['url']));
        // Les images de l'ancien site sont rapatriées chez Joow (le nouveau site ne dépend plus de l'ancien)
        // Le logo d'abord (il ne doit jamais être coupé par la limite), puis les images
        $mirrored = $this->images->mirror(array_merge(array_filter([$import['logo'] ?? null]), (array) ($import['images'] ?? [])), $site->slug, 16);
        $import['images_original'] = $import['images'] ?? [];
        $import['images'] = array_values(array_filter(array_map(fn ($u) => $mirrored[$u] ?? null, (array) ($import['images'] ?? []))));
        if (! empty($import['logo']) && isset($mirrored[$import['logo']])) {
            $import['logo'] = $mirrored[$import['logo']];
        }

        return $import;
    }

    /** Un « site » de fiche Google qui vaut la peine d'être lu (pas un réseau social ni un annuaire). */
    private function crawlable(string $url): bool
    {
        $host = Str::lower((string) parse_url(Str::startsWith($url, 'http') ? $url : 'https://'.$url, PHP_URL_HOST));
        if ($host === '' || ! Str::contains($host, '.')) {
            return false;
        }
        foreach (['facebook.', 'instagram.', 'linktr.ee', 'pagesjaunes.', 'tripadvisor.', 'thefork.', 'lafourchette.', 'doctolib.', 'booking.com', 'airbnb.', 'ubereats.', 'deliveroo.', 'google.', 'youtube.', 'tiktok.', 'leboncoin.', 'planity.', 'treatwell.', 'resalib.', 'linkedin.', 'joow.fr', 'wa.me', 'whatsapp.'] as $bad) {
            if (Str::contains($host, $bad)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fiche Google correspondant au site : « nom + ville », puis nom seul. Acceptée si le nom concorde
     * ou si le téléphone est le même (le nom commercial diffère souvent de la raison sociale).
     */
    private function findGoogle(string $name, string $place, ?string $phone = null): ?array
    {
        $digits = fn ($s) => preg_replace('/\D+/', '', (string) $s);
        $norm = fn ($s) => preg_replace('/[^a-z0-9 ]+/', ' ', Str::lower(Str::ascii((string) $s)));
        $words = array_values(array_filter(explode(' ', $norm($name)), fn ($w) => strlen($w) > 2));

        foreach (array_unique(array_filter([trim("$name $place"), trim($name)])) as $q) {
            try {
                $g = $this->places->lookup($q);
            } catch (\Throwable) {
                continue;
            }
            if (! $g) {
                continue;
            }
            $hay = $norm($g['name']);
            $hits = count(array_filter($words, fn ($w) => str_contains($hay, $w)));
            $nameOk = $words && $hits >= ceil(count($words) / 2);
            $phoneOk = $phone && ! empty($g['phone']) && substr($digits($phone), -9) === substr($digits($g['phone']), -9);
            if ($nameOk || $phoneOk) {
                return $g;
            }
        }

        return null;
    }

    /** Fiche construite à partir des réponses du questionnaire. */
    private function fromIntake(array $in, Site $site): array
    {
        $address = trim((string) ($in['address'] ?? ''));
        $city = trim((string) ($in['city'] ?? $site->city ?? ''));
        if ($address !== '' && $city !== '' && ! Str::contains(Str::lower($address), Str::lower($city))) {
            $address .= ', '.$city;
        }

        return [
            'place_id'      => null,
            'name'          => $in['name'] ?? $site->name,
            'address'       => $address !== '' ? $address : $city,
            'city'          => $city ?: null,
            'phone'         => $in['phone'] ?? $site->phone,
            'email'         => $in['email'] ?? $site->owner_email,
            'rating'        => null,
            'reviews_count' => 0,
            'website'       => null,
            'maps_url'      => null,
            'lat'           => null,
            'lng'           => null,
            'types'         => [],
            'primary_type'  => $in['sector_hint'] ?? null,
            'opening_hours' => $this->hoursFromText((string) ($in['hours'] ?? '')),
            'reviews'       => [],
            'photos'        => [],
        ];
    }

    /** Refonte : les faits extraits du site existant, Google (si trouvé) en renfort pour avis, note, photos. */
    private function mergeImport(?array $google, array $import, Site $site): array
    {
        $base = $google ?? [
            'place_id' => null, 'rating' => null, 'reviews_count' => 0, 'maps_url' => null, 'lat' => null, 'lng' => null,
            'types' => [], 'primary_type' => null, 'opening_hours' => [], 'reviews' => [], 'photos' => [],
        ];

        $base['name'] = $google['name'] ?? ($import['name'] ?? $site->name);
        $base['address'] = $google['address'] ?? ($import['address'] ?? ($import['city'] ?? ''));
        $base['city'] = $google['city'] ?? ($import['city'] ?? $site->city);
        $base['phone'] = $google['phone'] ?? ($import['phone'] ?? null);
        $base['email'] = $import['email'] ?? null;
        $base['website'] = $import['url'] ?? ($google['website'] ?? null);
        if (empty($base['opening_hours']) && ! empty($import['hours'])) {
            $base['opening_hours'] = (array) $import['hours'];
        }
        // Photos : celles du site existant d'abord (ce sont les leurs), Google ensuite
        $base['photos'] = array_values(array_unique(array_merge(array_values((array) ($import['images'] ?? [])), $google['photos'] ?? [])));
        $base['logo'] = $import['logo'] ?? null;
        $base['existing'] = [
            'url'   => $import['url'] ?? null,
            'pages' => array_slice(array_values((array) ($import['pages'] ?? [])), 0, 12),
        ];

        return $base;
    }

    private function ownerHints(array $in): array
    {
        $list = fn ($v) => array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) $v), fn ($s) => $s !== ''));

        return array_filter([
            'sector_hint' => trim((string) ($in['sector_hint'] ?? '')) ?: null,
            'services'    => $list($in['services'] ?? []),
            'strengths'   => $list($in['strengths'] ?? []),
            'description' => trim((string) ($in['description'] ?? '')) ?: null,
            'since'       => $in['since'] ?? null,
            'zone'        => trim((string) ($in['zone'] ?? '')) ?: null,
            'hours'       => trim((string) ($in['hours'] ?? '')) ?: null,
        ]);
    }

    /** URLs publiques des photos téléversées (servies par /media/{slug}/{file}). */
    private function uploadedPhotos(array $in, Site $site): array
    {
        $files = array_values(array_filter((array) ($in['photos'] ?? []), fn ($f) => is_string($f) && preg_match('/^[A-Za-z0-9._-]+$/', $f)));

        return array_map(fn ($f) => rtrim(config('app.url'), '/')."/media/{$site->slug}/{$f}", $files);
    }

    /** « lun-ven 9h-19h, sam 9h-12h » → liste de lignes lisibles pour le template et le rédacteur. */
    private function hoursFromText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[;\n]+|,\s*(?=[a-zA-Zéû])/u', $text) ?: [])));
    }

    private function normalize(array $b): array
    {
        $b += [
            'place_id' => null, 'name' => '', 'address' => '', 'city' => null, 'phone' => null, 'email' => null,
            'rating' => null, 'reviews_count' => 0, 'website' => null, 'maps_url' => null, 'lat' => null, 'lng' => null,
            'types' => [], 'primary_type' => null, 'opening_hours' => [], 'reviews' => [], 'photos' => [], 'owner' => [], 'source' => 'google',
        ];
        $b['photos'] = array_values(array_filter($b['photos'], fn ($u) => is_string($u) && Str::startsWith($u, ['http://', 'https://'])));

        return $b;
    }
}
