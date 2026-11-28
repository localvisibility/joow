<?php

namespace Tests\Feature\Public;

use App\Services\LogoStudio;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogoCleanTest extends TestCase
{
    private function png(callable $draw, int $w = 200, int $h = 80): string
    {
        $img = imagecreatetruecolor($w, $h);
        $draw($img, $w, $h);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    public function test_flat_background_is_removed_and_drawing_kept(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/clos/logo.png', $this->png(function ($img, $w, $h) {
            imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 255, 255, 255));
            imagefilledrectangle($img, 60, 20, 140, 60, imagecolorallocate($img, 20, 80, 40));
        }));
        $url = app(LogoStudio::class)->removeBackground('/media/clos/logo.png');
        $this->assertNotNull($url);
        preg_match('#/media/clos/(.+)$#', $url, $m);
        $out = imagecreatefromstring(Storage::disk('local')->get('uploads/clos/'.$m[1]));
        $this->assertSame(127, imagecolorsforindex($out, imagecolorat($out, 2, 2))['alpha']);
        $this->assertSame(0, imagecolorsforindex($out, imagecolorat($out, 100, 40))['alpha']);
    }

    public function test_photo_like_or_transparent_logos_are_left_alone(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/clos/photo.png', $this->png(function ($img, $w, $h) {
            for ($x = 0; $x < $w; $x++) {
                imageline($img, $x, 0, $x, $h, imagecolorallocate($img, $x % 256, (255 - $x) % 256, 120));
            }
        }));
        $this->assertNull(app(LogoStudio::class)->removeBackground('/media/clos/photo.png'));
        $this->assertNull(app(LogoStudio::class)->removeBackground('/media/clos/logo.svg'));
    }

    public function test_logo_only_by_default_and_cleaned_logo_used(): void
    {
        $s = LogoStudio::settings(['business' => ['logo' => '/media/a/l.png'], 'brand' => ['clean' => '/media/a/logo-clean-x.png', 'clean_for' => '/media/a/l.png']]);
        $this->assertFalse($s['show_name']);
        $this->assertSame('/media/a/logo-clean-x.png', $s['logo']);
        $this->assertTrue(LogoStudio::settings(['brand' => ['logo' => '/media/a/l.png', 'show_name' => true]])['show_name']);
    }
}
