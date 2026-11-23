<?php

namespace Tests\Feature\Public;

use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LegacyStripeTest extends TestCase
{
    use RefreshDatabase;

    public function test_historic_plan_and_invoices_come_from_the_old_stripe_account(): void
    {
        $this->withoutVite();
        config(['services.stripe_lv.secret' => 'rk_test_x']);
        Http::fake([
            'api.stripe.com/v1/customers*' => Http::response(['data' => [['id' => 'cus_1']]]),
            'api.stripe.com/v1/subscriptions*' => Http::response(['data' => [['id' => 'sub_1', 'status' => 'active', 'created' => 1700000000, 'current_period_end' => 1798000000, 'cancel_at_period_end' => false,
                'items' => ['data' => [['price' => ['unit_amount' => 1900, 'recurring' => ['interval' => 'month']]]]]]]]),
            'api.stripe.com/v1/invoices*' => Http::response(['has_more' => false, 'data' => [
                ['id' => 'in_1', 'number' => 'AB12-0007', 'status' => 'paid', 'total' => 1900, 'total_excluding_tax' => 1583, 'tax' => 317, 'currency' => 'eur', 'created' => 1760000000, 'invoice_pdf' => 'https://pay.stripe.com/invoice/x/pdf', 'lines' => ['data' => [['description' => 'Hébergement']]]],
                ['id' => 'in_2', 'status' => 'draft', 'total' => 1900, 'currency' => 'eur', 'created' => 1761000000],
            ]]),
            'api.stripe.com/v1/billing_portal/sessions' => Http::response(['url' => 'https://billing.stripe.com/p/session/x']),
        ]);
        $user = User::factory()->create(['email' => 'proprio@yahoo.fr']);
        $site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'hebergement', 'status' => 'paid', 'source' => 'legacy', 'owner_email' => 'proprio@yahoo.fr', 'site_data' => []]);

        $this->artisan('lv:stripe', ['site' => 'clos'])->assertSuccessful();
        $this->artisan('lv:stripe', ['site' => 'clos'])->assertSuccessful(); // idempotent

        $site->refresh();
        $this->assertSame('historique', $site->hosting_plan);
        $this->assertEquals(19, $site->site_data['legacy_plan']['amount']);
        $this->assertSame(1, Invoice::count());
        $inv = Invoice::first();
        $this->assertSame('LV-AB12-0007', $inv->invoice_number);
        $this->assertEquals(15.83, (float) $inv->price_ht);

        $this->actingAs($user)->get('/factures')->assertOk()->assertInertia(fn ($p) => $p->where('invoices.0.has_pdf', true));
        $this->actingAs($user)->get(route('invoices.show', $inv->id))->assertRedirect('https://pay.stripe.com/invoice/x/pdf');
        $this->actingAs($user)->get(route('hosting.index'))->assertInertia(fn ($p) => $p->where('sites.0.legacy.amount', 19));
        $this->actingAs($user)->get(route('hosting.legacy-portal', 'clos'))->assertRedirect('https://billing.stripe.com/p/session/x');
    }
}
