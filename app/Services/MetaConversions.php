<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mesure des conversions Meta : chaque événement part deux fois avec le même event_id,
 * côté navigateur (pixel, via la prop partagée « track ») et côté serveur (API de conversions),
 * Meta ne compte qu'une fois. Rien ne part sans consentement marketing (cookie joow_consent=all).
 */
class MetaConversions
{
    public const COOKIE = 'joow_consent';

    public const PRICES = ['pro' => 39.0, 'liberte' => 349.0];

    public static function enabled(): bool
    {
        return (bool) config('services.meta.pixel_id');
    }

    public static function consented(?Request $request = null): bool
    {
        $request ??= request();

        return $request && self::rawCookie($request, self::COOKIE) === 'all';
    }

    /** Cookie lu dans l'en-tête brut : écrit par le navigateur (consentement, pixel), jamais chiffré par Laravel. */
    public static function rawCookie(Request $request, string $name): ?string
    {
        $header = (string) $request->headers->get('cookie', '');
        if ($header !== '' && preg_match('/(?:^|;\s*)'.preg_quote($name, '/').'=([^;]*)/', $header, $m)) {
            return urldecode(trim($m[1]));
        }
        $v = $request->cookie($name);

        return is_string($v) ? $v : null;
    }

    /**
     * Enregistre un événement : il sera envoyé à l'API de conversions (si consentement) et remis
     * au navigateur pour le pixel. Retourne l'event_id.
     */
    public function event(string $name, array $custom = [], ?string $email = null, ?int $userId = null, ?string $eventId = null, ?Request $request = null): string
    {
        $eventId ??= (string) Str::uuid();
        // Hors requête web (jobs, commandes) : pas de navigateur ; en test PHPUnit la requête simulée est bien là
        $request ??= (app()->runningInConsole() && ! app()->runningUnitTests()) ? null : request();

        // Pixel : remis à la page suivante (prop partagée « track ») ou à la réponse JSON
        Tracking::push($name, $eventId, $custom);

        if (! self::enabled() || ! config('services.meta.access_token') || ! self::consented($request)) {
            return $eventId;
        }

        $payload = [
            'event_name'       => $name,
            'event_time'       => time(),
            'event_id'         => $eventId,
            'action_source'    => 'website',
            'event_source_url' => $request?->fullUrl(),
            'user_data'        => array_filter([
                'em'                => $email ? [hash('sha256', strtolower(trim($email)))] : null,
                'external_id'       => $userId ? [hash('sha256', (string) $userId)] : null,
                'client_ip_address' => $request?->ip(),
                'client_user_agent' => $request?->userAgent(),
                'fbp'               => $request ? self::rawCookie($request, '_fbp') : null,
                'fbc'               => $request ? (self::rawCookie($request, '_fbc') ?: ($request->hasSession() ? $request->session()->get('attribution.fbc') : null)) : null,
            ]),
            'custom_data'      => $custom ?: null,
        ];

        $events = [array_filter($payload)];
        if (app()->runningUnitTests() || app()->runningInConsole()) {
            $this->post($events);
        } else {
            dispatch(fn () => $this->post($events))->afterResponse();
        }

        return $eventId;
    }

    /** Envoi brut (utilisé aussi par la commande de test). */
    public function post(array $events, ?string $testCode = null): array
    {
        $pixel = config('services.meta.pixel_id');
        $body = ['data' => json_encode(array_values($events)), 'access_token' => config('services.meta.access_token')];
        if ($testCode ?: config('services.meta.test_event_code')) {
            $body['test_event_code'] = $testCode ?: config('services.meta.test_event_code');
        }
        try {
            $r = Http::timeout(8)->asForm()->post("https://graph.facebook.com/v21.0/{$pixel}/events", $body);
            if (! $r->successful()) {
                Log::warning('Meta CAPI : HTTP '.$r->status().' '.Str::limit($r->body(), 300));
            }

            return $r->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI : '.$e->getMessage());

            return [];
        }
    }
}
