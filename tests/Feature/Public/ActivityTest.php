<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_prospect_activity_is_recorded_and_shown_to_admins(): void
    {
        config(['joow.admins' => ['boss@joow.fr']]);
        $owner = User::factory()->create(['email' => 'client@exemple.fr']);
        $site = Site::create(['slug' => 'bistrot-ab12', 'name' => 'Bistrot', 'sector' => 'restaurant', 'status' => 'preview', 'source' => 'joow-public',
            'owner_email' => 'client@exemple.fr', 'user_id' => $owner->id, 'site_data' => ['content' => ['hero_title' => 'x']]]);

        $this->postJson('/api/site/bistrot-ab12/view', ['full' => true])->assertOk();
        $this->postJson('/api/site/bistrot-ab12/view', ['full' => false])->assertOk();   // aperçu dans un cadre : pas « plein écran »
        $this->get('/site/bistrot-ab12');
        $this->actingAs($owner)->get('/sites/bistrot-ab12/editeur');

        $act = $site->fresh()->site_data['activity'];
        $this->assertSame(1, $act['fullpage']['n']);
        $this->assertSame(1, $act['preview']['n']);
        $this->assertSame(1, $act['studio']['n']);

        // L'équipe Joow n'est pas comptée
        $admin = User::factory()->create(['email' => 'boss@joow.fr']);
        $this->travel(11)->minutes();
        $this->actingAs($admin)->get('/sites/bistrot-ab12/editeur');
        $this->assertSame(1, $site->fresh()->site_data['activity']['studio']['n']);

        $this->actingAs($admin)->get('/admin?tab=sites&f=studio')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('data.rows.0.activity.studio.n', 1)->where('data.rows.0.email', 'client@exemple.fr'));
        $this->actingAs($admin)->get('/admin?tab=sites&f=noemail')->assertOk()->assertInertia(fn (Assert $p) => $p->has('data.rows', 0));
    }
}
