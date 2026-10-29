<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Services\Domains\DomainManager;
use App\Services\Domains\HostingerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainFollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bought_domain_waits_for_registry_then_points_dns_itself(): void
    {
        config(['joow.domains.server_ip' => '203.0.113.9']);
        $site = Site::create(['slug' => 'ienova', 'name' => 'Ienova', 'sector' => 'service', 'status' => 'published', 'source' => 'joow-public', 'site_data' => [],
            'custom_domain' => 'ienova-test.fr', 'domain_source' => 'hostinger', 'domain_status' => 'error', 'domain_error' => 'Le DNS ne pointe pas encore']);

        Http::fake(['rdap.nic.fr/*' => Http::sequence()->push(['errorCode' => 404], 404)->push(['ldhName' => 'ienova-test.fr'])]);
        $this->mock(HostingerClient::class, fn ($m) => $m->shouldNotReceive('pointToServer'));
        $site = app(DomainManager::class)->checkDns($site);
        $this->assertSame('registering', $site->domain_status);
        $this->assertNull($site->domain_error);

        Cache::flush();
        $this->mock(HostingerClient::class, fn ($m) => $m->shouldReceive('pointToServer')->once()->with('ienova-test.fr', '203.0.113.9', 'ienova')->andReturn(true));
        $site = app(DomainManager::class)->checkDns($site->fresh());
        $this->assertSame('pending_dns', $site->domain_status);
    }
}
