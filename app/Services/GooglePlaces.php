<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Récupération d'une fiche Google Business (API Places legacy).
 * Accepte : un lien Google Maps, un place_id, ou un texte "nom ville".
 */
class GooglePlaces
{
    private string $key;

    public function __construct()
    {
        $this->key = (string) config('services.google_places.key');
    }

    /** Résout une entrée libre en fiche normalisée, ou null si introuvable. */
    public function lookup(string $input): ?array
    {
        $placeId = $this->resolvePlaceId(trim($input));
        return $placeId ? $this->details($placeId) : null;
    }

    /** Autocomplétion : liste d'établissements candidats pour une saisie. */
    public function autocomplete(string $input): array
    {
        if (mb_strlen(trim($input)) < 3 || ! $this->key) {
            return [];
        }

        $res = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $input,
            'language' => 'fr',
            'components' => 'country:fr',
            'types' => 'establishment',
            'key' => $this->key,
        ])->json();

        return collect($res['predictions'] ?? [])->take(6)->map(fn ($p) => [
            'place_id'  => $p['place_id'],
            'main'      => $p['structured_formatting']['main_text'] ?? $p['description'],
            'secondary' => $p['structured_formatting']['secondary_text'] ?? '',
        ])->values()->all();
    }

    private function resolvePlaceId(string $input): ?string
    {
        if (Str::startsWith($input, 'ChIJ') && ! Str::contains($input, ' ')) {
            return $input;
        }

        // Lien Google Maps (court ou long) : suivre la redirection puis parser
        $text = $input;
        if (Str::contains($input, ['http://', 'https://'])) {
            $resolved = $this->followRedirect($input);
            if (preg_match('/place_id=([A-Za-z0-9_\-]+)/', $resolved, $m)) {
                return $m[1];
            }
            if (preg_match('#/maps/place/([^/@]+)#', $resolved, $m)) {
                $text = urldecode(str_replace('+', ' ', $m[1]));
            }
        }

        return $this->findPlaceId($text);
    }

    private function followRedirect(string $url): string
    {
        try {
            $res = Http::withOptions(['allow_redirects' => ['track_redirects' => true]])
                ->timeout(12)->get($url);
            $chain = $res->handlerStats()['redirect_url'] ?? null;
            return $chain ?: ($res->effectiveUri() ? (string) $res->effectiveUri() : $url);
        } catch (\Throwable) {
            return $url;
        }
    }

    private function findPlaceId(string $text): ?string
    {
        $res = Http::timeout(12)->get('https://maps.googleapis.com/maps/api/place/findplacefromtext/json', [
            'input' => $text,
            'inputtype' => 'textquery',
            'fields' => 'place_id',
            'language' => 'fr',
            'key' => $this->key,
        ])->json();

        return $res['candidates'][0]['place_id'] ?? null;
    }

    private function details(string $placeId): ?array
    {
        $res = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'language' => 'fr',
            'fields' => 'place_id,name,formatted_address,formatted_phone_number,international_phone_number,'
                .'rating,user_ratings_total,reviews,photos,types,geometry,url,website,opening_hours',
            'key' => $this->key,
        ])->json();

        $r = $res['result'] ?? null;
        if (! $r) {
            return null;
        }

        $city = null;
        if (preg_match('/\d{5}\s+([^,]+)/', $r['formatted_address'] ?? '', $m)) {
            $city = trim($m[1]);
        }

        return [
            'place_id'      => $r['place_id'] ?? $placeId,
            'name'          => $r['name'] ?? '',
            'address'       => $r['formatted_address'] ?? '',
            'city'          => $city,
            'phone'         => $r['formatted_phone_number'] ?? ($r['international_phone_number'] ?? null),
            'rating'        => $r['rating'] ?? null,
            'reviews_count' => $r['user_ratings_total'] ?? 0,
            'website'       => $r['website'] ?? null,
            'maps_url'      => $r['url'] ?? null,
            'lat'           => $r['geometry']['location']['lat'] ?? null,
            'lng'           => $r['geometry']['location']['lng'] ?? null,
            'types'         => $r['types'] ?? [],
            'opening_hours' => $r['opening_hours']['weekday_text'] ?? [],
            'reviews'       => collect($r['reviews'] ?? [])->map(fn ($rv) => [
                'author' => $rv['author_name'] ?? '',
                'rating' => $rv['rating'] ?? 5,
                'text'   => $rv['text'] ?? '',
            ])->take(6)->values()->all(),
            'photos'        => collect($r['photos'] ?? [])->take(10)->map(
                fn ($p) => 'https://maps.googleapis.com/maps/api/place/photo?maxwidth=1600&photo_reference='
                    .$p['photo_reference'].'&key='.$this->key
            )->all(),
        ];
    }
}
