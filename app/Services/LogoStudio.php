<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logo de l'en-tête : réglages d'affichage (site_data.brand), analyse de la tonalité du logo
 * (clair / sombre) pour le poser sur le bon fond, et génération de propositions de logos
 * vectoriels par l'IA (wordmark + pictogramme, SVG propre, couleurs de la marque).
 */
class LogoStudio
{
    public const BG = ['auto', 'none', 'light', 'dark', 'accent'];
    public const SIZES = ['sm', 'md', 'lg'];
    public const STYLES = ['épuré', 'élégant', 'moderne', 'artisanal', 'chaleureux', 'premium', 'ludique'];

    public function __construct(private ImageStore $images) {}

    /** Réglages effectifs de la marque pour le rendu. */
    public static function settings(array $siteData): array
    {
        $b = $siteData['brand'] ?? [];

        $logo = ! empty($b['hide']) ? null : ($b['logo'] ?? ($siteData['business']['logo'] ?? null));
        // Logo détouré automatiquement (fond uni retiré) : utilisé tant que la source n'a pas changé
        if ($logo && ! empty($b['clean']) && ($b['clean_for'] ?? null) === $logo) {
            $logo = $b['clean'];
        }

        return [
            'logo'      => $logo,
            'source'    => $b['logo'] ?? ($siteData['business']['logo'] ?? null),
            'bg'        => in_array($b['bg'] ?? 'auto', self::BG, true) ? ($b['bg'] ?? 'auto') : 'auto',
            'size'      => in_array($b['size'] ?? 'md', self::SIZES, true) ? ($b['size'] ?? 'md') : 'md',
            'invert'    => ! empty($b['invert']),
            // Logo OU nom : avec un logo, le nom n'est affiché que si le client l'a demandé
            'show_name' => array_key_exists('show_name', $b) ? (bool) $b['show_name'] : false,
            'tone'      => $b['tone'] ?? null, // light | dark, calculé
        ];
    }

