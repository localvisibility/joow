<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function site(array $attrs = []): Site
    {
        return Site::create(array_merge(['slug' => 'demo-'.fake()->unique()->lexify('????'), 'name' => 'Démo', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'site_data' => ['content' => ['hero_title' => 'x']]], $attrs));
    }

    private function admin(): User
    {
        config(['joow.admins' => ['boss@joow.fr']]);

        return User::factory()->create(['email' => 'boss@joow.fr']);
    }

    public function test_non_admins_cannot_open_the_back_office(): void
    {
        config(['joow.admins' => ['boss@joow.fr']]);
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_mrr_and_conversion(): void
    {
        $admin = $this->admin();
        $this->site(['status' => 'published', 'hosting_plan' => 'pro', 'subscription_status' => 'active', 'paid_at' => now()]);
        $this->site(['status' => 'published', 'hosting_plan' => 'pro', 'subscription_status' => 'active', 'paid_at' => now()]);
        $this->site(['status' => 'paid', 'hosting_plan' => 'liberte', 'paid_at' => now()]);
        $this->site();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Index')
            ->where('kpis.active_pro', 2)
            ->where('kpis.liberte_total', 1)
            ->where('kpis.new_paid', 3)
            ->where('kpis.generated', 4)
            ->where('kpis.mrr', fn ($v) => (float) $v === 78.0)
            ->has('data.funnel', 6));

        foreach (['subscriptions', 'clients', 'sites', 'invoices', 'domains', 'ai'] as $tab) {
            $this->actingAs($admin)->get("/admin?tab=$tab&q=demo")->assertOk();
        }
    }

    public function test_unpaid_previews_can_be_deleted_but_not_paid_sites(): void
    {
        $admin = $this->admin();
        $preview = $this->site();
        $paid = $this->site(['status' => 'published', 'hosting_plan' => 'pro']);

        $this->actingAs($admin)->delete("/admin/sites/{$preview->id}")->assertRedirect();
        $this->assertModelMissing($preview);
        $this->actingAs($admin)->delete("/admin/sites/{$paid->id}")->assertStatus(422);
        $this->assertModelExists($paid);
    }

    public function test_admin_can_impersonate_a_client_and_come_back(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create();

        $this->actingAs($admin)->post("/admin/clients/{$client->id}/connexion")->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($client);
        $this->get('/admin')->assertForbidden();

        $this->post('/admin/retour')->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }
}
