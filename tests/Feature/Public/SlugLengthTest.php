<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SlugLengthTest extends TestCase
{
    use RefreshDatabase;

    private const LONG = 'Dj Ludo Remix Gard Vaucluse Ardèche Drôme Languedoc Roussillon Limoges Haute Vienne Brive la Gaillarde dordogne France Lozère';

    public function test_new_slugs_fit_in_a_dns_label(): void
    {
        $slug = Site::makeSlug(self::LONG);
        $this->assertLessThanOrEqual(63, strlen($slug));
        $this->assertMatchesRegularExpression('/^dj-ludo-remix-gard-vaucluse-ardeche-[a-z0-9]{4}$/', $slug);
        $this->assertMatchesRegularExpression('/^le-bistrot-[a-z0-9]{4}$/', Site::makeSlug('Le Bistrot'));
    }

    public function test_existing_long_slugs_are_shortened_and_old_links_redirect(): void
    {
        $old = 'dj-ludo-remix-gard-vaucluse-ardeche-drome-languedoc-roussillon-limoges-haute-vienne-3ngx';
        $site = Site::create(['slug' => $old, 'name' => self::LONG, 'sector' => 'evenementiel', 'status' => 'ready', 'source' => 'joow-public',
            'preview_url' => "https://$old.joow.fr", 'site_data' => ['content' => ['hero_title' => 'x'], 'logo' => "https://app.joow.fr/media/$old/logo.png"]]);
        DB::table('site_stats')->insert(['site_slug' => $old, 'day' => now()->toDateString()]);

        $this->artisan('sites:shorten-slugs')->assertSuccessful();

        $site->refresh();
        $this->assertLessThanOrEqual(63, strlen($site->slug));
        $this->assertSame("https://{$site->slug}.joow.fr", $site->preview_url);
        $this->assertSame("https://app.joow.fr/media/$old/logo.png", $site->site_data['logo']); // photos inchangées
        $this->assertSame(1, DB::table('site_stats')->where('site_slug', $site->slug)->count());
        $this->get("/site/$old")->assertRedirect("/site/{$site->slug}");
    }
}
