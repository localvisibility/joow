<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_finds_any_site_by_server_side_search_and_online_sites_come_first(): void
    {
        $this->withoutVite();
        config(['joow.admins' => ['boss@joow.fr']]);
        $admin = User::factory()->create(['email' => 'boss@joow.fr']);
        $old = Site::create(['slug' => 'clos-du-petit-jesus-cassis', 'name' => 'Le Clos du petit Jesus', 'sector' => 'chambres', 'status' => 'paid', 'source' => 'legacy', 'custom_domain' => 'clos-du-petit-jesus-cassis.com', 'site_data' => []]);
        $old->forceFill(['created_at' => now()->subYear()])->save();
        foreach (range(1, 130) as $i) {
            Site::create(['slug' => "apercu-$i", 'name' => "Aperçu $i", 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'site_data' => []]);
        }

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertInertia(fn ($p) => $p
            ->where('stats.online', 1)->where('stats.previews', 130)->where('total', 131)->where('sites.0.slug', 'clos-du-petit-jesus-cassis'));
        $this->actingAs($admin)->get('/dashboard?q=petit-jesus')->assertOk()->assertInertia(fn ($p) => $p->where('total', 1)->where('sites.0.slug', 'clos-du-petit-jesus-cassis'));
    }
}
