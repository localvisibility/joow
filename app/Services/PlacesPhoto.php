<?php

namespace App\Services;

/**
 * Photos Google Places sans jamais exposer la clé API : toute adresse
 * « places.googleapis.com/…/media?…&key=… » est remplacée par une adresse Joow signée
 * (/gphoto/<jeton>) ; le serveur va chercher la photo une seule fois puis la garde.
 */
class PlacesPhoto
{
    public const PATTERN = '#https://places\.googleapis\.com/v1/(places/[A-Za-z0-9_\-]+/photos/[A-Za-z0-9_\-]+)/media\?maxWidthPx=(\d+)&(?:amp;)?key=[A-Za-z0-9_\-]+#';

    public static function token(string $name, int $w): string
    {
        $payload = rtrim(strtr(base64_encode($name.'|'.$w), '+/', '-_'), '=');

        return $payload.'.'.substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 20);
    }

    /** @return array{0:string,1:int}|null */
    public static function decode(string $token): ?array
    {
        [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if (! hash_equals(substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 20), $sig)) {
            return null;
        }
        $raw = base64_decode(strtr($payload, '-_', '+/'), true);
        if (! $raw || ! preg_match('#^(places/[A-Za-z0-9_\-]+/photos/[A-Za-z0-9_\-]+)\|(\d{2,4})$#', $raw, $m)) {
            return null;
        }

        return [$m[1], min(1600, max(100, (int) $m[2]))];
    }

    public static function url(string $name, int $w): string
    {
        return rtrim((string) config('app.url'), '/').'/gphoto/'.self::token($name, $w);
    }

    /** Remplace toutes les adresses Google contenant la clé, dans une chaîne. */
    public static function scrubString(string $s): string
    {
        if (! str_contains($s, 'places.googleapis.com')) {
            return $s;
        }

        return preg_replace_callback(self::PATTERN, fn ($m) => self::url($m[1], (int) $m[2]), $s) ?? $s;
    }

    /** Idem dans une structure (site_data, résultat de génération…). */
    public static function scrub(mixed $data): mixed
    {
        if (is_string($data)) {
            return self::scrubString($data);
        }
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::scrub($v);
            }
        }

        return $data;
    }
}
