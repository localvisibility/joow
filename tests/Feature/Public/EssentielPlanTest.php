<?php

namespace Tests\Feature\Public;

use App\Models\Site;
use App\Models\User;
use App\Services\AiCredits;
use App\Services\Plans;
use App\Services\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EssentielPlanTest extends TestCase
{
    use RefreshDatabase;

    private function site(array $attrs = []): Site
    {
        return Site::create($attrs + ['slug' => 'boulangerie', 'name' => 'Boulangerie', 'sector' => 'restaurant', 'status' => 'paid', 'hosting_plan' => 'essentiel', 'subscription_status' => 'active', 'owner_email' => 'pro@exemple.fr', 'site_data' => [], 'modules' => ['booking' => true, 'bot' => ['enabled' => true], 'whatsapp' => ['enabled' => true]]]);
    }

    public function test_pro_modules_are_off_on_an_essentiel_site_but_basic_ones_work(): void
    {
        $s = $this->site();
        $this->assertFalse($s->moduleEnabled('bot'));
        $this->assertTrue($s->moduleEnabled('whatsapp'));
        $this->assertTrue($s->moduleEnabled('booking'));
        // En aperçu (pas encore payé), tout reste essayable
        $p = $this->site(['slug' => 'apercu', 'status' => 'preview', 'hosting_plan' => null]);
        $this->assertTrue($p->moduleEnabled('bot'));
        // En Pro : débloqué
        $s->forceFill(['hosting_plan' => 'pro'])->save();
        $this->assertTrue($s->fresh()->moduleEnabled('bot'));
    }

    public function test_enabling_a_pro_module_is_refused_with_an_upgrade_link(): void
    {
        $user = User::factory()->create(['email' => 'pro@exemple.fr']);
        $s = $this->site(['user_id' => $user->id]);
        $this->actingAs($user)->postJson(route('sites.editor.module', $s->slug), ['key' => 'rooms', 'enabled' => true])->assertStatus(402)->assertJsonStructure(['upgrade']);
        $this->actingAs($user)->post(route('modules.update', $s->slug), ['module' => 'payment', 'config' => ['enabled' => true]])->assertSessionHasErrors('module');
        $this->actingAs($user)->postJson(route('sites.editor.module', $s->slug), ['key' => 'popup', 'enabled' => true])->assertOk();
    }

    public function test_monthly_credits_and_prices(): void
    {
        $s = $this->site(['ai_credits_reset_at' => now()->subDay()]);
        app(AiCredits::class)->refresh($s);
        $this->assertSame(20, (int) $s->fresh()->ai_credits_monthly);
        $this->assertSame(19.0, Plans::price('essentiel'));
        $this->assertSame(19, (int) app(Subscription::class)->state($s)['price']);
    }

    public function test_checkout_accepts_the_essentiel_plan(): void
    {
        config(['cashier.secret' => null]);
        $s = $this->site(['status' => 'preview', 'hosting_plan' => null]);
        // Stripe non configuré : message clair, mais la formule est bien acceptée par la validation
        $this->post(route('public.checkout', $s->slug), ['email' => 'pro@exemple.fr', 'plan' => 'essentiel'])->assertSessionHasErrors(['email' => "Le paiement n'est pas encore disponible. Réessayez bientôt."]);
        $this->post(route('public.checkout', $s->slug), ['email' => 'pro@exemple.fr', 'plan' => 'gold'])->assertSessionHasErrors('plan');
    }
}
