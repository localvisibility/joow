<?php

namespace Tests\Feature\Public;

use App\Models\EmailLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_emails_to_joow_accounts_are_logged_with_an_open_pixel(): void
    {
        config(['mail.default' => 'array', 'joow.admins' => ['boss@joow.fr']]);
        User::factory()->create(['email' => 'lea@exemple.fr']);
        Site::create(['slug' => 'bistrot-ef56', 'name' => 'Bistrot', 'sector' => 'restaurant', 'status' => 'preview', 'source' => 'joow-public', 'owner_email' => 'lea@exemple.fr', 'site_data' => []]);

        Mail::html('<html><body><p>Bonjour</p></body></html>', fn ($m) => $m->to('lea@exemple.fr')->subject('Votre site est prêt'));
        Mail::html('<p>Réservation</p>', fn ($m) => $m->to('client-du-resto@exemple.fr')->subject('Réservation confirmée')); // pas un compte Joow

        $this->assertSame(1, EmailLog::count());
        $log = EmailLog::first();
        $sent = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString(route('email.open', $log->id), $sent->getHtmlBody());

        $this->get("/e/o/{$log->id}.gif")->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertNotNull($log->fresh()->opened_at);

        $admin = User::factory()->create(['email' => 'boss@joow.fr']);
        $this->actingAs($admin)->get('/admin?tab=emails')->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.rows.0.subject', 'Votre site est prêt')->where('data.opened_30', 1));
        $this->actingAs($admin)->get('/admin?tab=sites')->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.rows.0.mails.opened', 1)->where('data.rows.0.mails.sent', 1));
    }
}
