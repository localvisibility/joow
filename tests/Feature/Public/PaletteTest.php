<?php

namespace Tests\Feature\Public;

use App\Services\Palette;
use Tests\TestCase;

class PaletteTest extends TestCase
{
    private function png(callable $fill): string
    {
        $img = imagecreatetruecolor(120, 80);
        $fill($img);
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }

    public function test_extracts_the_dominant_brand_colour_and_keeps_it_readable(): void
    {
        $bytes = $this->png(function ($img) {
            imagefill($img, 0, 0, imagecolorallocate($img, 240, 240, 240));           // fond clair (ignoré)
            imagefilledrectangle($img, 0, 0, 60, 80, imagecolorallocate($img, 230, 90, 40)); // orange de marque
        });
        $hex = app(Palette::class)->fromBytes($bytes);
        $this->assertNotNull($hex);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $this->assertGreaterThan($b, $r, 'teinte orange conservée');
        $this->assertGreaterThan($b, $g);
        // Lisible avec du texte blanc : luminance relative limitée
        $lin = fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $this->assertLessThan(0.19, 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b));
    }

    public function test_svg_logo_colours_are_read_from_fills(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect fill="#ffffff" width="10" height="10"/><path fill="#c8102e" d="M0 0h5v5H0z"/><path fill="#C8102E" d="M5 5h5v5H5z"/><text fill="#222">x</text></svg>';
        $hex = app(Palette::class)->fromBytes($svg);
        $this->assertNotNull($hex);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $this->assertGreaterThan($g + 60, $r, 'rouge dominant conservé');
    }

    public function test_grey_images_yield_no_colour(): void
    {
        $bytes = $this->png(fn ($img) => imagefill($img, 0, 0, imagecolorallocate($img, 128, 128, 128)));
        $this->assertNull(app(Palette::class)->fromBytes($bytes));
    }
}
