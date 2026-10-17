<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Récupération d'une fiche Google Business via Places API (New).
 * Accepte : un lien Google Maps, un place_id, ou un texte "nom ville".
 *
 * L'ancienne API Places (« legacy ») n'est plus activable sur les nouveaux projets
 * Google Cloud ; tout passe désormais par places.googleapis.com/v1.
 */
class GooglePlaces
{
    private const BASE = 'https://places.googleapis.com/v1';

    private string $key;

    public function __construct()
    {
        $this->key = (string) config('services.google_places.key');
    }

    /** Résout une entrée libre en fiche normalisée, ou null si introuvable. */
    public function lookup(string $input): ?array
    {
        if (! $this->key) {
            Log::warning('Places : GOOGLE_PLACES_API_KEY absente, recherche désactivée.');

            return null;
        }

        $placeId = $this->resolvePlaceId(trim($input));

        return $placeId ? $this->details($placeId) : null;
    }

    /** Autocomplétion : liste d'établissements candidats pour une saisie. */
    public function autocomplete(string $input): array
    {
        if (mb_strlen(trim($input)) < 3) {
            return [];
        }
        if (! $this->key) {
            Log::warning('Places : GOOGLE_PLACES_API_KEY absente, recherche désactivée.');

            return [];
        }

        $res = $this->client()->post(self::BASE.'/places:autocomplete', [
            'input'               => $input,
            'languageCode'        => 'fr',
            'regionCode'          => 'FR',
            'includedRegionCodes' => ['fr'],
        ]);
        $this->logFailure('autocomplete', $res);

        return collect($res->json('suggestions') ?? [])
            ->pluck('placePrediction')
            ->filter(fn ($p) => ! empty($p['placeId']))
            ->take(6)
            ->map(fn ($p) => [
                'place_id'  => $p['placeId'],
                'main'      => $p['structuredFormat']['mainText']['text'] ?? ($p['text']['text'] ?? ''),
                'secondary' => $p['structuredFormat']['secondaryText']['text'] ?? '',
            ])->values()->all();
    }

    private function client(string $fieldMask = ''): PendingRequest
    {
        $req = Http::timeout(15)->withHeaders(['X-Goog-Api-Key' => $this->key]);

        return $fieldMask ? $req->withHeaders(['X-Goog-FieldMask' => $fieldMask]) : $req;
    }

    /** Places API (New) renvoie les erreurs en HTTP 4xx avec un objet error : on les journalise. */
    private function logFailure(string $op, Response $res): void
    {
        if ($res->failed()) {
            Log::warning("Places $op : HTTP {$res->status()} ".($res->json('error.status') ?? '').' — '.($res->json('error.message') ?? $res->body()));
        }
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
        $res = $this->client('places.id')->post(self::BASE.'/places:searchText', [
            'textQuery'    => $text,
            'languageCode' => 'fr',
            'regionCode'   => 'FR',
            'pageSize'     => 1,
        ]);
        $this->logFailure('searchText', $res);

        return $res->json('places.0.id');
    }

    private function details(string $placeId): ?array
    {
        $res = $this->client(implode(',', [
            'id', 'displayName', 'formattedAddress', 'addressComponents', 'nationalPhoneNumber', 'internationalPhoneNumber',
            'rating', 'userRatingCount', 'reviews', 'photos', 'types', 'location', 'googleMapsUri', 'websiteUri',
            'regularOpeningHours',
        ]))->get(self::BASE.'/places/'.rawurlencode($placeId), ['languageCode' => 'fr']);
        $this->logFailure('details', $res);

        $r = $res->successful() ? $res->json() : null;
        if (! $r || empty($r['id'])) {
            return null;
        }

        $city = collect($r['addressComponents'] ?? [])
            ->first(fn ($c) => in_array('locality', $c['types'] ?? [], true))['longText'] ?? null;
        if (! $city && preg_match('/\d{5}\s+([^,]+)/', $r['formattedAddress'] ?? '', $m)) {
            $city = trim($m[1]);
        }

        return [
            'place_id'      => $r['id'],
            'name'          => $r['displayName']['text'] ?? '',
            'address'       => $r['formattedAddress'] ?? '',
            'city'          => $city,
            'phone'         => $r['nationalPhoneNumber'] ?? ($r['internationalPhoneNumber'] ?? null),
            'rating'        => $r['rating'] ?? null,
            'reviews_count' => $r['userRatingCount'] ?? 0,
            'website'       => $r['websiteUri'] ?? null,
            'maps_url'      => $r['googleMapsUri'] ?? null,
            'lat'           => $r['location']['latitude'] ?? null,
            'lng'           => $r['location']['longitude'] ?? null,
            'types'         => $r['types'] ?? [],
            'opening_hours' => $r['regularOpeningHours']['weekdayDescriptions'] ?? [],
            'reviews'       => collect($r['reviews'] ?? [])->map(fn ($rv) => [
                'author' => $rv['authorAttribution']['displayName'] ?? '',
                'rating' => $rv['rating'] ?? 5,
                'text'   => $rv['text']['text'] ?? ($rv['originalText']['text'] ?? ''),
            ])->filter(fn ($rv) => $rv['text'] !== '')->take(6)->values()->all(),
            // Photos triées pour la première impression : paysage et haute résolution d'abord,
            // les visuels trop petits (< 800 px) sont écartés.
            'photos'        => collect($r['photos'] ?? [])
                ->filter(fn ($p) => (int) ($p['widthPx'] ?? 0) >= 800)
                ->sortByDesc(fn ($p) => (($p['widthPx'] ?? 0) >= ($p['heightPx'] ?? 1) ? 1_000_000_000 : 0) + (int) ($p['widthPx'] ?? 0) * (int) ($p['heightPx'] ?? 0))
                ->take(10)->values()
                ->map(fn ($p) => self::BASE.'/'.$p['name'].'/media?maxWidthPx=1600&key='.$this->key)
                ->all(),
        ];
    }
}
