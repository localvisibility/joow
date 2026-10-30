<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Services\SiteRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_bar_on_unpaid_preview_only(): void
    {
        $site = Site::create(['slug' => 'bistrot-gh78', 'name' => 'Bistrot', 'sector' => 'restaurant', 'status' => 'preview', 'source' => 'joow-public', 'owner_email' => 'a@b.fr', 'site_data' => ['content' => ['hero_title' => 'x']]]);
        $r = app(SiteRenderer::class);
        $this->assertStringContainsString('id="joow-bar"', $r->html($site));
        $this->assertStringNotContainsString('id="joow-bar"', $r->html($site, true));
        $site->update(['status' => 'paid']);
        $this->assertStringNotContainsString('id="joow-bar"', $r->html($site->fresh()));
    }

    public function test_editor_link_sends_strangers_to_login(): void
    {
        Site::create(['slug' => 'bistrot-ij90', 'name' => 'Bistrot', 'sector' => 'restaurant', 'status' => 'preview', 'source' => 'joow-public', 'owner_email' => 'a@b.fr', 'site_data' => ['content' => ['hero_title' => 'x']]]);
        $this->get('/sites/bistrot-ij90/editeur')->assertRedirect(route('login'));
    }
}
