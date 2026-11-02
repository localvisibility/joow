<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\SiteRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Site $site;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->dir = sys_get_temp_dir().'/joow-seo-'.uniqid();
        config(['services.sites_path' => $this->dir]);
        $this->owner = User::factory()->create(['email' => 'client@exemple.fr']);
        $this->site = Site::create(['slug' => 'plombier-seo', 'name' => 'Plomberie Dupont', 'sector' => 'plombier', 'city' => 'Lyon', 'status' => 'published', 'hosting_plan' => 'pro',
            'source' => 'joow-public', 'user_id' => $this->owner->id, 'owner_email' => 'client@exemple.fr', 'phone' => '06 11 22 33 44', 'ai_credits_monthly' => 5, 'ai_credits_reset_at' => now()->addMonth(),
            'site_data' => ['content' => ['hero_title' => 'Votre plombier à Lyon'], 'business' => ['name' => 'Plomberie Dupont', 'city' => 'Lyon', 'phone' => '06 11 22 33 44'],
                'images' => ['hero' => 'https://app.joow.fr/media/x/hero.jpg']]]);
    }

    public function test_audit_scores_the_site_and_gives_advice(): void
    {
        $this->actingAs($this->owner)->get('/referencement')->assertOk()->assertInertia(fn ($p) => $p->component('Seo')
            ->where('current', 'plombier-seo')
            ->where('audit.pages.0.key', 'index')
            ->has('audit.site')
            ->where('audit.score', fn ($s) => $s > 0 && $s <= 100)
            ->where('audit.pages.0.checks', fn ($c) => collect($c)->contains('id', 'keyword')));

        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/referencement/plombier-seo', ['key' => 'index', 'title' => 'x'])->assertForbidden();
    }

    public function test_custom_title_description_and_alts_are_published_with_structured_data(): void
    {
        $this->actingAs($this->owner)->postJson('/referencement/plombier-seo', ['key' => 'index', 'keyword' => 'plombier Lyon',
            'title' => 'Plombier Lyon : dépannage rapide | Plomberie Dupont', 'description' => 'Fuite, débouchage, chauffe-eau : appelez votre plombier à Lyon pour une intervention rapide et un devis clair.'])
            ->assertOk()->assertJsonPath('audit.pages.0.keyword', 'plombier Lyon');
        $this->actingAs($this->owner)->postJson('/referencement/plombier-seo', ['key' => 'index', 'alts' => ['https://app.joow.fr/media/x/hero.jpg' => 'Plombier réparant une fuite sous un évier à Lyon']])->assertOk();

        $html = app(SiteRenderer::class)->html($this->site->fresh());
        $this->assertStringContainsString('<title>Plombier Lyon : dépannage rapide | Plomberie Dupont</title>', $html);
        $this->assertStringContainsString('content="Fuite, débouchage, chauffe-eau', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Plumber"', $html);
        $this->assertStringNotContainsString('aggregateRating', $html); // aucune note dans les données structurées
        $this->assertStringContainsString('alt="Plombier réparant une fuite sous un évier à Lyon"', $html);

        // Publication : sitemap + robots
        $this->assertFileExists($this->dir.'/plombier-seo/sitemap.xml');
        $this->assertStringContainsString('Sitemap: https://plombier-seo.joow.fr/sitemap.xml', file_get_contents($this->dir.'/plombier-seo/robots.txt'));
    }

    public function test_ai_rewrites_meta_and_charges_one_credit(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'title' => 'Plombier à Lyon : dépannage et débouchage | Dupont',
            'description' => 'Votre plombier à Lyon pour fuites, débouchage et chauffe-eau. Appelez-nous pour une intervention rapide et un devis clair.',
        ])]]]]]])]);
        $this->actingAs($this->owner)->postJson('/referencement/plombier-seo/ia', ['type' => 'meta', 'key' => 'index'])->assertOk()
            ->assertJsonPath('audit.pages.0.custom.title', 'Plombier à Lyon : dépannage et débouchage | Dupont');
        $this->assertSame(4, $this->site->fresh()->ai_credits_monthly);

        $this->site->forceFill(['ai_credits_monthly' => 0, 'ai_credits_wallet' => 0])->save();
        $this->actingAs($this->owner)->postJson('/referencement/plombier-seo/ia', ['type' => 'meta', 'key' => 'index'])->assertStatus(402);
    }

    public function test_preview_site_is_not_indexed(): void
    {
        $this->site->forceFill(['status' => 'preview'])->save();
        app(SiteRenderer::class)->publish($this->site->fresh());
        $this->assertStringContainsString('Disallow: /', file_get_contents($this->dir.'/plombier-seo/robots.txt'));
        $this->assertFileDoesNotExist($this->dir.'/plombier-seo/sitemap.xml');
    }
}
