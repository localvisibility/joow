<?php

namespace Tests\Feature\Public;

use App\Listeners\StripeWebhookListener;
use App\Models\LandingPage;
use App\Models\Site;
use App\Models\User;
use App\Services\Ads\AbTest;
use App\Services\Ads\AdsStudio;
use App\Services\Ads\LandingPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class JoowAdsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sites_path' => sys_get_temp_dir().'/joow-ads-'.uniqid()]);
        $this->owner = User::factory()->create(['email' => 'client@exemple.fr']);
        $this->site = Site::create(['slug' => 'plombier-ab12', 'name' => 'Plomberie Dupont', 'sector' => 'plombier', 'city' => 'Lyon', 'status' => 'published', 'hosting_plan' => 'pro',
            'source' => 'joow-public', 'user_id' => $this->owner->id, 'owner_email' => 'client@exemple.fr', 'phone' => '06 11 22 33 44', 'rating' => 4.8, 'reviews_count' => 120,
            'site_data' => ['content' => ['hero_title' => 'Votre plombier à Lyon'], 'images' => ['hero' => 'https://app.joow.fr/media/x/hero.jpg']]]);
    }

    private function activate(): void
    {
        $d = $this->site->site_data;
        $d['addons']['ads'] = ['active' => true];
        $this->site->forceFill(['site_data' => $d])->save();
    }

    public function test_module_is_locked_until_subscribed(): void
    {
        $this->actingAs($this->owner)->get('/publicite')->assertOk()->assertInertia(fn ($p) => $p->component('Ads')->where('active', false)->where('online', true));
        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pack', ['goal' => 'calls'])->assertStatus(402);
    }

    public function test_pack_generation_and_landing_from_pack(): void
    {
        $this->activate();
        $this->mock(AdsStudio::class, function ($m) {
            $m->shouldReceive('pack')->andReturn(['strategy' => ['summary' => 'S'], 'meta_ads' => [['hook' => 'H', 'primary_text' => 'T', 'headline' => 'Hd', 'image' => null]],
                'landing' => ['title' => 'Dépannage', 'path' => 'depannage', 'variants' => [['headline' => 'A1', 'cta' => 'Appeler'], ['headline' => 'B1', 'cta' => 'Appeler']]]]);
        });
        $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pack', ['goal' => 'calls', 'offer' => 'Devis gratuit'])->assertOk()->assertJsonPath('pack.strategy.summary', 'S');

        $page = $this->actingAs($this->owner)->postJson('/publicite/plombier-ab12/pages', ['goal' => 'calls', 'title' => 'Dépannage', 'path' => 'depannage',
            'variants' => [['headline' => 'A1'], ['headline' => 'B1']]])->assertOk()->json('page');
        $this->assertSame(['A', 'B'], array_column($page['variants'], 'key'));
        $this->assertSame('https://plombier-ab12.joow.fr/o/depannage/', $page['url']);

        // Publication : fichier statique avec les deux variantes
        $this->actingAs($this->owner)->postJson("/publicite/plombier-ab12/pages/{$page['id']}/action", ['action' => 'publish'])->assertOk()->assertJsonPath('page.status', 'live');
        $html = file_get_contents(config('services.sites_path').'/plombier-ab12/o/depannage/index.html');
        $this->assertStringContainsString('data-v="A"', $html);
        $this->assertStringContainsString('data-v="B"', $html);
        $this->assertStringContainsString('tel:0611223344', $html);
    }

    public function test_hits_and_ab_verdict(): void
    {
        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'offre', 'title' => 'Offre', 'goal' => 'leads', 'status' => 'live',
            'variants' => [['key' => 'A', 'headline' => 'a'], ['key' => 'B', 'headline' => 'b']]]);
        $this->postJson("/api/site/plombier-ab12/lp/{$lp->id}/hit", ['v' => 'A', 't' => 'view'])->assertOk();
        $this->postJson("/api/site/plombier-ab12/lp/{$lp->id}/hit", ['v' => 'A', 't' => 'call'])->assertOk();
        $r = app(AbTest::class)->results($lp);
        $this->assertSame(1, $r['variants']['A']['views']);
        $this->assertSame(1, $r['variants']['A']['calls']);
        $this->assertSame(1, $r['variants']['A']['conversions']);
        $this->assertSame('collecting', $r['verdict']['status']);

        $ab = app(AbTest::class);
        $this->assertSame('winner', $ab->verdict(['A' => ['views' => 1000, 'conversions' => 30], 'B' => ['views' => 1000, 'conversions' => 70]])['status']);
        $this->assertSame('B', $ab->verdict(['A' => ['views' => 1000, 'conversions' => 30], 'B' => ['views' => 1000, 'conversions' => 70]])['winner']);
        $this->assertSame('tie', $ab->verdict(['A' => ['views' => 200, 'conversions' => 10], 'B' => ['views' => 200, 'conversions' => 12]])['status']);
    }

    public function test_addon_webhooks_never_touch_the_hosting_subscription(): void
    {
        $listener = app(StripeWebhookListener::class);
        $listener->handle(new WebhookReceived(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_ads', 'payment_status' => 'paid', 'subscription' => 'sub_ads', 'metadata' => ['type' => 'addon_ads', 'site_slug' => 'plombier-ab12']]]]));
        $this->assertTrue($this->site->fresh()->adsActive());

        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'offre', 'title' => 'Offre', 'goal' => 'leads', 'status' => 'live', 'variants' => [['key' => 'A', 'headline' => 'a']]]);
        $listener->handle(new WebhookReceived(['type' => 'customer.subscription.deleted', 'data' => ['object' => [
            'id' => 'sub_ads', 'customer' => 'cus_1', 'metadata' => ['type' => 'addon_ads', 'site_slug' => 'plombier-ab12']]]]));

        $site = $this->site->fresh();
        $this->assertFalse($site->adsActive());
        $this->assertSame('published', $site->status);
        $this->assertNotSame('canceled', $site->subscription_status); // l'hébergement n'est pas résilié
        $this->assertSame('paused', $lp->fresh()->status);
    }

    public function test_preview_renders_for_owner(): void
    {
        $lp = LandingPage::create(['site_id' => $this->site->id, 'site_slug' => 'plombier-ab12', 'path' => 'offre', 'title' => 'Offre', 'goal' => 'calls', 'status' => 'draft',
            'variants' => [['key' => 'A', 'headline' => 'Dépannage en 1 h', 'bullets' => ['7j/7']]]]);
        $this->actingAs($this->owner)->get("/publicite/plombier-ab12/pages/{$lp->id}/apercu")->assertOk()->assertSee('Dépannage en 1 h');
        $this->assertStringContainsString('Plomberie Dupont', app(LandingPublisher::class)->html($lp->setRelation('site', $this->site)));
    }
}
