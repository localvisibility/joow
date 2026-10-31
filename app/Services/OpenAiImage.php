<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Génération d'image publicitaire via OpenAI (dernier modèle GPT Image), prioritaire sur Gemini.
 * Actif seulement si OPENAI_API_KEY est renseignée. Rendu en 2:3 / 1:1 puis recadré au format Meta demandé.
 */
class OpenAiImage
{
    public static ?string $lastError = null;

    public static ?string $lastModel = null;

    public static function enabled(): bool
    {
        return (string) config('services.openai.key') !== '';
    }

    /** @param  string[]  $refs  photos de référence (octets JPEG) */
    public static function image(string $prompt, string $aspect, array $refs = []): ?string
    {
        self::$lastError = null;
        if (! self::enabled()) {
            return null;
        }
        $size = $aspect === '1:1' ? '1024x1024' : '1024x1536';
        // Dernier modèle d'abord ; si le compte n'y a pas encore accès, le précédent
        $models = array_values(array_unique(array_filter([(string) config('services.openai.image_model'), 'gpt-image-2.5', 'gpt-image-2', 'gpt-image-1.5', 'gpt-image-1'])));
        foreach ($models as $model) {
            try {
                $http = Http::withToken((string) config('services.openai.key'))->timeout(170);
                if ($refs) {
                    $req = $http->asMultipart();
                    foreach (array_slice($refs, 0, 3) as $i => $bytes) {
                        $req = $req->attach('image[]', $bytes, "ref$i.jpg", ['Content-Type' => 'image/jpeg']);
                    }
                    $res = $req->post('https://api.openai.com/v1/images/edits', ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'quality' => 'high']);
                } else {
                    $res = $http->post('https://api.openai.com/v1/images/generations', ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'quality' => 'high']);
                }
            } catch (\Throwable $e) {
                self::$lastError = "OpenAI $model : délai dépassé ou réseau";
                Log::warning('OpenAiImage : '.str($e->getMessage())->limit(160));

                return null;
            }
            if ($res->successful()) {
                self::$lastModel = $model;
                break;
            }
            self::$lastError = "OpenAI $model : HTTP ".$res->status().' '.str($res->json('error.message') ?? '')->limit(140);
            Log::warning('OpenAiImage '.self::$lastError);
            $msg = strtolower((string) $res->json('error.message'));
            $modelIssue = in_array($res->status(), [403, 404], true) || ($res->status() === 400 && (str_contains($msg, 'model') || str_contains($msg, 'verif')));
            if (! $modelIssue) {
                return null; // quota, facturation, contenu refusé : inutile d'essayer un autre modèle
            }
        }
        if (! $res->successful()) {
            return null;
        }
        $b64 = $res->json('data.0.b64_json');

        return $b64 ? self::crop(base64_decode($b64), $aspect) : null;
    }

    /** Recadrage centré au ratio exact (4:5 ou 9:16 depuis 2:3). */
    private static function crop(string $bytes, string $aspect): string
    {
        [$rw, $rh] = array_map('intval', explode(':', $aspect)) + [1, 1];
        $img = @imagecreatefromstring($bytes);
        if (! $img || ! $rw || ! $rh) {
            return $bytes;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $tw = $w;
        $th = (int) round($w * $rh / $rw);
        if ($th > $h) {
            $th = $h;
            $tw = (int) round($h * $rw / $rh);
        }
        if ($tw === $w && $th === $h) {
            return $bytes;
        }
        $out = imagecrop($img, ['x' => (int) (($w - $tw) / 2), 'y' => (int) (($h - $th) / 2), 'width' => $tw, 'height' => $th]);
        if (! $out) {
            return $bytes;
        }
        ob_start();
        imagejpeg($out, null, 92);

        return (string) ob_get_clean();
    }
}
