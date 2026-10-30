<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DomainSuggest;
use App\Services\Domains\HostingerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HotLeadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_candidates_use_the_main_part_of_the_name(): void
    {
        $this->assertSame('provencedrive', DomainSuggest::candidates('PROVENCE DRIVE | Taxis conventionnés & Van 7 places')[0]);
        $this->assertSame('bistrotsaugecitron', DomainSuggest::candidates('Le Bistrot Sauge & Citron')[1]);
    }

    public function test_available_domain_is_exposed_before_payment(): void
    {
        $this->mock(HostingerClient::class, function ($m) {
            $m->shouldReceive('enabled')->andReturn(true);
            $m->shouldReceive('search')->andReturn([['domain' => 'provencedrive.com', 'tld' => 'com', 'available' => true], ['domain' => 'provencedrive.fr', 'tld' => 'fr', 'available' => true]]);
        });
        Site::create(['slug' => 'provence-drive-ab12', 'name' => 'PROVENCE DRIVE | Taxis', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'site_data' => []]);
        $this->getJson('/api/site/provence-drive-ab12/domain')->assertOk()->assertJson(['domain' => 'provencedrive.fr', 'available' => true]);
    }

    public function test_admin_is_alerted_once_per_day_when_a_prospect_opens_the_studio(): void
    {
        config(['joow.admins' => ['boss@joow.fr'], 'mail.default' => 'array']);
        $sent = [];
        Event::listen(MessageSending::class, function ($e) use (&$sent) { $sent[] = $e->message->getSubject(); });
        $owner = User::factory()->create(['email' => 'contact@provencedrive.fr']);
        Site::create(['slug' => 'provence-drive-cd34', 'name' => 'Provence Drive', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'phone' => '06 00 00 00 00',
            'owner_email' => 'contact@provencedrive.fr', 'user_id' => $owner->id, 'site_data' => ['content' => ['hero_title' => 'x']]]);

        $this->actingAs($owner)->get('/sites/provence-drive-cd34/editeur');
        $this->travel(15)->minutes();
        $this->actingAs($owner)->get('/sites/provence-drive-cd34/editeur');

        $alerts = array_values(array_filter($sent, fn ($s) => str_contains((string) $s, 'Studio')));
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Provence Drive', $alerts[0]);
    }
}
