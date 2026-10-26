<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\Accounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
    }

    private function site(array $attrs = []): Site
    {
        return Site::create(array_merge(['slug' => 'demo-'.fake()->lexify('????'), 'name' => 'Démo', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'site_data' => ['content' => ['hero_title' => 'x']]], $attrs));
    }

    public function test_ensure_is_idempotent_and_claims_session_sites(): void
    {
        $a = $this->site();
        $b = $this->site(['owner_email' => 'pro@exemple.fr']);
        [$user, $created] = app(Accounts::class)->ensure('PRO@exemple.fr', [$a->slug], 'intake');
        $this->assertTrue($created);
        [$again, $createdAgain] = app(Accounts::class)->ensure('pro@exemple.fr');
        $this->assertFalse($createdAgain);
        $this->assertSame($user->id, $again->id);
        $this->assertSame($user->id, $a->fresh()->user_id, 'site de la session rattaché');
        $this->assertSame($user->id, $b->fresh()->user_id, 'site au même email rattaché');
        $this->assertSame(1, User::count());
    }

    public function test_welcome_email_contains_studio_magic_link_and_set_password_link(): void
    {
        $site = $this->site();
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr', [$site->slug]);
        app(Accounts::class)->welcome($user, $site);
        Mail::assertSent(\App\Mail\JoowNotification::class, function (\App\Mail\JoowNotification $m) use ($site) {
            $html = $m->render();

            return $m->hasTo('pro@exemple.fr') && str_contains($html, '/connexion/lien/') && str_contains($html, 'reset-password') && str_contains($html, 'set=1') && str_contains($html, 'desinscription') && str_contains($html, $site->slug.'.joow.fr');
        });
    }

    public function test_magic_link_from_welcome_logs_in_and_opens_the_studio(): void
    {
        $site = $this->site();
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr', [$site->slug]);
        $link = app(Accounts::class)->magicLink($user, route('sites.editor', $site->slug, absolute: false));
        $this->get($link)->assertRedirect(route('sites.editor', $site->slug, absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_set_password_link_shows_the_choose_page_then_logs_in(): void
    {
        $site = $this->site();
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr', [$site->slug]);
        $link = app(Accounts::class)->setPasswordLink($user);
        $this->get($link)->assertOk()->assertInertia(fn ($p) => $p->component('Auth/ResetPassword')->where('set', true)->where('email', 'pro@exemple.fr'));

        parse_str(parse_url($link, PHP_URL_QUERY), $q);
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->post(route('password.store'), ['token' => $token, 'email' => 'pro@exemple.fr', 'password' => 'MonSuperMotDePasse1!', 'password_confirmation' => 'MonSuperMotDePasse1!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->password_set_at);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unsubscribe_link_stops_marketing_only(): void
    {
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr');
        $this->get(app(Accounts::class)->unsubscribeLink($user))->assertOk()->assertInertia(fn ($p) => $p->component('Public/Unsubscribed'));
        $this->assertNotNull($user->fresh()->marketing_optout_at);
        // Lien non signé refusé
        $this->get('/emails/desinscription/'.$user->id)->assertStatus(403);
    }

    public function test_nurture_is_off_by_default_and_sends_the_right_step_when_forced(): void
    {
        $site = $this->site();
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr', [$site->slug]);
        $user->forceFill(['created_at' => now()->subDays(4)])->save();
        $paidSite = $this->site(['status' => 'paid', 'paid_at' => now()]);
        [$paid] = app(Accounts::class)->ensure('paid@exemple.fr', [$paidSite->slug]);
        $paid->forceFill(['created_at' => now()->subDays(4)])->save();
        $optSite = $this->site();
        [$opt] = app(Accounts::class)->ensure('opt@exemple.fr', [$optSite->slug]);
        $opt->forceFill(['created_at' => now()->subDays(4), 'marketing_optout_at' => now()])->save();

        $this->artisan('emails:nurture')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('emails:nurture --force')->assertSuccessful();
        Mail::assertSent(\App\Mail\JoowNotification::class, fn (\App\Mail\JoowNotification $m) => $m->hasTo('pro@exemple.fr') && str_contains($m->subjectLine, 'Trois choses'));
        Mail::assertNotSent(\App\Mail\JoowNotification::class, fn (\App\Mail\JoowNotification $m) => $m->hasTo('paid@exemple.fr') || $m->hasTo('opt@exemple.fr'));
        $this->assertArrayHasKey('j1', $user->fresh()->nurture);

        // Le lendemain : l'étape suivante (J+3) part, pas J+1 une seconde fois
        Mail::fake();
        $this->artisan('emails:nurture --force')->assertSuccessful();
        Mail::assertSent(\App\Mail\JoowNotification::class, fn (\App\Mail\JoowNotification $m) => $m->hasTo('pro@exemple.fr') && str_contains($m->subjectLine, 'modules'));
        $this->assertArrayHasKey('j3', $user->fresh()->nurture);
    }
}
