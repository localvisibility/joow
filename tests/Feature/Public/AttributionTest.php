<?php

namespace Tests\Feature\Public;

use App\Http\Controllers\AdminController;
use App\Services\MetaConversions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttributionTest extends TestCase
{
    public function test_campaign_reads_the_session_attribution_shape(): void
    {
        $this->assertSame('joow-lancement', AdminController::campaign(['first' => ['utm_source' => 'meta'], 'last' => ['utm_campaign' => 'joow-lancement']]));
        $this->assertSame('Meta (sans UTM)', AdminController::campaign(['last' => ['fbclid' => 'abc']]));
        $this->assertSame('direct', AdminController::campaign(null));
    }

    public function test_deferred_server_event_needs_consent_and_keeps_event_id(): void
    {
        config(['services.meta.pixel_id' => '123', 'services.meta.access_token' => 't']);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        app(MetaConversions::class)->deferred('SiteGenerated', ['consent' => false, 'ip' => '1.2.3.4'], [], 'a@b.fr', 'sitegen-x');
        Http::assertNothingSent();

        app(MetaConversions::class)->deferred('SiteGenerated', ['consent' => true, 'ip' => '1.2.3.4', 'fbc' => 'fb.1.1.abc'], [], 'a@b.fr', 'sitegen-x');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r['data'], '"event_id":"sitegen-x"') && str_contains($r['data'], 'fb.1.1.abc'));
    }
}
