<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Services\CreationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CreationGuardTest extends TestCase
{
    use RefreshDatabase;

    private function req(string $ip, string $device): Request
    {
        $r = Request::create('/generer', 'POST', [], [CreationGuard::COOKIE => $device], [], ['REMOTE_ADDR' => $ip]);
        $r->setLaravelSession(app('session.store'));

        return $r;
    }

    private function makeSites(int $n, array $attrs): void
    {
        for ($i = 0; $i < $n; $i++) {
            Site::create(array_merge(['slug' => 's-'.uniqid(), 'name' => 'S', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'site_data' => []], $attrs));
        }
    }

    public function test_same_device_is_limited_to_five_per_day(): void
    {
        $dev = str_repeat('a', 32);
        $this->makeSites(4, ['creator_device' => $dev, 'creator_ip' => '10.0.0.1']);
        $this->assertNull(app(CreationGuard::class)->check($this->req('10.0.0.1', $dev)));
        $this->makeSites(1, ['creator_device' => $dev, 'creator_ip' => '10.0.0.1']);
        $this->assertNotNull(app(CreationGuard::class)->check($this->req('10.0.0.1', $dev)));
    }

    public function test_strangers_sharing_a_mobile_ip_are_not_blocked(): void
    {
        $this->makeSites(10, ['creator_device' => str_repeat('b', 32), 'creator_ip' => '92.184.0.1']);
        $this->assertNull(app(CreationGuard::class)->check($this->req('92.184.0.1', str_repeat('c', 32))));
        $this->makeSites(5, ['creator_device' => str_repeat('d', 32), 'creator_ip' => '92.184.0.1']);
        $this->assertNotNull(app(CreationGuard::class)->check($this->req('92.184.0.1', str_repeat('e', 32)))); // filet : 15 / IP / jour
    }

    public function test_same_email_on_another_device_counts(): void
    {
        $this->makeSites(5, ['creator_device' => str_repeat('f', 32), 'owner_email' => 'agence@exemple.fr']);
        $this->assertNotNull(app(CreationGuard::class)->check($this->req('1.1.1.1', str_repeat('g', 32)), 'Agence@exemple.fr'));
    }

    public function test_internal_proxy_ip_never_blocks_and_blocks_are_logged(): void
    {
        $this->makeSites(30, ['creator_device' => str_repeat('h', 32), 'creator_ip' => '172.18.0.1']);
        $this->assertNull(app(CreationGuard::class)->check($this->req('172.18.0.1', str_repeat('i', 32))));

        $this->makeSites(5, ['creator_device' => str_repeat('j', 32), 'creator_ip' => '92.184.0.2']);
        app(CreationGuard::class)->check($this->req('92.184.0.2', str_repeat('j', 32)), 'x@y.fr');
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('creation_blocks')->where('reason', 'device_email')->count());
    }
}
