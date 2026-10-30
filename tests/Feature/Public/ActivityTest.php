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

    public function test_admin_can_email_a_prospect_with_a_studio_link(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        config(['joow.admins' => ['boss@joow.fr']]);
        $admin = User::factory()->create(['email' => 'boss@joow.fr']);
        $site = Site::create(['slug' => 'bistrot-cd34', 'name' => 'Bistrot Léa', 'sector' => 'restaurant', 'status' => 'preview', 'source' => 'joow-public',
            'owner_email' => 'lea@exemple.fr', 'owner_name' => 'Léa Martin', 'site_data' => ['content' => ['hero_title' => 'x']]]);

        $this->actingAs($admin)->post("/admin/sites/{$site->id}/email", ['subject' => '{prenom}, votre site {site}', 'message' => 'Bonjour {prenom}', 'link' => 'studio'])
            ->assertRedirect()->assertSessionHas('status');

        $msgs = $site->fresh()->site_data['emails']['admin_messages'];
        $this->assertSame('Léa, votre site Bistrot Léa', $msgs[0]['subject']);
        $this->assertNotNull(User::firstWhere('email', 'lea@exemple.fr'));

        // Désinscrit : refusé
        User::firstWhere('email', 'lea@exemple.fr')->forceFill(['marketing_optout_at' => now()])->save();
        $this->actingAs($admin)->post("/admin/sites/{$site->id}/email", ['subject' => 'x', 'message' => 'y'])->assertStatus(422);
    }

    public function test_admin_sees_ai_assistant_requests(): void
    {
        config(['joow.admins' => ['boss@joow.fr']]);
        $admin = User::factory()->create(['email' => 'boss@joow.fr']);
        $site = Site::create(['slug' => 'bistrot-kl12', 'name' => 'Bistrot', 'sector' => 'restaurant', 'status' => 'preview', 'source' => 'joow-public', 'owner_email' => 'lea@exemple.fr',
            'site_data' => ['content' => ['hero_title' => 'x'], 'chat' => [['role' => 'user', 'text' => 'Passe en thème sombre'], ['role' => 'ai', 'text' => 'Fait', 'applied' => ['Thème sombre']]]]]);
        $site->forceFill(['ai_credits_wallet' => 10])->save();
        app(\App\Services\AiCredits::class)->charge($site, 1, ['message' => 'Passe en thème sombre', 'applied' => ['Thème sombre']]);
        app(\App\Services\AiCredits::class)->charge($site, 0, ['message' => 'Combien coûte la mise en ligne ?']);

        $rows = collect($this->actingAs($admin)->get('/admin?tab=ai')->assertOk()->viewData('page')['props']['data']['requests']['rows']);
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->contains(fn ($r) => $r['message'] === 'Passe en thème sombre' && (int) $r['credits'] === 1));
        $this->assertTrue($rows->contains(fn ($r) => $r['message'] === 'Combien coûte la mise en ligne ?' && (int) $r['credits'] === 0));
        $this->actingAs($admin)->get('/admin?tab=sites')->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.rows.0.ai_chat.0.text', 'Passe en thème sombre'));
    }
}
