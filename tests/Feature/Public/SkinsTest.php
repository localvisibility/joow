<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\SiteRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkinsTest extends TestCase
{
    use RefreshDatabase;

    private function site(string $sector, string $slug): Site
    {
        return Site::create(['slug' => $slug, 'name' => 'Gîte des Oliviers', 'sector' => $sector, 'status' => 'published', 'source' => 'joow-public', 'owner_email' => 'gite@exemple.fr',
            'site_data' => ['business' => ['name' => 'Gîte des Oliviers', 'city' => 'Gordes'], 'content' => ['hero_title' => 'Un gîte au calme', 'services' => [['name' => 'Gîte 4 personnes', 'desc' => 'Piscine']]]]]);
    }

    public function test_gites_can_switch_to_charme_or_nature_and_other_trades_cannot(): void
    {
        config(['services.sites_path' => sys_get_temp_dir().'/joow-skin-'.uniqid()]);
        $user = User::factory()->create(['email' => 'gite@exemple.fr']);
        $gite = $this->site('hebergement', 'gite-oliviers');

        $this->actingAs($user)->getJson(route('sites.editor.state', $gite->slug))->assertJsonPath('skins_allowed', true);
        $this->actingAs($user)->postJson(route('sites.editor.style', $gite->slug), ['skin' => 'charme'])->assertOk()->assertJsonPath('skin', 'charme')->assertJsonPath('font', 'Playfair Display');
        $html = app(SiteRenderer::class)->html($gite->fresh());
        $this->assertStringContainsString('skin-charme', $html);
        $this->assertStringContainsString('family=Inter', $html);

        $this->actingAs($user)->postJson(route('sites.editor.style', $gite->slug), ['skin' => 'nature'])->assertOk()->assertJsonPath('accent', '#b0613a');
        $this->assertStringContainsString('skin-nature', app(SiteRenderer::class)->html($gite->fresh()));
        $this->actingAs($user)->postJson(route('sites.editor.style', $gite->slug), ['skin' => 'joow'])->assertOk();
        $this->assertStringNotContainsString(' skin-', app(SiteRenderer::class)->html($gite->fresh()));

        $resto = $this->site('restaurant', 'resto-x');
        $this->actingAs($user)->getJson(route('sites.editor.state', $resto->slug))->assertJsonPath('skins_allowed', false);
        $this->actingAs($user)->postJson(route('sites.editor.style', $resto->slug), ['skin' => 'charme'])->assertStatus(422);
    }
}
