<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Services\PlacesPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleKeyLeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_api_key_is_never_stored_nor_served_and_photos_go_through_proxy(): void
    {
        Storage::fake('local');
        config(['services.google_places.key' => 'AIzaSECRETKEY123']);
        $g = 'https://places.googleapis.com/v1/places/ChIJabc/photos/AbC_d-1/media?maxWidthPx=1600&key=AIzaSECRETKEY123';
        $site = Site::create(['slug' => 'demo-key', 'name' => 'Démo', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public',
            'site_data' => ['business' => ['name' => 'Démo', 'photos' => [$g]], 'images' => ['hero' => $g], 'content' => ['hero_title' => 'x']]]);

        $this->assertStringNotContainsString('AIzaSECRETKEY123', json_encode($site->fresh()->site_data));
        $url = $site->fresh()->site_data['images']['hero'];
        $this->assertStringContainsString('/gphoto/', $url);

        // Le proxy récupère la photo une seule fois avec la clé, côté serveur
        Http::fake(['places.googleapis.com/*' => Http::response(str_repeat('x', 2000), 200)]);
        $path = parse_url($url, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get($path)->assertOk();
        Http::assertSentCount(1);

        // Jeton falsifié refusé
        $this->get('/gphoto/'.PlacesPhoto::token('places/ChIJabc/photos/AbC_d-1', 1600).'x')->assertNotFound();
        $this->assertStringNotContainsString('AIzaSECRETKEY123', PlacesPhoto::scrubString('<img src="'.$g.'">'));
    }
}