    /**
     * Retire le fond uni d'un logo bitmap (blanc, noir ou couleur pleine autour du dessin) :
     * remplissage depuis les bords, avec un léger fondu sur le contour. Renvoie l'URL du PNG
     * transparent, ou null si le logo est déjà transparent, vectoriel, ou sur un fond non uni (photo).
     */
    public function removeBackground(?string $url): ?string
    {
        if (! $url || ! preg_match('#/media/([a-z0-9-]+)/([A-Za-z0-9._-]+)$#', $url, $m) || Str::endsWith(strtolower($m[2]), '.svg')) {
            return null;
        }
        $path = "uploads/{$m[1]}/{$m[2]}";
        if (! function_exists('imagecreatefromstring') || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $img = @imagecreatefromstring(Storage::disk('local')->get($path));
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 8 || $h < 8 || $w * $h > 4_000_000) {
            return null;
        }
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        // Bords : couleur dominante et proportion de pixels proches d'elle
        $border = [];
        $step = max(1, (int) floor(max($w, $h) / 200));
        for ($x = 0; $x < $w; $x += $step) {
            $border[] = [$x, 0];
            $border[] = [$x, $h - 1];
        }
        for ($y = 0; $y < $h; $y += $step) {
            $border[] = [0, $y];
            $border[] = [$w - 1, $y];
        }
        $rgba = fn ($x, $y) => imagecolorsforindex($img, imagecolorat($img, $x, $y));
        $cols = array_map(fn ($p) => $rgba($p[0], $p[1]), $border);
        $opaque = array_filter($cols, fn ($c) => $c['alpha'] < 20);
        if (count($opaque) < count($cols) * 0.6) {
            return null; // déjà transparent
        }
        $ref = ['red' => $this->median(array_column($opaque, 'red')), 'green' => $this->median(array_column($opaque, 'green')), 'blue' => $this->median(array_column($opaque, 'blue'))];
        $dist = fn ($c) => sqrt(($c['red'] - $ref['red']) ** 2 + ($c['green'] - $ref['green']) ** 2 + ($c['blue'] - $ref['blue']) ** 2);
        $near = count(array_filter($opaque, fn ($c) => $dist($c) < 40));
        if ($near < count($opaque) * 0.9) {
            return null; // fond non uni (photo, dégradé) : on ne touche pas
        }
        // Remplissage depuis les bords
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopy($out, $img, 0, 0, 0, 0, $w, $h);
        $seen = [];
        $queue = [];
        for ($x = 0; $x < $w; $x++) {
            $queue[] = [$x, 0];
            $queue[] = [$x, $h - 1];
        }
        for ($y = 0; $y < $h; $y++) {
            $queue[] = [0, $y];
            $queue[] = [$w - 1, $y];
        }
        $removed = 0;
        while ($queue) {
            [$x, $y] = array_pop($queue);
            $k = $y * $w + $x;
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $c = $rgba($x, $y);
            $d = $dist($c);
            if ($d >= 70) {
                continue; // le dessin commence ici
            }
            // Cœur du fond : transparent ; zone de contour : semi-transparente (anticrénelage)
            $alpha = $d < 35 ? 127 : (int) round(127 * (70 - $d) / 35);
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $c['red'], $c['green'], $c['blue'], max($alpha, $c['alpha'])));
            $removed++;
            if ($d < 35) {
                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if ($nx >= 0 && $ny >= 0 && $nx < $w && $ny < $h && ! isset($seen[$ny * $w + $nx])) {
                        $queue[] = [$nx, $ny];
                    }
                }
            }
        }
        if ($removed < $w * $h * 0.08 || $removed > $w * $h * 0.97) {
            return null; // presque rien retiré, ou tout retiré : on garde l'original
        }
        ob_start();
        imagepng($out, null, 9);
        $png = (string) ob_get_clean();
        $name = $this->images->storePng($png, $m[1], 'logo-clean');

        return $name ? '/media/'.$m[1].'/'.$name : null;
    }

    private function median(array $v): int
    {
        sort($v);

        return (int) ($v[intdiv(count($v), 2)] ?? 0);
    }

    /**
     * Tonalité d'un logo (clair ou sombre) d'après ses pixels opaques ou ses couleurs SVG.
     * Un logo clair a besoin d'un fond sombre quand la barre devient blanche, et inversement.
     */
    public function tone(?string $url): ?string
    {
        if (! $url || ! preg_match('#/media/([a-z0-9-]+)/([A-Za-z0-9._-]+)$#', $url, $m)) {
            return null;
        }
        $path = "uploads/{$m[1]}/{$m[2]}";
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($path);
        if (Str::endsWith(strtolower($m[2]), '.svg')) {
            // Logo adaptatif : il prend la couleur du texte de la barre (blanc sur le visuel, sombre sur barre blanche)
            if (str_contains($bytes, 'currentColor')) {
                return 'adaptive';
            }
            if ($png = $this->rasterize($bytes)) {
                $bytes = $png;
                $m[2] = 'raster.png';
            }
        }
        if (Str::endsWith(strtolower($m[2]), '.svg')) {
            preg_match_all('/(?:fill|stroke|stop-color)\s*[:=]\s*["\']?\s*#([0-9a-f]{3,8})\b/i', $bytes, $mm);
            $lums = [];
            foreach ($mm[1] as $hex) {
                if (strlen($hex) === 3) {
                    $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                }
                if (strlen($hex) < 6) {
                    continue;
                }
                $lums[] = $this->lum(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
            }
            if (preg_match('/fill\s*[:=]\s*["\']?\s*(white|#fff)/i', $bytes)) {
                $lums[] = 1.0;
            }

            return $lums ? (array_sum($lums) / count($lums) > 0.5 ? 'light' : 'dark') : null;
        }
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $sum = 0;
        $n = 0;
        $step = max(1, (int) floor(max($w, $h) / 64));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                if (($c['alpha'] ?? 0) > 100) {
                    continue; // transparent
                }
                $sum += $this->lum($c['red'], $c['green'], $c['blue']);
                $n++;
            }
        }
        if ($n === 0) {
            return null;
        }

        return $sum / $n > 0.6 ? 'light' : 'dark';
    }

    /**
     * Propositions de logos vectoriels. Retourne des URLs /media/{slug}/logo-….svg (4 max).
     *
     * @return array<int, array{url: string, name: string}>
     */
    public function generate(Site $site, string $style = 'épuré', string $hint = ''): array
    {
        if (! Gemini::enabled()) {
            return [];
        }
        $d = $site->site_data ?? [];
        $b = $d['business'] ?? [];
        $name = trim((string) ($b['name'] ?? $site->name));
        $sector = config('sectors.'.($site->sector ?: 'service'));
        $accent = $d['accent'] ?? ($sector['color'] ?? '#4f46e5');
        $font = $d['font'] ?? ($sector['font'] ?? 'Space Grotesk');
        $theme = $d['theme'] ?? ($sector['theme'] ?? 'light');
        $ink = $theme === 'dark' ? '#ffffff' : '#0f172a';
        $size = mb_strlen($name) > 18 ? 40 : 48; // taille fixe et lisible : la largeur du viewBox est ajustée ensuite
        $styleTxt = in_array($style, self::STYLES, true) ? $style : 'épuré';

        $prompt = <<<TXT
Tu es un designer de logos. Crée 4 propositions de logo horizontal (wordmark + pictogramme) pour « {$name} », {$sector['label']}.
Style demandé : {$styleTxt}. {$hint}

CONTRAINTES SVG (strictes)
- Chaque logo est un SVG complet : <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 120" role="img" aria-label="{$name}"> … </svg>, sans width/height, sans script, sans image, sans texte hors du nom.
- Le nom « {$name} » en <text> : x="140" y="76" font-family="{$font}, Inter, Arial, sans-serif" font-weight="700" font-size="{$size}" fill="currentColor" (tu peux mettre un mot en couleur {$accent} avec un <tspan fill="{$accent}>). IMPORTANT : tout ce qui est « encre » (texte, traits fins) utilise fill="currentColor" ou stroke="currentColor", jamais une couleur fixe : le logo s'adapte ainsi au fond clair comme au fond sombre. Les aplats colorés utilisent {$accent}. Lettres espacées autorisées (letter-spacing ≤ 3). Garde exactement ce font-size : la largeur du logo s'ajuste automatiquement au nom.
- Le pictogramme occupe la zone x 0–120, y 0–120 : formes géométriques simples (≤ 12 éléments : circle, rect rx, path, polygon, line), évocatrices du métier, lisibles à 40 px de haut. Couleurs : {$accent} pour les aplats, currentColor pour l'encre, et au plus une teinte dérivée de {$accent}. Sur une pastille {$accent}, les détails sont en #ffffff. Un <linearGradient> interne est autorisé.
- Variez vraiment les 4 propositions : monogramme en pastille, pictogramme linéaire, forme pleine, pictogramme + soulignement.
- Pas de dégradés complexes, pas de filtres, pas d'ombres, pas de fond pleine largeur (fond transparent).

Réponds UNIQUEMENT en JSON : {"logos":[{"name":"nom court de la proposition","svg":"<svg …>…</svg>"}, … 4 éléments]}
TXT;

        try {
            $res = Gemini::generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.9, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 8000, 'thinkingConfig' => ['thinkingBudget' => 0]],
            ], 90, 'LogoStudio');
            $data = json_decode(Gemini::text($res), true);
        } catch (\Throwable $e) {
            Log::warning('LogoStudio : '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach (array_slice((array) ($data['logos'] ?? []), 0, 4) as $i => $l) {
            $svg = $this->sanitize((string) ($l['svg'] ?? ''), $name);
            if (! $svg) {
                continue;
            }
            $file = $this->images->storeSvg($svg, $site->slug, 'logo-ia');
            if ($file) {
                $out[] = ['url' => route('media.show', [$site->slug, $file]), 'svg' => $svg, 'name' => Str::limit(trim((string) ($l['name'] ?? 'Proposition '.($i + 1))), 40, '')];
            }
        }
        Log::info('LogoStudio '.$site->slug.' : '.count($out).' proposition(s)');

        return $out;
    }

    /** SVG sûr et autonome, ou null s'il est invalide. */
    public function sanitize(string $svg, string $name): ?string
    {
        $svg = trim($svg);
        if (! preg_match('/^<svg\b[^>]*>.*<\/svg>\s*$/is', $svg) || strlen($svg) > 40000) {
            return null;
        }
        if (preg_match('/<(script|foreignObject|image|iframe|use)\b|javascript:|xlink:href\s*=\s*["\']?\s*http|href\s*=\s*["\']?\s*http|@import|url\(\s*["\']?\s*http/i', $svg)) {
            return null;
        }
        $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg);
        if (! str_contains($svg, 'xmlns=')) {
            $svg = preg_replace('/^<svg\b/', '<svg xmlns="http://www.w3.org/2000/svg"', $svg, 1);
        }
        if (! preg_match('/viewBox\s*=/i', $svg)) {
            $svg = preg_replace('/^<svg\b/', '<svg viewBox="0 0 520 120"', $svg, 1);
        }
        // Largeur ajustée au nom : le viewBox s'élargit pour que le texte ne soit jamais coupé
        if (preg_match('/<text\b[^>]*font-size\s*=\s*["\']?([\d.]+)/i', $svg, $fs) && preg_match('/viewBox\s*=\s*["\']\s*([\d.-]+)\s+([\d.-]+)\s+([\d.]+)\s+([\d.]+)\s*["\']/i', $svg, $vb)) {
            preg_match('/<text\b[^>]*\bx\s*=\s*["\']?([\d.]+)/i', $svg, $tx);
            $ls = preg_match('/letter-spacing\s*[:=]\s*["\']?([\d.]+)/i', $svg, $lsm) ? (float) $lsm[1] : 0;
            $text = html_entity_decode(trim(strip_tags(preg_replace('/^.*?(<text\b.*<\/text>).*$/is', '$1', $svg))));
            $need = (float) ($tx[1] ?? 140) + mb_strlen($text) * ((float) $fs[1] * 0.6 + $ls) + 16;
            if ($need > (float) $vb[3]) {
                $svg = preg_replace('/viewBox\s*=\s*["\'][^"\']*["\']/i', 'viewBox="'.$vb[1].' '.$vb[2].' '.(int) ceil($need).' '.$vb[4].'"', $svg, 1);
            }
        }
        $svg = preg_replace('/^<svg\b(?![^>]*overflow=)/', '<svg overflow="visible"', $svg, 1);
        // Pas de dimensions fixes : la taille est pilotée par le gabarit
        $svg = preg_replace('/^(<svg\b[^>]*?)\s(width|height)\s*=\s*("[^"]*"|\'[^\']*\')/i', '$1', $svg, 2);
        // Le nom doit y figurer (sinon ce n'est pas un wordmark exploitable)
        if (! str_contains(mb_strtolower(strip_tags($svg)), mb_strtolower(mb_substr($name, 0, 4)))) {
            return null;
        }

        return $svg;
    }

    /** Contenu d'un SVG local pour l'inliner dans l'en-tête (les polices de la page s'appliquent). */
    public static function inline(?string $url): ?string
    {
        if (! $url || ! preg_match('#/media/([a-z0-9-]+)/([A-Za-z0-9._-]+\.svg)$#i', $url, $m)) {
            return null;
        }
        $path = "uploads/{$m[1]}/{$m[2]}";
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }
        $svg = (string) Storage::disk('local')->get($path);
        $svg = preg_replace('/<\?xml[^>]*>|<!DOCTYPE[^>]*>/i', '', $svg);
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg);

        return strlen($svg) < 60000 && preg_match('/^\s*<svg\b/i', $svg) ? trim($svg) : null;
    }

    /** SVG → PNG 160 px via rsvg-convert (présent dans l'image Docker), null si indisponible. */
    private function rasterize(string $svg): ?string
    {
        $bin = trim((string) @shell_exec('command -v rsvg-convert 2>/dev/null'));
        if ($bin === '') {
            return null;
        }
        $in = tempnam(sys_get_temp_dir(), 'logo').'.svg';
        $out = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($in, $svg);
        @exec(escapeshellcmd($bin).' -w 160 --keep-aspect-ratio '.escapeshellarg($in).' -o '.escapeshellarg($out).' 2>/dev/null');
        $png = is_file($out) && filesize($out) > 100 ? file_get_contents($out) : null;
        @unlink($in);
        @unlink($out);

        return $png ?: null;
    }

    private function lum(int $r, int $g, int $b): float
    {
        $f = fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
    }
}
