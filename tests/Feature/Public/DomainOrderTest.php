<?php

namespace Tests\Feature\Public;

use App\Models\DomainRequest;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\HostingerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'T', 'email' => 'client@exemple.fr', 'password' => bcrypt('secret-123')]);
    }

    private function site(array $attrs = []): Site
    {
        return Site::create(array_merge(['slug' => 'bistrot-'.fake()->lexify('????'), 'name' => 'Bistrot', 'sector' => 'restaurant', 'status' => 'paid', 'hosting_plan' => 'pro', 'subscription_status' => 'active', 'source' => 'joow-public', 'user_id' => $this->user->id, 'owner_email' => 'client@exemple.fr', 'site_data' => ['content' => ['hero_title' => 'x']]], $attrs));
    }

    private function hostinger(int $purchases): void
    {
        $this->mock(HostingerClient::class, function ($m) use ($purchases) {
            $m->shouldReceive('enabled')->andReturn(true);
            $m->shouldReceive('prices')->andReturn(['fr' => ['item_id' => 'hostingercom-domain-fr', 'price' => 799]]);
            $m->shouldReceive('search')->andReturnUsing(fn ($q) => [['domain' => $q.'.fr', 'available' => true, 'item_id' => 'hostingercom-domain-fr']]);
            $m->shouldReceive('purchase')->times($purchases)->andReturn(['id' => 'ord_1', 'status' => 'completed']);
            $m->shouldReceive('pointToServer')->andReturn(true);
        });
    }

    public function test_one_included_domain_per_site_then_blocked(): void
    {
        $this->hostinger(1);
        $site = $this->site();
        $this->actingAs($this->user)->postJson(route('domains.order', $site->slug), ['domain' => 'monbistrot.fr'])->assertOk();
        $this->actingAs($this->user)->postJson(route('domains.order', $site->slug), ['domain' => 'autrebistrot.fr'])->assertStatus(422);
        $this->assertSame(1, DomainRequest::where('site_slug', $site->slug)->where('type', 'order')->count());
    }

    public function test_no_included_domain_when_subscription_is_unpaid_or_canceled(): void
    {
        $this->hostinger(0);
        foreach (['past_due', 'canceled'] as $status) {
            $site = $this->site(['subscription_status' => $status]);
            $this->actingAs($this->user)->postJson(route('domains.order', $site->slug), ['domain' => 'monbistrot.fr'])->assertStatus(422);
        }
    }

    public function test_an_order_in_progress_blocks_a_concurrent_second_order(): void
    {
        $this->hostinger(0);
        $site = $this->site();
        $lock = \Illuminate\Support\Facades\Cache::lock("joow:domain-order:{$site->id}", 120);
        $this->assertTrue($lock->get());
        $this->actingAs($this->user)->postJson(route('domains.order', $site->slug), ['domain' => 'monbistrot.fr'])->assertStatus(409);
        $lock->release();
    }

    public function test_liberte_gets_one_domain_too(): void
    {
        $this->hostinger(1);
        $site = $this->site(['hosting_plan' => 'liberte', 'subscription_status' => 'lifetime']);
        $this->actingAs($this->user)->postJson(route('domains.order', $site->slug), ['domain' => 'monbistrot.fr'])->assertOk();
        $this->actingAs($this->user)->postJson(route('domains.order', $site->slug), ['domain' => 'deux.fr'])->assertStatus(422);
    }
}
