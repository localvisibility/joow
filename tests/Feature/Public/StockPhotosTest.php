<?php

namespace Tests\Feature\Public;

use App\Services\StockPhotos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockPhotosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_pexels_results_are_normalized_and_used_first(): void
    {
        config(['services.stock.pexels' => 'k', 'services.stock.openverse' => true]);
        Http::fake([
            'api.pexels.com/*' => Http::response(['photos' => [
                ['src' => ['large2x' => 'https://px/1-large.jpg', 'medium' => 'https://px/1-m.jpg'], 'alt' => 'Plumber', 'photographer' => 'Ana'],
                ['src' => ['large2x' => 'https://px/2-large.jpg', 'medium' => 'https://px/2-m.jpg'], 'alt' => 'Pipes', 'photographer' => 'Bo'],
            ]]),
            'api.openverse.org/*' => Http::response(['results' => []]),
        ]);
        $r = app(StockPhotos::class)->search('plumber fixing sink');
        $this->assertCount(2, $r);
        $this->assertSame('https://px/1-large.jpg', $r[0]['url']);
        $this->assertSame('pexels', $r[0]['provider']);
        $this->assertStringContainsString('Ana', $r[0]['credit']);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'openverse'));
    }

    public function test_fill_assigns_distinct_images_and_falls_back_to_sector_stock(): void
    {
        config(['services.stock.pexels' => 'k', 'services.stock.openverse' => false]);
        Http::fake(function ($req) {
            $q = $req->data()['query'] ?? '';
            if (str_contains($q, 'empty')) {
                return Http::response(['photos' => []]);
            }

            return Http::response(['photos' => [
                ['src' => ['large2x' => 'https://px/shared.jpg', 'medium' => 'https://px/shared-m.jpg'], 'alt' => '', 'photographer' => 'A'],
                ['src' => ['large2x' => 'https://px/'.md5($q).'.jpg', 'medium' => 'https://px/m.jpg'], 'alt' => '', 'photographer' => 'B'],
            ]]);
        });
        $out = app(StockPhotos::class)->fill([
            'hero' => ['subject' => 'salle de restaurant', 'pick' => false],
            'service_0' => ['subject' => 'plat du jour', 'pick' => false],
            'service_1' => ['subject' => 'empty subject', 'pick' => false],
        ], 'Restaurant', '', [], ['https://sector/a.jpg', 'https://sector/b.jpg']);

        $this->assertSame('https://px/shared.jpg', $out['hero']['url']);
        $this->assertNotSame('https://px/shared.jpg', $out['service_0']['url']); // jamais deux fois la même image
        $this->assertSame('https://sector/a.jpg', $out['service_1']['url']);   // aucun résultat → visuel du métier
        $this->assertSame('sector', $out['service_1']['provider']);
    }

    public function test_openverse_filters_small_images(): void
    {
        config(['services.stock.pexels' => null, 'services.stock.pixabay' => null, 'services.stock.unsplash' => null, 'services.stock.openverse' => true]);
        Http::fake(['api.openverse.org/*' => Http::response(['results' => [
            ['url' => 'https://ov/small.jpg', 'thumbnail' => 'https://ov/s-t.jpg', 'width' => 600, 'height' => 400, 'title' => 'x', 'creator' => 'c', 'license' => 'by'],
            ['url' => 'https://ov/big.jpg', 'thumbnail' => 'https://ov/b-t.jpg', 'width' => 2000, 'height' => 1300, 'title' => 'y', 'creator' => 'd', 'license' => 'by'],
        ]])]);
        $r = app(StockPhotos::class)->search('bakery bread oven');
        $this->assertCount(1, $r);
        $this->assertSame('https://ov/big.jpg', $r[0]['url']);
    }
}
