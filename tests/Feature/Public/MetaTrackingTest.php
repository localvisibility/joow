<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Services\MetaConversions;
use App\Services\Tracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MetaTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        config(['services.meta.pixel_id' => '123456', 'services.meta.access_token' => 'tok', 'services.meta.test_event_code' => null]);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
    }

    private function site(array $attrs = []): Site
    {
        return Site::create(array_merge(['slug' => 'demo-'.fake()->lexify('????'), 'name' => 'Démo', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'site_data' => ['content' => ['hero_title' => 'x']]], $attrs));
    }

    public function test_lead_is_sent_server_side_with_consent_and_returned_for_the_pixel(): void
    {
        $site = $this->site();
        $res = $this->withCredentials()->withSession(['joow_sites' => [$site->slug]])->withUnencryptedCookie('joow_consent', 'all')->withUnencryptedCookie('_fbp', 'fb.1.1.abc')
            ->postJson(route('public.site.email', $site->slug), ['email' => 'Pro@Exemple.fr'])->assertOk()->json();

        $names = collect($res['track'])->pluck('name')->all();
        $this->assertContains('Lead', $names);
        $this->assertContains('CompleteRegistration', $names);
        $leadId = collect($res['track'])->firstWhere('name', 'Lead')['id'];

        Http::assertSent(function ($req) use ($leadId) {
            if (! str_contains($req->url(), 'graph.facebook.com/v21.0/123456/events')) {
                return false;
            }
            $data = json_decode($req['data'], true);
            $ev = $data[0];

            return $ev['event_name'] === 'Lead' && $ev['event_id'] === $leadId && $ev['user_data']['em'][0] === hash('sha256', 'pro@exemple.fr') && $ev['user_data']['fbp'] === 'fb.1.1.abc' && $ev['action_source'] === 'website' && $req['access_token'] === 'tok';
        });
    }

    public function test_nothing_is_sent_server_side_without_consent(): void
    {
        $site = $this->site();
        $res = $this->withSession(['joow_sites' => [$site->slug]])
            ->postJson(route('public.site.email', $site->slug), ['email' => 'pro@exemple.fr'])->assertOk()->json();
        $this->assertNotEmpty($res['track'], 'le navigateur décide seul (pixel chargé uniquement si consentement)');
        Http::assertNothingSent();
    }

    public function test_attribution_is_captured_in_session_and_stored_on_generated_sites(): void
    {
        $this->get('/?utm_source=meta&utm_campaign=lancement&fbclid=XYZ')->assertOk();
        $attr = session('attribution');
        $this->assertSame('meta', $attr['first']['utm_source']);
        $this->assertSame('lancement', $attr['last']['utm_campaign']);
        $this->assertStringStartsWith('fb.1.', $attr['fbc']);
        $this->assertStringEndsWith('.XYZ', $attr['fbc']);

        // Deuxième visite avec une autre campagne : premier contact conservé, dernier mis à jour
        $this->get('/?utm_source=google&utm_campaign=marque')->assertOk();
        $this->assertSame('meta', session('attribution.first.utm_source'));
        $this->assertSame('google', session('attribution.last.utm_source'));
    }

    public function test_shared_track_prop_is_delivered_once(): void
    {
        $this->startSession();
        Tracking::push('Purchase', 'purchase-1', ['value' => 39]);
        $this->assertSame([['name' => 'Purchase', 'id' => 'purchase-1', 'params' => ['value' => 39]]], Tracking::pull());
        $this->assertSame([], Tracking::pull());
    }

    public function test_test_command_reports_events_received(): void
    {
        $this->artisan('meta:test --code=TEST123')->assertSuccessful()->expectsOutputToContain('events_received');
        Http::assertSent(fn ($req) => ($req['test_event_code'] ?? null) === 'TEST123');
    }

    public function test_google_tag_manager_is_injected_only_when_configured_and_loads_after_consent(): void
    {
        config(['services.gtm.id' => null, 'marketing.host' => 'joow.fr']);
        $this->get('https://joow.fr/')->assertOk()->assertDontSee('googletagmanager');
        config(['services.gtm.id' => 'GTM-TEST123']);
        $html = $this->get('https://joow.fr/')->assertOk()->assertSee('data-gtm="GTM-TEST123"', false)->assertSee("gtag('consent','default'", false)->getContent();
        $this->assertStringContainsString('joow_consent=all', $html);
        $this->get('https://app.joow.fr/')->assertOk()->assertSee("gtm.js?id='+i", false);
    }
}
