<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\SiteRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un site importé (page figée) ne doit jamais être remplacé par le gabarit Joow vide. */
class LegacySiteSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_page_is_shown_as_is_and_never_overwritten(): void
    {
        $dir = sys_get_temp_dir().'/joow-legacy-'.uniqid();
        config(['services.sites_path' => $dir]);
        @mkdir("$dir/clos", 0777, true);
        file_put_contents("$dir/clos/index.html", '<html><body><h1>Le vrai Clos</h1></body></html>');
        $user = User::factory()->create(['email' => 'proprio@yahoo.fr']);
        $site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'chambres', 'status' => 'paid', 'source' => 'legacy', 'owner_email' => 'proprio@yahoo.fr', 'html_content' => 'copie', 'site_data' => []]);

        $this->assertStringContainsString('Le vrai Clos', app(SiteRenderer::class)->html($site, true)); // aperçu du Studio = vraie page
        app(SiteRenderer::class)->publish($site);
        (new \App\Jobs\PublishSiteJob($site->id))->handle(app(SiteRenderer::class));
        $this->assertSame('<html><body><h1>Le vrai Clos</h1></body></html>', file_get_contents("$dir/clos/index.html"));

        $this->actingAs($user)->postJson(route('sites.editor.content', 'clos'), ['path' => 'content.hero_title', 'value' => 'x'])->assertStatus(422);
        $this->actingAs($user)->postJson(route('sites.editor.publish', 'clos'))->assertStatus(422);
        $this->assertSame('<html><body><h1>Le vrai Clos</h1></body></html>', file_get_contents("$dir/clos/index.html"));
    }
}
