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

        return [
            'logo'      => ! empty($b['hide']) ? null : ($b['logo'] ?? ($siteData['business']['logo'] ?? null)),
            'bg'        => in_array($b['bg'] ?? 'auto', self::BG, true) ? ($b['bg'] ?? 'auto') : 'auto',
            'size'      => in_array($b['size'] ?? 'md', self::SIZES, true) ? ($b['size'] ?? 'md') : 'md',
            'invert'    => ! empty($b['invert']),
            'show_name' => ! array_key_exists('show_name', $b) || (bool) $b['show_name'],
            'tone'      => $b['tone'] ?? null, // light | dark, calculé
        ];
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
        $size = max(28, min(60, (int) round(1100 / max(8, mb_strlen($name) + 2))));
        $styleTxt = in_array($style, self::STYLES, true) ? $style : 'épuré';

        $prompt = <<<TXT
Tu es un designer de logos. Crée 4 propositions de logo horizontal (wordmark + pictogramme) pour « {$name} », {$sector['label']}.
Style demandé : {$styleTxt}. {$hint}

CONTRAINTES SVG (strictes)
- Chaque logo est un SVG complet : <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 120" role="img" aria-label="{$name}"> … </svg>, sans width/height, sans script, sans image, sans texte hors du nom.
- Le nom « {$name} » en <text> : x="140" y="76" font-family="{$font}, Inter, Arial, sans-serif" font-weight="700" font-size="{$size}" fill="currentColor" (tu peux mettre un mot en couleur {$accent} avec un <tspan fill="{$accent}>). IMPORTANT : tout ce qui est « encre » (texte, traits fins) utilise fill="currentColor" ou stroke="currentColor", jamais une couleur fixe : le logo s'adapte ainsi au fond clair comme au fond sombre. Les aplats colorés utilisent {$accent}. Lettres espacées autorisées (letter-spacing ≤ 4). Le texte doit tenir dans la largeur : réduis font-size si le nom est long.
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
