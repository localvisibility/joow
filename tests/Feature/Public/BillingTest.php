<?php

namespace Tests\Feature\Public;

use App\Listeners\StripeWebhookListener;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use App\Services\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function site(array $attrs = []): Site
    {
        return Site::create(array_merge(['slug' => 'demo-'.fake()->lexify('????'), 'name' => 'Démo', 'sector' => 'service', 'status' => 'preview', 'source' => 'joow-public', 'owner_email' => 'client@exemple.fr', 'site_data' => ['content' => ['hero_title' => 'x']]], $attrs));
    }

    private function stripeInvoice(string $id = 'in_test_1', int $total = 4680, int $tax = 780, array $extra = []): array
    {
        return $extra + ['id' => $id, 'number' => 'ABCD-0001', 'total' => $total, 'tax' => $tax, 'amount_paid' => $total, 'currency' => 'eur', 'customer_email' => 'client@exemple.fr', 'customer_name' => 'Léa Martin', 'subscription' => 'sub_1', 'invoice_pdf' => 'https://pay.stripe.com/invoice/test.pdf', 'created' => 1793100000, 'lines' => ['data' => [['period' => ['start' => 1793100000, 'end' => 1795692000]]]]];
    }

    public function test_a_paid_stripe_invoice_becomes_a_joow_invoice_with_ht_tva_and_sequential_number(): void
    {
        $site = $this->site(['hosting_plan' => 'pro']);
        $inv = app(Billing::class)->recordFromStripeInvoice($this->stripeInvoice(), $site, 'pro');
        $this->assertSame('JOOW-2026-00001', $inv->invoice_number);
        $this->assertSame(39.0, (float) $inv->price_ht);
        $this->assertSame(20.0, (float) $inv->tva_rate);
        $this->assertSame(46.8, $inv->amount_ttc);
        $this->assertStringContainsString('Formule Pro', $inv->module_name);
        $this->assertStringContainsString('GMD COM', $inv->html);
        $this->assertStringContainsString('FR20 882 334 733', $inv->html);
        $this->assertStringContainsString('test.pdf', $inv->html);
        // Idempotent, puis numéro suivant
        $this->assertSame($inv->id, app(Billing::class)->recordFromStripeInvoice($this->stripeInvoice(), $site, 'pro')->id);
        $this->assertSame('JOOW-2026-00002', app(Billing::class)->recordFromStripeInvoice($this->stripeInvoice('in_test_2'), $site, 'pro')->invoice_number);
        $this->assertNull(app(Billing::class)->recordFromStripeInvoice($this->stripeInvoice('in_zero', 0, 0), $site, 'pro'), 'facture à 0 € ignorée');
    }

    public function test_the_owner_sees_the_invoice_in_their_space_and_can_open_it(): void
    {
        $user = User::create(['name' => 'Léa', 'email' => 'client@exemple.fr', 'password' => bcrypt('secret-123')]);
        $site = $this->site(['user_id' => $user->id, 'hosting_plan' => 'pro']);
        $inv = app(Billing::class)->recordFromStripeInvoice($this->stripeInvoice(), $site, 'pro');
        $this->actingAs($user)->get(route('invoices.index'))->assertOk()->assertSee('JOOW-2026-00001');
        $this->actingAs($user)->get(route('invoices.show', $inv))->assertOk()->assertSee('Facture JOOW-2026-00001')->assertSee('46,80');
        $other = User::create(['name' => 'X', 'email' => 'autre@exemple.fr', 'password' => bcrypt('secret-123')]);
        $this->actingAs($other)->get(route('invoices.show', $inv))->assertForbidden();
    }

    public function test_checkout_webhook_marks_the_site_paid_and_sends_the_live_email_even_without_return(): void
    {
        Mail::fake();
        $site = $this->site();
        $payload = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid', 'metadata' => ['site_slug' => $site->slug, 'plan' => 'pro']]]];
        app(StripeWebhookListener::class)->handle(new WebhookReceived($payload));
        $site->refresh();
        $this->assertSame('paid', $site->status);
        $this->assertSame('pro', $site->hosting_plan);
        $this->assertNotNull($site->user_id, 'compte propriétaire créé et rattaché');
        $this->assertNotEmpty($site->site_data['emails']['live_at'] ?? null);
        Mail::assertSent(\App\Mail\JoowNotification::class, fn ($m) => str_contains($m->subjectLine, 'en ligne'));
        // Rejouer le webhook ne change rien
        app(StripeWebhookListener::class)->handle(new WebhookReceived($payload));
        Mail::assertSent(\App\Mail\JoowNotification::class, 1);
    }

    public function test_subscription_invoice_webhook_records_a_joow_invoice_for_the_customer(): void
    {
        $user = User::create(['name' => 'Léa', 'email' => 'client@exemple.fr', 'password' => bcrypt('secret-123')]);
        $user->forceFill(['stripe_id' => 'cus_1'])->save();
        $site = $this->site(['user_id' => $user->id, 'status' => 'paid', 'hosting_plan' => 'pro', 'subscription_status' => 'active']);
        app(StripeWebhookListener::class)->handle(new WebhookReceived(['type' => 'invoice.payment_succeeded', 'data' => ['object' => $this->stripeInvoice('in_renew', 4680, 780, ['customer' => 'cus_1'])]]));
        $this->assertSame(1, Invoice::where('payment_id', 'in_renew')->where('site_slug', $site->slug)->count());
    }
}
