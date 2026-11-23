<?php

namespace Tests\Feature\Public;

use App\Mail\JoowNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordForgotTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_french_link_that_sets_a_new_password(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'closdupetitjesus@yahoo.fr', 'name' => 'Rodolphe']);

        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => 'closdupetitjesus@yahoo.fr'])->assertSessionHas('status');

        $url = null;
        Mail::assertSent(JoowNotification::class, function ($m) use (&$url) {
            $html = $m->render();
            if (! $m->hasTo('closdupetitjesus@yahoo.fr') || ! str_contains($html, 'Choisir mon nouveau mot de passe')) {
                return false;
            }
            preg_match('#https?://[^"\s]*reset-password/[^"\s]+#', html_entity_decode($html), $u);
            $url = $u[0] ?? null;

            return $url !== null;
        });
        preg_match('#reset-password/([^?]+)#', $url, $t);

        $this->post(route('password.store'), ['token' => $t[1], 'email' => 'closdupetitjesus@yahoo.fr', 'password' => 'NouveauMdp!2026', 'password_confirmation' => 'NouveauMdp!2026'])
            ->assertRedirect(route('dashboard'));
        $this->assertTrue(Hash::check('NouveauMdp!2026', $user->fresh()->password));
    }

    public function test_unknown_address_gets_the_same_answer(): void
    {
        Mail::fake();
        $this->post(route('password.email'), ['email' => 'personne@exemple.fr'])->assertSessionHas('status')->assertSessionHasNoErrors();
        Mail::assertNothingSent();
    }
}
