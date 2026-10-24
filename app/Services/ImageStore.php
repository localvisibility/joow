<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos téléversées par les propriétaires : redimensionnées (≤ 1600 px), ré-encodées en JPEG,
 * orientation EXIF corrigée, stockées sur le disque local et servies par /media/{slug}/{file}.
 */
class ImageStore
{
    public const MAX_SIDE = 1600;

    /** Enregistre le fichier dans uploads/{dir}/ et retourne le nom du fichier créé. */
    public function store(UploadedFile $file, string $dir): string
    {
        $raw = file_get_contents($file->getRealPath());
        $name = Str::lower(Str::random(10)).'-'.time().'.jpg';
        $jpeg = $this->toJpeg($raw) ?? $raw;
        Storage::disk('local')->put("uploads/$dir/$name", $jpeg);

        return $name;
    }

    /** Déplace les photos d'un dossier temporaire vers le dossier définitif du site. */
    public function move(array $files, string $fromDir, string $toDir): array
    {
        $kept = [];
        foreach ($files as $f) {
            if (! is_string($f) || ! preg_match('/^[A-Za-z0-9._-]+$/', $f)) {
                continue;
            }
            if (Storage::disk('local')->exists("uploads/$fromDir/$f")) {
                Storage::disk('local')->move("uploads/$fromDir/$f", "uploads/$toDir/$f");
                $kept[] = $f;
            } elseif (Storage::disk('local')->exists("uploads/$toDir/$f")) {
                $kept[] = $f;
            }
        }

        return $kept;
    }

    private function toJpeg(string $raw): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring($raw);
        if (! $img) {
            return null;
        }
        // Orientation EXIF (photos de téléphone)
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
            $o = (int) ($exif['Orientation'] ?? 1);
            if (in_array($o, [3, 6, 8], true)) {
                $img = imagerotate($img, [3 => 180, 6 => -90, 8 => 90][$o], 0) ?: $img;
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        if ($scale < 1) {
            $img = imagescale($img, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC) ?: $img;
        }
        // Fond blanc pour les PNG transparents
        $canvas = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        ob_start();
        imagejpeg($canvas, null, 85);

        return ob_get_clean() ?: null;
    }
}
