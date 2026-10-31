<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\SupportBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_answers_and_logs_questions(): void
    {
        $user = User::factory()->create();
        $this->mock(SupportBot::class, fn ($m) => $m->shouldReceive('answer')->once()->andReturn(['answer' => 'Hébergement → Gérer l\'abonnement.', 'escalate' => false]));
        $id = $this->actingAs($user)->postJson('/support/assistant', ['message' => 'Comment résilier ?'])->assertOk()->assertJsonPath('escalate', false)->json('id');
        $this->actingAs($user)->postJson("/support/assistant/$id/avis", ['helpful' => false])->assertOk();
        $this->assertFalse((bool) DB::table('support_questions')->find($id)->helpful);
    }

    public function test_knowledge_and_client_context_are_built(): void
    {
        $user = User::factory()->create();
        Site::create(['slug' => 'bistrot-zz11', 'name' => 'Bistrot', 'sector' => 'restaurant', 'status' => 'published', 'hosting_plan' => 'pro', 'subscription_status' => 'active', 'source' => 'joow-public', 'user_id' => $user->id, 'site_data' => []]);
        $bot = app(SupportBot::class);
        $this->assertStringContainsString('39 € HT par mois', $bot->knowledge());
        $this->assertStringContainsString('Bistrot : en ligne · formule Pro', $bot->context($user));
    }

    public function test_contact_creates_a_ticket_visible_to_admins(): void
    {
        config(['joow.admins' => ['boss@joow.fr'], 'mail.default' => 'array']);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/support/message', ['subject' => 'Facture', 'message' => 'Bonjour, une question'])->assertRedirect()->assertSessionHas('status');
        $admin = User::factory()->create(['email' => 'boss@joow.fr']);
        $this->actingAs($admin)->get('/admin?tab=support')->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.open', 1)->where('data.tickets.0.subject', 'Facture'));
        $this->actingAs($admin)->patch('/admin/support/'.DB::table('support_tickets')->value('id'), ['status' => 'closed'])->assertRedirect();
        $this->assertSame('closed', DB::table('support_tickets')->value('status'));
        $this->actingAs($user)->get('/support')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Support')->has('tickets', 1));
    }
}
