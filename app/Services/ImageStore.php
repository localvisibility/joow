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
        $this->write("uploads/$dir/$name", $this->toJpeg($raw) ?? $raw);

        return $name;
    }

    /** Enregistre un SVG déjà assaini (logo généré) et retourne le nom du fichier. */
    public function storeSvg(string $svg, string $dir, string $prefix = 'logo'): ?string
    {
        if (! preg_match('/^\s*<svg\b/i', $svg)) {
            return null;
        }
        $name = $prefix.'-'.Str::lower(Str::random(8)).'-'.time().'.svg';
        $this->write("uploads/$dir/$name", $svg);

        return $name;
    }

    /** Enregistre des octets d'image (déjà téléchargés) et retourne le nom du fichier, ou null si illisible. */
    public function storeBytes(string $raw, string $dir): ?string
    {
        $jpeg = $this->toJpeg($raw);
        if (! $jpeg) {
            return null;
        }
        $name = Str::lower(Str::random(10)).'-'.time().'.jpg';
        $this->write("uploads/$dir/$name", $jpeg);

        return $name;
    }

    /**
     * Écriture lisible par le serveur web : le worker (root) crée sinon des dossiers privés (0700)
     * que PHP-FPM (www-data) ne peut pas traverser → /media renvoie 404.
     */
    private function write(string $path, string $bytes): void
    {
        $disk = Storage::disk('local');
        $disk->put($path, $bytes);
        try {
            $abs = $disk->path($path);
            @chmod(dirname($abs), 0775);
            @chmod($abs, 0664);
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                foreach ([dirname($abs), $abs] as $p) {
                    @chown($p, 'www-data');
                    @chgrp($p, 'www-data');
                }
            }
        } catch (\Throwable) {
            // disque factice (tests) : pas de chemin physique
        }
    }

    /**
     * Rapatrie des images distantes (site existant) chez Joow : le nouveau site ne dépend plus de l'ancien
     * (hotlink, site coupé après la refonte). Retourne [url d'origine => url Joow] pour celles qui ont abouti.
     */
    public function mirror(array $urls, string $dir, int $max = 14): array
    {
        $urls = array_values(array_unique(array_filter($urls, fn ($u) => is_string($u) && Str::startsWith($u, ['http://', 'https://']))));
        $urls = array_slice($urls, 0, $max);
        if (! $urls) {
            return [];
        }
        $responses = \Illuminate\Support\Facades\Http::pool(fn ($pool) => array_map(
            fn ($u) => $pool->as($u)->timeout(15)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; JoowBot/1.0; +https://joow.fr)', 'Accept' => 'image/*'])->get($u),
            $urls
        ));
        $out = [];
        foreach ($urls as $u) {
            $r = $responses[$u] ?? null;
            if (! $r instanceof \Illuminate\Http\Client\Response || ! $r->successful() || strlen($r->body()) < 300) {
                continue;
            }
            // Logo vectoriel : conservé en SVG (nettoyé de tout script), sans passer par GD
            if ($this->isSvg($r->body(), (string) $r->header('Content-Type'))) {
                $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $r->body());
                $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg);
                $name = Str::lower(Str::random(10)).'-'.time().'.svg';
                $this->write("uploads/$dir/$name", $svg);
                $out[$u] = rtrim(config('app.url'), '/')."/media/$dir/$name";

                continue;
            }
            if (strlen($r->body()) < 1000) {
                continue;
            }
            $info = @getimagesizefromstring($r->body());
            if (! $info || $info[0] < 400 || $info[1] < 250) {
                continue; // trop petit pour un visuel de site
            }
            if ($name = $this->storeBytes($r->body(), $dir)) {
                $out[$u] = rtrim(config('app.url'), '/')."/media/$dir/$name";
            }
        }

        return $out;
    }

    public function isSvg(string $bytes, string $contentType = ''): bool
    {
        return str_contains(strtolower($contentType), 'svg') || preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg\b/is', $bytes) === 1;
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
