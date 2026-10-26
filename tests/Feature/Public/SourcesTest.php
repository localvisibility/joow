<?php

namespace Tests\Feature\Public;

use App\Jobs\GenerateSiteJob;
use App\Models\Site;
use App\Services\BusinessSource;
use App\Services\GooglePlaces;
use App\Services\SectorDetector;
use App\Services\SiteImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Queue::fake();
    }

    private const HTML = <<<HTML
<!doctype html><html lang="fr"><head><title>Accueil - Plomberie Dupont | Plombier à Lyon</title>
<meta name="description" content="Plombier chauffagiste à Lyon depuis 1998"><meta property="og:image" content="/img/devanture.jpg"></head>
<body><header><img src="/img/logo.png" alt="logo"><a href="tel:0478123456">04 78 12 34 56</a></header>
<nav><a href="/prestations">Nos prestations</a><a href="/tarifs">Tarifs</a><a href="/mentions-legales">Mentions</a><a href="https://facebook.com/x">FB</a></nav>
<main><h1>Plomberie Dupont, plombier à Lyon</h1><p>Dépannage, installation sanitaire et chauffage depuis 1998. Intervention en moins de 2 heures sur Lyon et sa région, devis gratuit, garantie décennale sur toutes nos installations.</p>
<ul><li>Dépannage fuite</li><li>Installation chaudière</li></ul><img src="/img/atelier.jpg" width="1200" height="800"><img src="/img/icon-star.png"></main>
<footer><p>12 rue de la République, 69002 Lyon</p><p>contact@plomberie-dupont.fr</p><p>Lundi - vendredi : 8h - 19h</p></footer></body></html>
HTML;

    public function test_importer_extracts_structured_data(): void
    {
        Http::fake([
            'https://plomberie-dupont.fr' => Http::response(self::HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'https://plomberie-dupont.fr/prestations' => Http::response('<html><body><h1>Nos prestations</h1><p>'.str_repeat('Détail des prestations de plomberie et chauffage. ', 8).'</p></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://plomberie-dupont.fr/tarifs' => Http::response('<html><body><h1>Tarifs</h1><p>'.str_repeat('Dépannage dès 89 €, déplacement inclus. ', 6).'</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $imp = app(SiteImporter::class)->crawl('plomberie-dupont.fr');

        $this->assertSame('Plomberie Dupont', $imp['name']);
        $this->assertSame('04 78 12 34 56', $imp['phone']);
        $this->assertSame('contact@plomberie-dupont.fr', $imp['email']);
        $this->assertStringContainsString('69002 Lyon', $imp['address']);
        $this->assertSame('Lyon', $imp['city']);
        $this->assertSame('https://plomberie-dupont.fr/img/logo.png', $imp['logo']);
        $this->assertContains('https://plomberie-dupont.fr/img/devanture.jpg', $imp['images']);
        $this->assertContains('https://plomberie-dupont.fr/img/atelier.jpg', $imp['images']);
        $this->assertNotContains('https://plomberie-dupont.fr/img/icon-star.png', $imp['images']);
        $this->assertCount(3, $imp['pages']);
        $this->assertFalse($imp['thin']);
        $this->assertNotEmpty($imp['hours']);
    }

    public function test_mirror_keeps_svg_logos_as_svg_without_scripts(): void
    {
        Http::fake(['https://old.site/logo.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>alert(1)</script><rect onclick="x()" fill="#c8102e" width="10" height="10"/></svg>'.str_repeat(' ', 300), 200, ['Content-Type' => 'image/svg+xml'])]);
        $out = app(\App\Services\ImageStore::class)->mirror(['https://old.site/logo.svg'], 'demo-svg');
        $this->assertCount(1, $out);
        $this->assertStringEndsWith('.svg', $out['https://old.site/logo.svg']);
        $file = basename($out['https://old.site/logo.svg']);
        $svg = Storage::disk('local')->get("uploads/demo-svg/$file");
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('onclick', $svg);
        $this->assertStringContainsString('#c8102e', $svg);
    }

    public function test_business_source_builds_profile_from_intake(): void
    {
        $site = Site::create([
            'slug' => 'plomberie-test-abcd', 'name' => 'Plomberie Test', 'sector' => 'plombier', 'status' => 'generating', 'source' => 'questionnaire',
            'city' => 'Lyon', 'owner_email' => 'pro@exemple.fr',
            'site_data' => ['intake' => ['name' => 'Plomberie Test', 'city' => 'Lyon', 'phone' => '06 00 00 00 00', 'email' => 'pro@exemple.fr', 'services' => ['Dépannage', 'Chaudière'], 'strengths' => ['Devis gratuit'], 'since' => 2010, 'hours' => 'lun-ven 8h-19h ; sam 9h-12h', 'photos' => ['a1.jpg']]],
        ]);
        $this->mock(GooglePlaces::class)->shouldNotReceive('lookup');

        $b = app(BusinessSource::class)->resolve($site);

        $this->assertSame('questionnaire', $b['source']);
        $this->assertSame('Plomberie Test', $b['name']);
        $this->assertNull($b['rating']);
        $this->assertSame([], $b['reviews']);
        $this->assertSame(['Dépannage', 'Chaudière'], $b['owner']['services']);
        $this->assertSame(['lun-ven 8h-19h', 'sam 9h-12h'], $b['opening_hours']);
        $this->assertSame([config('app.url').'/media/plomberie-test-abcd/a1.jpg'], $b['photos']);
    }

    public function test_business_source_resolves_redesign_with_mirrored_images_and_google(): void
    {
        $png = imagecreatetruecolor(800, 600); ob_start(); imagepng($png); $bytes = ob_get_clean();
        Http::fake([
            'https://plomberie-dupont.fr' => Http::response(self::HTML, 200, ['Content-Type' => 'text/html']),
            'https://plomberie-dupont.fr/prestations' => Http::response('<html><body><h1>Prestations</h1><p>'.str_repeat('Plomberie et chauffage toutes marques. ', 10).'</p></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://plomberie-dupont.fr/tarifs' => Http::response('', 404),
            'https://plomberie-dupont.fr/img/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
        ]);
        $this->mock(GooglePlaces::class, function ($m) {
            $m->shouldReceive('lookup')->with('Plomberie Dupont Lyon')->once()->andReturn(['place_id' => 'ChIJdup', 'name' => 'Plomberie Dupont', 'address' => '12 rue de la République, 69002 Lyon', 'city' => 'Lyon', 'phone' => '04 78 12 34 56', 'rating' => 4.8, 'reviews_count' => 120, 'reviews' => [], 'photos' => ['https://g/p1.jpg'], 'maps_url' => null, 'lat' => null, 'lng' => null, 'types' => ['plumber'], 'primary_type' => 'Plombier', 'opening_hours' => [], 'website' => 'https://plomberie-dupont.fr']);
            $m->shouldReceive('lookup')->with('ChIJdup')->once()->andReturn(['place_id' => 'ChIJdup', 'name' => 'Plomberie Dupont', 'address' => '12 rue de la République, 69002 Lyon', 'city' => 'Lyon', 'phone' => '04 78 12 34 56', 'rating' => 4.8, 'reviews_count' => 120, 'reviews' => [], 'photos' => ['https://g/p1.jpg'], 'maps_url' => null, 'lat' => null, 'lng' => null, 'types' => ['plumber'], 'primary_type' => 'Plombier', 'opening_hours' => [], 'website' => 'https://plomberie-dupont.fr']);
        });
        $this->mock(SectorDetector::class)->shouldReceive('detect')->once()->andReturn('plombier');
        $site = Site::create(['slug' => 'plomberie-dupont-ab12', 'name' => 'Plomberie Dupont', 'sector' => 'service', 'status' => 'generating', 'source' => 'refonte', 'site_data' => ['import' => ['url' => 'https://plomberie-dupont.fr', 'host' => 'plomberie-dupont.fr']]]);

        $b = app(BusinessSource::class)->resolve($site);

        $site->refresh();
        $this->assertSame('refonte', $b['source']);
        $this->assertSame('ChIJdup', $site->place_id);
        $this->assertSame('plombier', $site->sector);
        $this->assertSame(4.8, $b['rating']);
        $this->assertCount(2, $site->site_data['import']['pages']);
        // Images de l'ancien site rapatriées : URLs Joow d'abord, photo Google ensuite
        $this->assertStringStartsWith(config('app.url').'/media/plomberie-dupont-ab12/', $b['photos'][0]);
        $this->assertSame('https://g/p1.jpg', end($b['photos']));
        $this->assertStringStartsWith(config('app.url').'/media/plomberie-dupont-ab12/', $b['logo']);
        $this->assertSame('https://plomberie-dupont.fr', $b['website']);
    }

    public function test_google_profile_with_website_is_combined_with_the_site_content(): void
    {
        $png = imagecreatetruecolor(800, 600); ob_start(); imagepng($png); $bytes = ob_get_clean();
        Http::fake([
            'https://plomberie-dupont.fr' => Http::response(self::HTML, 200, ['Content-Type' => 'text/html']),
            'https://plomberie-dupont.fr/prestations' => Http::response('<html><body><h1>Prestations</h1><p>'.str_repeat('Plomberie et chauffage toutes marques. ', 10).'</p></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://plomberie-dupont.fr/tarifs' => Http::response('', 404),
            'https://plomberie-dupont.fr/img/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
        ]);
        $this->mock(GooglePlaces::class)->shouldReceive('lookup')->with('ChIJdup')->once()->andReturn(['place_id' => 'ChIJdup', 'name' => 'Plomberie Dupont', 'address' => '12 rue de la République, 69002 Lyon', 'city' => 'Lyon', 'phone' => '04 78 12 34 56', 'rating' => 4.8, 'reviews_count' => 120, 'reviews' => [], 'photos' => ['https://g/p1.jpg'], 'maps_url' => null, 'lat' => null, 'lng' => null, 'types' => ['plumber'], 'primary_type' => 'Plombier', 'opening_hours' => ['lundi: 9-18'], 'website' => 'https://plomberie-dupont.fr']);
        $site = Site::create(['slug' => 'plomberie-dupont-cd34', 'name' => 'Plomberie Dupont', 'sector' => 'plombier', 'status' => 'generating', 'source' => 'joow-public', 'place_id' => 'ChIJdup']);

        $progress = [];
        $b = app(BusinessSource::class)->resolve($site, function (array $more) use (&$progress) { $progress = array_merge($progress, $more); });

        $this->assertSame('google', $b['source']);
        $this->assertTrue($b['combo']);
        $this->assertSame(4.8, $b['rating']);
        $this->assertSame('plomberie-dupont.fr', $progress['website']);
        $this->assertSame(2, $progress['site_pages']);
        $this->assertCount(2, $b['existing']['pages']);
        $this->assertStringStartsWith(config('app.url').'/media/plomberie-dupont-cd34/', $b['photos'][0]);
        $this->assertContains('https://g/p1.jpg', $b['photos']);
        $this->assertNotEmpty($site->fresh()->site_data['import']['pages']);
    }

    public function test_google_profile_website_on_social_network_is_ignored(): void
    {
        Http::fake();
        $this->mock(GooglePlaces::class)->shouldReceive('lookup')->once()->andReturn(['place_id' => 'ChIJfb', 'name' => 'Démo', 'address' => 'Lyon', 'city' => 'Lyon', 'phone' => null, 'rating' => null, 'reviews_count' => 0, 'reviews' => [], 'photos' => [], 'maps_url' => null, 'lat' => null, 'lng' => null, 'types' => [], 'primary_type' => null, 'opening_hours' => [], 'website' => 'https://www.facebook.com/demo']);
        $site = Site::create(['slug' => 'demo-fb-1', 'name' => 'Démo', 'sector' => 'service', 'status' => 'generating', 'source' => 'joow-public', 'place_id' => 'ChIJfb']);
        $b = app(BusinessSource::class)->resolve($site);
        $this->assertFalse($b['combo']);
        Http::assertNothingSent();
    }

    public function test_redesign_accepts_google_match_by_phone_when_names_differ(): void
    {
        Http::fake(['https://plomberie-dupont.fr' => Http::response(self::HTML, 200, ['Content-Type' => 'text/html']), '*' => Http::response('', 404)]);
        $g = ['place_id' => 'ChIJsarl', 'name' => 'SARL DUPONT SERVICES', 'address' => '12 rue de la République, 69002 Lyon', 'city' => 'Lyon', 'phone' => '+33 4 78 12 34 56', 'rating' => 4.5, 'reviews_count' => 10, 'reviews' => [], 'photos' => [], 'maps_url' => null, 'lat' => null, 'lng' => null, 'types' => ['plumber'], 'primary_type' => 'Plombier', 'opening_hours' => [], 'website' => null];
        $this->mock(GooglePlaces::class, function ($m) use ($g) {
            $m->shouldReceive('lookup')->with('Plomberie Dupont Lyon')->once()->andReturn($g);
            $m->shouldReceive('lookup')->with('ChIJsarl')->once()->andReturn($g);
        });
        $this->mock(SectorDetector::class)->shouldReceive('detect')->andReturn('plombier');
        $site = Site::create(['slug' => 'plomberie-dupont-ef56', 'name' => 'Plomberie Dupont', 'sector' => 'service', 'status' => 'generating', 'source' => 'refonte', 'site_data' => ['import' => ['url' => 'https://plomberie-dupont.fr', 'host' => 'plomberie-dupont.fr']]]);

        $b = app(BusinessSource::class)->resolve($site);

        $this->assertSame('ChIJsarl', $site->fresh()->place_id);
        $this->assertSame('refonte', $b['source']);
        $this->assertTrue($b['combo']);
    }

    public function test_intake_store_creates_site_and_dispatches_generation(): void
    {
        $this->mock(SectorDetector::class)->shouldReceive('detect')->andReturn('plombier');
        $token = 'tok123';
        Storage::disk('local')->put("uploads/intake-$token/p1.jpg", 'x');

        $this->withSession(['intake_token' => $token])->post(route('intake.store'), [
            'name' => 'Plomberie Martin', 'city' => 'Toulouse', 'phone' => '06 12 34 56 78', 'email' => 'martin@exemple.fr',
            'sector_hint' => 'plombier', 'services' => ['Dépannage'], 'photos' => ['p1.jpg', 'inconnu.jpg'],
        ])->assertRedirect();

        $site = Site::where('source', 'questionnaire')->firstOrFail();
        $this->assertSame('plombier', $site->sector);
        $this->assertSame('martin@exemple.fr', $site->owner_email);
        $this->assertSame(['p1.jpg'], $site->site_data['intake']['photos']);
        Storage::disk('local')->assertExists("uploads/{$site->slug}/p1.jpg");
        Queue::assertPushed(GenerateSiteJob::class);
    }

    public function test_intake_photo_upload_is_resized_to_jpeg(): void
    {
        $file = UploadedFile::fake()->image('grande.png', 2400, 1600);
        $res = $this->postJson(route('intake.photos'), ['photos' => [$file]])->assertOk()->json();
        $this->assertCount(1, $res['photos']);
        $this->assertStringEndsWith('.jpg', $res['photos'][0]['id']);
        $token = session('intake_token');
        $stored = Storage::disk('local')->get("uploads/intake-$token/".$res['photos'][0]['id']);
        [$w] = getimagesizefromstring($stored);
        $this->assertSame(1600, $w);
    }

    public function test_redesign_unreadable_site_falls_back_to_intake(): void
    {
        Http::fake(['*' => Http::response('', 403)]);
        $this->post(route('redesign.start'), ['url' => 'www.site-bloque.fr'])
            ->assertRedirect(route('intake.create', ['q' => 'Site Bloque', 'email' => null, 'from_url' => 'site-bloque.fr']));
        $this->assertSame(0, Site::count());
    }

    public function test_redesign_creates_site_and_dispatches(): void
    {
        Http::fake(['*' => Http::response(self::HTML, 200, ['Content-Type' => 'text/html'])]);
        $this->post(route('redesign.start'), ['url' => 'plomberie-dupont.fr', 'email' => 'pro@exemple.fr'])->assertRedirect();
        $site = Site::where('source', 'refonte')->firstOrFail();
        $this->assertSame('https://plomberie-dupont.fr', $site->site_data['import']['url']);
        $this->assertSame('pro@exemple.fr', $site->owner_email);
        Queue::assertPushed(GenerateSiteJob::class);
    }

    public function test_attach_google_merges_reviews_and_photos_without_touching_content(): void
    {
        $site = Site::create([
            'slug' => 'demo-google-xyz', 'name' => 'Démo', 'sector' => 'plombier', 'status' => 'preview', 'source' => 'questionnaire', 'owner_email' => 'pro@exemple.fr',
            'site_data' => ['content' => ['hero_title' => 'Mon titre'], 'business' => ['name' => 'Démo', 'phone' => '06', 'photos' => ['https://x/a.jpg']]],
        ]);
        $this->mock(GooglePlaces::class)->shouldReceive('lookup')->once()->andReturn([
            'place_id' => 'ChIJtest', 'name' => 'Démo', 'address' => '1 rue A, 69000 Lyon', 'city' => 'Lyon', 'phone' => '04', 'rating' => 4.7, 'reviews_count' => 88,
            'reviews' => [['author' => 'A', 'rating' => 5, 'text' => str_repeat('Très bien. ', 5)]], 'photos' => ['https://g/1.jpg'], 'maps_url' => 'https://maps/x', 'lat' => 1.0, 'lng' => 2.0,
            'types' => ['plumber'], 'primary_type' => 'Plombier', 'opening_hours' => ['lundi: 9-18'], 'website' => null,
        ]);
        $this->mock(\App\Services\SiteRenderer::class)->shouldReceive('publish')->once()->andReturn(['queued' => false]);

        $this->withSession(['joow_sites' => [$site->slug]])
            ->postJson(route('sites.editor.google', $site->slug), ['place_id' => 'ChIJtest'])
            ->assertOk()->assertJsonPath('google.reviews_count', 88);

        $site->refresh();
        $this->assertSame('ChIJtest', $site->place_id);
        $this->assertSame('Mon titre', $site->site_data['content']['hero_title']);
        $this->assertSame(['https://x/a.jpg', 'https://g/1.jpg'], $site->site_data['business']['photos']);
        $this->assertSame('06', $site->site_data['business']['phone']);
        $this->assertSame(4.7, $site->site_data['business']['rating']);
    }
}
