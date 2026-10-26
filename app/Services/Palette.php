<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Couleur d'accent extraite des visuels de l'établissement (logo d'abord, puis photo principale) :
 * le site prend immédiatement les couleurs de la marque au lieu de la teinte générique du métier.
 * Les teintes trop grises, trop claires ou trop sombres sont écartées ; la couleur retenue est
 * ajustée pour rester lisible avec du texte blanc.
 */
class Palette
{
    /** @param  array<int, string|null>  $sources  URLs (ou chemins /media) par ordre de priorité */
    public function fromSources(array $sources): ?string
    {
        foreach (array_filter($sources) as $src) {
            $bytes = $this->bytes($src);
            if ($bytes && ($hex = $this->fromBytes($bytes))) {
                return $hex;
            }
        }

        return null;
    }

    public function fromBytes(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $small = imagescale($img, 64, max(1, (int) round(64 * $h / max(1, $w))));
        if (! $small) {
            return null;
        }
        $sw = imagesx($small);
        $sh = imagesy($small);

        // Histogramme des teintes « vives » (saturation et luminosité exploitables), par paliers de 12°
        $buckets = [];
        $total = 0;
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 255;
                $g = ($rgb >> 8) & 255;
                $b = $rgb & 255;
                [$hue, $s, $l] = $this->hsl($r, $g, $b);
                $total++;
                if ($s < 0.28 || $l < 0.14 || $l > 0.82) {
                    continue; // gris, noir, blanc : pas une couleur de marque
                }
                $k = (int) floor($hue / 12);
                $weight = $s * (1 - abs($l - 0.45)); // saturé et ni trop clair ni trop sombre
                $buckets[$k] ??= ['w' => 0, 'n' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                $buckets[$k]['w'] += $weight;
                $buckets[$k]['n']++;
                $buckets[$k]['r'] += $r * $weight;
                $buckets[$k]['g'] += $g * $weight;
                $buckets[$k]['b'] += $b * $weight;
            }
        }
        if (! $buckets || $total === 0) {
            return null;
        }
        uasort($buckets, fn ($a, $b) => $b['w'] <=> $a['w']);
        $best = reset($buckets);
        // Une couleur doit occuper une part crédible de l'image (sinon c'est un détail, pas une identité)
        if ($best['n'] / $total < 0.035) {
            return null;
        }
        $r = (int) round($best['r'] / $best['w']);
        $g = (int) round($best['g'] / $best['w']);
        $b = (int) round($best['b'] / $best['w']);

        return $this->readable($r, $g, $b);
    }

    /** Assombrit si besoin pour garantir un contraste suffisant avec du texte blanc (≈ 4.5:1). */
    private function readable(int $r, int $g, int $b): string
    {
        [$h, $s, $l] = $this->hsl($r, $g, $b);
        $s = max(0.45, min(0.9, $s));
        $l = min($l, 0.42);
        // Boucle de contraste : on descend la luminosité jusqu'à 4.5:1
        for ($i = 0; $i < 12; $i++) {
            [$rr, $gg, $bb] = $this->rgb($h, $s, $l);
            if ($this->contrastWithWhite($rr, $gg, $bb) >= 4.5) {
                break;
            }
            $l -= 0.03;
        }
        [$rr, $gg, $bb] = $this->rgb($h, $s, max(0.12, $l));

        return sprintf('#%02x%02x%02x', $rr, $gg, $bb);
    }

    private function contrastWithWhite(int $r, int $g, int $b): float
    {
        $lin = fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $lum = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return 1.05 / ($lum + 0.05);
    }

    /** @return array{0: float, 1: float, 2: float} teinte 0-360, saturation 0-1, luminosité 0-1 */
    private function hsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s, $l];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return [(int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255)];
    }

    private function bytes(string $src): ?string
    {
        if (preg_match('#/media/([a-z0-9-]+)/([A-Za-z0-9._-]+)$#', $src, $m) && Storage::disk('local')->exists("uploads/{$m[1]}/{$m[2]}")) {
            return Storage::disk('local')->get("uploads/{$m[1]}/{$m[2]}");
        }
        try {
            $small = preg_replace('/([?&])w=\d+/', '$1w=480', preg_replace('/maxWidthPx=\d+/', 'maxWidthPx=480', $src));
            $r = Http::timeout(10)->get($small);

            return $r->successful() && strlen($r->body()) > 1000 ? $r->body() : null;
        } catch (\Throwable $e) {
            Log::info('Palette : '.$e->getMessage());

            return null;
        }
    }
}
