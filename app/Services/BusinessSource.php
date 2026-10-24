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
    public function __construct(private GooglePlaces $places, private SiteImporter $importer, private SectorDetector $detector) {}

    /** @param  callable|null  $progress  fn(array $more) : publie l'avancement sur la page d'attente */
    public function resolve(Site $site, ?callable $progress = null): array
    {
        $d = $site->site_data ?? [];
        $intake = is_array($d['intake'] ?? null) ? $d['intake'] : [];
        $import = is_array($d['import'] ?? null) ? $d['import'] : [];

        // Refonte : lecture du site existant (une seule fois), puis recherche de la fiche Google correspondante
        if ($import && empty($import['pages']) && ! empty($import['url'])) {
            $progress && $progress(['step' => 'lookup', 'source' => 'refonte', 'name' => $site->name]);
            $import = array_merge($import, $this->importer->crawl($import['url']));
            if (! $site->place_id && ! empty($import['name'])) {
                $g = $this->findGoogle($import['name'], $import['city'] ?? ($import['address'] ?? ''));
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
        $b['source'] = $import ? 'refonte' : ($site->place_id ? 'google' : 'questionnaire');

        return $this->normalize($b);
    }

    /** Fiche Google correspondant à « nom + ville », acceptée seulement si le nom concorde. */
    private function findGoogle(string $name, string $place): ?array
    {
        try {
            $g = $this->places->lookup(trim("$name $place"));
        } catch (\Throwable) {
            return null;
        }
        if (! $g) {
            return null;
        }
        $norm = fn ($s) => preg_replace('/[^a-z0-9 ]+/', ' ', Str::lower(Str::ascii((string) $s)));
        $words = array_filter(explode(' ', $norm($name)), fn ($w) => strlen($w) > 2);
        $hay = $norm($g['name']);
        $hits = count(array_filter($words, fn ($w) => str_contains($hay, $w)));

        return $words && $hits >= ceil(count($words) / 2) ? $g : null;
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
