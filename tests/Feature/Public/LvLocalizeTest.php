<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LvLocalizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_server_assets_legal_stats_and_owner_access_move_to_joow(): void
    {
        Mail::fake();
        $dir = sys_get_temp_dir().'/joow-lvl-'.uniqid();
        config(['services.sites_path' => $dir, 'app.url' => 'https://app.joow.fr']);
        $id = '1575c0d1-4ee0-4bfd-9376-7a910b2e9125';
        Http::fake([
            'local-visibility.com/api/get-legal.php*' => Http::response(['success' => true, 'data' => ['company_name' => 'R. Coulin', 'host_name' => 'OVH', 'host_address' => 'Roubaix']]),
            'local-visibility.com/widgets/legal.js*' => Http::response("const CONFIG={API_URL:'https://local-visibility.com/api/get-legal.php'};fetch(`\${CONFIG.API_URL}?site_id=x`);"),
            'local-visibility.com/previews/*' => Http::response(str_repeat('x', 500)),
        ]);
        $html = '<html><head><link rel="canonical" href="https://www.clos.fr/"><meta property="og:url" content="https://local-visibility.com/previews/clos/"><meta property="og:image" content="https://www.clos.fr/images/photo-1.jpg"><link href="https://local-visibility.com"></head><body><img src="https://local-visibility.com/previews/clos/images/hero.webp">'
            .'<div style="background:url(\'https://local-visibility.com/previews/clos/images/bg.jpg\')"></div><script>window.LEGAL_SITE_ID="'.$id.'";</script>'
            .'<script src="https://local-visibility.com/widgets/legal.js?v=1"></script></body></html>';
        @mkdir("$dir/clos", 0777, true);
        file_put_contents("$dir/clos/index.html", $html);
        $site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'chambres', 'status' => 'published', 'source' => 'legacy', 'html_content' => 'ancienne copie', 'site_data' => []]);

        $this->artisan('lv:localize', ['lv_slug' => 'clos', '--invite' => 'proprio@yahoo.fr'])->assertSuccessful();

        $page = file_get_contents("$dir/clos/index.html");
        $this->assertStringContainsString('src="/lv-assets/previews/clos/images/hero.webp"', $page);
        $this->assertStringContainsString("url('/lv-assets/previews/clos/images/bg.jpg')", $page);
        $this->assertStringContainsString('src="/lv-assets/widgets/legal.js"', $page);
        $this->assertStringContainsString('https://app.joow.fr/api/site/clos/view', $page);
        $this->assertFileExists("$dir/clos/lv-assets/previews/clos/images/hero.webp");
        $this->assertStringContainsString('/lv-assets/legal.json', file_get_contents("$dir/clos/lv-assets/widgets/legal.js"));
        $this->assertStringContainsString('Hostinger', file_get_contents("$dir/clos/lv-assets/legal.json"));
        $this->assertSame($page, $site->fresh()->html_content);
        $this->assertStringContainsString('og:url" content="https://www.clos.fr/"', $page);
        $this->assertStringContainsString('og:image" content="https://www.clos.fr/lv-assets/previews/clos/images/hero.webp"', $page);
        $this->assertStringContainsString('Sitemap: https://www.clos.fr/sitemap.xml', file_get_contents("$dir/clos/robots.txt"));
        $this->assertStringContainsString('<loc>https://www.clos.fr/</loc>', file_get_contents("$dir/clos/sitemap.xml"));

        $this->assertSame('proprio@yahoo.fr', $site->fresh()->owner_email);
        Mail::assertSent(\App\Mail\JoowNotification::class, fn ($m) => $m->hasTo('proprio@yahoo.fr') && str_contains($m->render(), 'reservations'));

        // Relancer ne duplique pas le traceur
        $this->artisan('lv:localize', ['lv_slug' => 'clos'])->assertSuccessful();
        $this->assertSame(1, substr_count(file_get_contents("$dir/clos/index.html"), 'data-joow-stats'));
    }
}
