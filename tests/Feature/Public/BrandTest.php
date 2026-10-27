<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\LogoStudio;
use App\Services\SiteAgent;
use App\Services\SiteRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private function site(array $data = []): Site
    {
        $c = ['hero_title' => 'Des burgers faits maison', 'hero_subtitle' => 'Pains aériens', 'tagline' => 'Burger joint', 'about_p1' => 'Depuis 2015.', 'about_p2' => 'Produits frais.', 'badges' => ['Fait maison'], 'services' => [['name' => 'Sur place', 'desc' => 'Salle cosy', 'price' => '']], 'faq' => [['q' => 'Réserver ?', 'a' => 'Oui.']], 'cta_text' => 'Réservez.'];

        return Site::create(['slug' => 'ruisseau-'.fake()->lexify('????'), 'name' => 'Le Ruisseau', 'sector' => 'restaurant', 'status' => 'preview', 'source' => 'joow-public', 'owner_email' => 'test@exemple.fr',
            'site_data' => array_replace_recursive(['business' => ['name' => 'Le Ruisseau', 'city' => 'Paris', 'phone' => '01 42 23 31 23', 'address' => '65 rue du Ruisseau', 'photos' => []], 'content' => $c], $data)]);
    }

    private function whitePng(string $slug): string
    {
        Storage::fake('local');
        $img = imagecreatetruecolor(64, 32);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledrectangle($img, 8, 8, 56, 24, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        Storage::disk('local')->put("uploads/$slug/logo.png", ob_get_clean());

        return route('media.show', [$slug, 'logo.png']);
    }

    public function test_a_light_logo_is_detected_and_rendered_on_an_auto_background_with_the_name_optional(): void
    {
        $site = $this->site();
        $logo = $this->whitePng($site->slug);
        $d = $site->site_data;
        $d['business']['logo'] = $logo;
        $site->update(['site_data' => $d]);

        $html = app(SiteRenderer::class)->html($site->fresh());
        $this->assertStringContainsString('brand-bg-auto', $html);
        $this->assertStringContainsString('data-tone="light"', $html);
        $this->assertSame('light', $site->fresh()->site_data['brand']['tone'], 'tonalité mémorisée');
        $this->assertStringContainsString('.theme-light #nav.solid .brand-bg-auto[data-tone=light]{background:rgba(15,23,42', $html, 'fond sombre sur barre blanche');

        $d = $site->fresh()->site_data;
        $d['brand'] = ['show_name' => false, 'bg' => 'dark', 'size' => 'lg'] + $d['brand'];
        $site->update(['site_data' => $d]);
        $html = app(SiteRenderer::class)->html($site->fresh());
        $this->assertStringContainsString('brand-bg-dark', $html);
        $this->assertStringContainsString('h-14', $html);
        $this->assertStringContainsString('sr-only" data-edit="business.name"', $html);
    }

    public function test_the_owner_adjusts_the_header_uploads_a_logo_and_removes_it(): void
    {
        $this->mock(SiteRenderer::class)->shouldReceive('publish')->andReturn(['queued' => false]);
        Storage::fake('local');
        $user = User::create(['name' => 'T', 'email' => 'test@exemple.fr', 'password' => bcrypt('secret-123')]);
        $site = $this->site();
        $site->update(['user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('sites.editor.brand', $site->slug), ['bg' => 'dark', 'size' => 'sm', 'show_name' => false, 'invert' => true])->assertOk()->assertJsonPath('brand.bg', 'dark')->assertJsonPath('brand.size', 'sm')->assertJsonPath('brand.show_name', false)->assertJsonPath('brand.invert', true);
        $r = $this->actingAs($user)->post(route('sites.editor.brand', $site->slug), ['file' => UploadedFile::fake()->image('logo.png', 120, 60)], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('/media/'.$site->slug.'/logo-', $r->json('brand.logo'));
        $this->actingAs($user)->postJson(route('sites.editor.brand', $site->slug), ['logo_url' => 'https://evil.example/logo.svg'])->assertStatus(422);
        $this->actingAs($user)->postJson(route('sites.editor.brand', $site->slug), ['remove' => true])->assertOk()->assertJsonPath('brand.logo', null);
    }

    public function test_svg_sanitizer_keeps_clean_logos_and_rejects_dangerous_ones(): void
    {
        $studio = app(LogoStudio::class);
        $ok = $studio->sanitize('<svg viewBox="0 0 520 120" width="520" height="120"><circle cx="60" cy="60" r="40" fill="#7a5025"/><text x="140" y="76" font-size="48" fill="#fff" onclick="x()">Le Ruisseau</text></svg>', 'Le Ruisseau');
        $this->assertNotNull($ok);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $ok);
        $this->assertStringNotContainsString('onclick', $ok);
        $this->assertStringNotContainsString('width="520"', $ok);
        $long = $studio->sanitize('<svg viewBox="0 0 520 120"><text x="140" y="76" font-size="40">Le Ruisseau Burger Alésia</text></svg>', 'Le Ruisseau Burger Alésia');
        $this->assertMatchesRegularExpression('/viewBox="0 0 (\\d+) 120"/', $long);
        preg_match('/viewBox="0 0 (\\d+) 120"/', $long, $m);
        $this->assertGreaterThan(700, (int) $m[1], 'viewBox élargi pour un nom long');
        $this->assertNull($studio->sanitize('<svg viewBox="0 0 1 1"><script>alert(1)</script><text>Le Ruisseau</text></svg>', 'Le Ruisseau'));
        $this->assertNull($studio->sanitize('<svg viewBox="0 0 1 1"><image href="https://x/y.png"/><text>Le Ruisseau</text></svg>', 'Le Ruisseau'));
        $this->assertNull($studio->sanitize('<svg viewBox="0 0 1 1"><text>Autre nom</text></svg>', 'Le Ruisseau'), 'le nom doit figurer');
    }

    public function test_agent_brand_operation_updates_header_settings(): void
    {
        $site = $this->site();
        [$d, , $applied] = app(SiteAgent::class)->apply($site->site_data, [], $site, [['op' => 'brand', 'bg' => 'dark', 'show_name' => false, 'invert' => true]]);
        $this->assertSame('dark', $d['brand']['bg']);
        $this->assertFalse($d['brand']['show_name']);
        $this->assertTrue($d['brand']['invert']);
        $this->assertSame(['Logo : affichage ajusté'], $applied);
    }
}
