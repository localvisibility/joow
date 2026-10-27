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

    public function test_welcome_is_sent_once_per_site_and_after_a_magic_login(): void
    {
        $site = $this->site();
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr', [$site->slug]);
        // Connexion par lien (ex. après « Continuer avec Google ») : l'email de bienvenue part
        $this->get(app(Accounts::class)->magicLink($user, '/dashboard'))->assertRedirect('/dashboard');
        Mail::assertSent(\App\Mail\JoowNotification::class, 1);
        $this->assertNotNull($site->fresh()->site_data['emails']['welcome_at']);
        // Un second passage n'envoie rien de plus
        $this->assertFalse(app(Accounts::class)->welcome($user, $site->fresh()));
        Mail::assertSent(\App\Mail\JoowNotification::class, 1);
    }

    public function test_live_email_after_payment_lists_next_steps(): void
    {
        $site = $this->site(['status' => 'paid', 'paid_at' => now(), 'site_data' => ['images' => ['hero' => 'https://x/hero.jpg']]]);
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr', [$site->slug]);
        $this->assertTrue(app(Accounts::class)->live($user, $site->fresh(), 'pro'));
        Mail::assertSent(\App\Mail\JoowNotification::class, function (\App\Mail\JoowNotification $m) use ($site) {
            $html = $m->render();

            return str_contains($m->subjectLine, 'en ligne') && str_contains($html, 'fiche Google') && str_contains($html, 'https://x/hero.jpg') && str_contains($html, $site->slug.'.joow.fr');
        });
        $this->assertFalse(app(Accounts::class)->live($user, $site->fresh(), 'pro'));
    }

    public function test_nurture_sequence_per_site_and_abandoned_checkout_reminder(): void
    {
        $site = $this->site(['created_at' => now()->subDays(4)]);
        [$user] = app(Accounts::class)->ensure('pro@exemple.fr', [$site->slug]);
        $paidSite = $this->site(['status' => 'paid', 'paid_at' => now()]);
        $otherPreview = $this->site(['created_at' => now()->subDays(4)]);
        [$client] = app(Accounts::class)->ensure('client@exemple.fr', [$paidSite->slug, $otherPreview->slug]);
        $optSite = $this->site(['created_at' => now()->subDays(4)]);
        [$opt] = app(Accounts::class)->ensure('opt@exemple.fr', [$optSite->slug]);
        $opt->forceFill(['marketing_optout_at' => now()])->save();

        config(['services.nurture.enabled' => false]);
        $this->artisan('emails:nurture')->assertSuccessful();
        Mail::assertNothingSent();

        config(['services.nurture.enabled' => true]);
        $this->artisan('emails:nurture')->assertSuccessful();
        Mail::assertSent(\App\Mail\JoowNotification::class, fn (\App\Mail\JoowNotification $m) => $m->hasTo('pro@exemple.fr') && str_contains($m->subjectLine, 'Trois retouches'));
        Mail::assertNotSent(\App\Mail\JoowNotification::class, fn (\App\Mail\JoowNotification $m) => $m->hasTo('client@exemple.fr') || $m->hasTo('opt@exemple.fr'));
        $this->assertArrayHasKey('nurture_j1', $site->fresh()->site_data['emails']);

        // Même jour : rien de plus. Le lendemain : J+3.
        Mail::fake();
        $this->artisan('emails:nurture')->assertSuccessful();
        Mail::assertNothingSent();
        $this->travel(1)->days();
        $this->artisan('emails:nurture')->assertSuccessful();
        Mail::assertSent(\App\Mail\JoowNotification::class, fn (\App\Mail\JoowNotification $m) => $m->hasTo('pro@exemple.fr') && str_contains($m->subjectLine, 'modules'));

        // Paiement abandonné : prioritaire, une seule fois
        $this->travel(1)->days();
        app(Accounts::class)->checkoutStarted($site->fresh(), 'liberte');
        $this->travel(2)->hours();
        Mail::fake();
        $this->artisan('emails:nurture')->assertSuccessful();
        Mail::assertSent(\App\Mail\JoowNotification::class, function (\App\Mail\JoowNotification $m) {
            return $m->hasTo('pro@exemple.fr') && str_contains($m->subjectLine, 'une étape') && str_contains($m->render(), '349');
        });
        $this->assertArrayHasKey('checkout_reminder_at', $site->fresh()->site_data['emails']);
    }

    public function test_leaving_an_email_signs_in_a_brand_new_account_but_never_an_existing_one(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $site = $this->site();
        $this->withSession(['joow_sites' => [$site->slug]])->postJson(route('public.site.email', $site->slug), ['email' => 'nouvelle@exemple.fr'])->assertOk();
        $this->assertAuthenticated();
        $this->assertSame('nouvelle@exemple.fr', auth()->user()->email);

        auth()->logout();
        $existing = \App\Models\User::create(['name' => 'Déjà', 'email' => 'deja-la@exemple.fr', 'password' => bcrypt('secret-123')]);
        $site2 = $this->site();
        $this->withSession(['joow_sites' => [$site2->slug]])->postJson(route('public.site.email', $site2->slug), ['email' => 'deja-la@exemple.fr'])->assertOk();
        $this->assertGuest();
    }
}
