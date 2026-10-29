<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\Domains\HostingerClient;
use App\Services\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlanSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function proSite(User $user): Site
    {
        return Site::create(['slug' => 'ienova', 'name' => 'Ienova', 'sector' => 'service', 'status' => 'published', 'hosting_plan' => 'pro', 'subscription_status' => 'active',
            'source' => 'joow-public', 'user_id' => $user->id, 'owner_email' => $user->email, 'paid_at' => now(), 'site_data' => ['content' => ['hero_title' => 'x']]]);
    }

    public function test_an_online_pro_site_cannot_take_a_second_pro_subscription(): void
    {
        $user = User::factory()->create();
        $this->proSite($user);
        $this->actingAs($user)->from('/hebergement')->post('/site/ienova/checkout', ['email' => $user->email, 'plan' => 'pro'])
            ->assertRedirect('/hebergement')->assertSessionHasErrors('email');
    }

    public function test_paying_liberte_switches_a_pro_site_without_taking_it_offline(): void
    {
        $user = User::factory()->create();
        $site = $this->proSite($user);

        $this->assertTrue(app(Subscription::class)->switchToLiberte($site));
        $site->refresh();
        $this->assertSame('liberte', $site->hosting_plan);
        $this->assertSame('lifetime', $site->subscription_status);
        $this->assertSame('published', $site->status);

        // Le webhook et le retour de paiement passent tous deux : aucun double traitement
        $this->assertFalse(app(Subscription::class)->fulfil($site, 'liberte', 'cs_1'));
        $this->assertSame('liberte', $site->fresh()->hosting_plan);
    }

    public function test_hosting_page_lists_online_sites_with_their_plan(): void
    {
        $user = User::factory()->create();
        $this->proSite($user);
        $this->actingAs($user)->get('/hebergement')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Hosting')->where('sites.0.online', true)->where('sites.0.plan', 'pro')->where('sites.0.sub', 'active'));
    }

    public function test_purchase_uses_a_whois_contact_for_the_domain_extension(): void
    {
        config(['services.hostinger.token' => 't']);
        Http::fake([
            'developers.hostinger.com/api/domains/v1/whois?tld=fr' => Http::response([]),
            'developers.hostinger.com/api/domains/v1/whois' => fn (HttpRequest $r) => $r->method() === 'POST'
                ? Http::response(['id' => 99, 'tld' => 'fr'])
                : Http::response([['id' => 7, 'tld' => 'com', 'country' => 'FR', 'entity_type' => 'organization', 'is_default' => true, 'whois_details' => ['company_name' => 'GMD COM']]]),
            'developers.hostinger.com/api/domains/v1/portfolio' => Http::response(['id' => 'ord_1', 'status' => 'completed']),
        ]);

        app(HostingerClient::class)->purchase('ienova.fr', 'hostingercom-domain-fr');

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/whois') && $r['tld'] === 'fr' && $r['whois_details']['company_name'] === 'GMD COM');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/portfolio') && $r['domain_contacts']['owner_id'] === 99);
    }
}
