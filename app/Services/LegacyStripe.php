<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Formules historiques facturées par l'ancien compte Stripe de Local Visibility (ex. 19 €/mois) :
 * l'abonnement continue là-bas ; Joow affiche la formule dans l'espace du client, rapatrie ses
 * factures (PDF Stripe) et lui donne accès au portail de l'ancien compte pour sa carte bancaire.
 */
class LegacyStripe
{
    public function enabled(): bool
    {
        return (string) config('services.stripe_lv.secret') !== '';
    }

    private function api(string $path, array $query = [], string $method = 'get'): array
    {
        $req = Http::withToken((string) config('services.stripe_lv.secret'))->timeout(20)->asForm();
        $res = $method === 'post' ? $req->post('https://api.stripe.com/v1/'.$path, $query) : $req->get('https://api.stripe.com/v1/'.$path, $query);
        if (! $res->successful()) {
            throw new \RuntimeException('Stripe (ancien compte) '.$path.' : HTTP '.$res->status().' '.($res->json('error.message') ?? ''));
        }

        return (array) $res->json();
    }

    /** Synchronise la formule et les factures d'un site ; renvoie un résumé. */
    public function sync(Site $site, ?string $email = null): array
    {
        $email = strtolower(trim($email ?: (string) $site->owner_email));
        if ($email === '') {
            return ['error' => 'aucune adresse e-mail'];
        }
        $customers = $this->api('customers', ['email' => $email, 'limit' => 10])['data'] ?? [];
        if (! $customers) {
            return ['error' => "aucun client Stripe pour $email sur l'ancien compte"];
        }

        $invoices = 0;
        $best = null;
        foreach ($customers as $c) {
            foreach ($this->api('subscriptions', ['customer' => $c['id'], 'status' => 'all', 'limit' => 20])['data'] ?? [] as $sub) {
                // L'abonnement actif d'abord, sinon le plus récent
                $rank = in_array($sub['status'], ['active', 'trialing', 'past_due'], true) ? 1 : 0;
                if (! $best || $rank > $best[0] || ($rank === $best[0] && $sub['created'] > $best[1]['created'])) {
                    $best = [$rank, $sub, $c['id']];
                }
            }
            $after = null;
            do {
                $page = $this->api('invoices', array_filter(['customer' => $c['id'], 'limit' => 100, 'starting_after' => $after]));
                foreach ($page['data'] ?? [] as $inv) {
                    if (in_array($inv['status'], ['draft', 'void'], true) || (int) $inv['total'] <= 0) {
                        continue;
                    }
                    $ht = (int) ($inv['total_excluding_tax'] ?? $inv['subtotal'] ?? $inv['total']) / 100;
                    $tax = (int) ($inv['tax'] ?? 0) / 100;
                    Invoice::updateOrCreate(['payment_id' => $inv['id']], [
                        'invoice_number' => 'LV-'.($inv['number'] ?? $inv['id']),
                        'site_slug' => $site->slug, 'client_email' => $email, 'client_name' => $inv['customer_name'] ?? null,
                        'module_name' => 'Hébergement du site · formule historique Local Visibility'.($inv['lines']['data'][0]['description'] ?? '' ? ' ('.$inv['lines']['data'][0]['description'].')' : ''),
                        'price_ht' => $ht, 'tva_rate' => $ht > 0 && $tax > 0 ? round($tax / $ht * 100, 2) : 0,
                        'currency' => strtoupper($inv['currency'] ?? 'eur'), 'status' => $inv['status'] === 'paid' ? 'paid' : 'pending',
                        'issued_at' => Carbon::createFromTimestamp((int) $inv['created']), 'external_url' => $inv['invoice_pdf'] ?? $inv['hosted_invoice_url'] ?? null,
                    ]);
                    $invoices++;
                    $after = $inv['id'];
                }
                $after = ($page['has_more'] ?? false) ? (end($page['data'])['id'] ?? null) : null;
            } while ($after);
        }

        $plan = null;
        if ($best) {
            [, $sub, $customer] = $best;
            $price = $sub['items']['data'][0]['price'] ?? [];
            $plan = [
                'label' => 'Formule historique', 'provider' => 'Local Visibility', 'customer' => $customer,
                'amount' => isset($price['unit_amount']) ? round($price['unit_amount'] / 100, 2) : null,
                'interval' => ($price['recurring']['interval'] ?? 'month') === 'year' ? 'an' : 'mois',
                'tax_included' => ($price['tax_behavior'] ?? '') === 'inclusive',
                'status' => $sub['status'],
                'period_end' => isset($sub['current_period_end']) ? Carbon::createFromTimestamp((int) $sub['current_period_end'])->toDateString()
                    : (isset($sub['items']['data'][0]['current_period_end']) ? Carbon::createFromTimestamp((int) $sub['items']['data'][0]['current_period_end'])->toDateString() : null),
                'cancel_at_end' => (bool) ($sub['cancel_at_period_end'] ?? false),
                'synced_at' => now()->toIso8601String(),
            ];
            $d = $site->site_data ?? [];
            $d['legacy_plan'] = $plan;
            $site->forceFill([
                'site_data' => $d, 'hosting_plan' => 'historique', 'subscription_status' => $sub['status'],
                'subscription_period_end' => $plan['period_end'],
            ])->save();
        }

        return ['invoices' => $invoices, 'plan' => $plan];
    }

    /** Lien vers le portail de facturation de l'ancien compte (carte bancaire, factures, résiliation). */
    public function portal(Site $site, string $returnUrl): ?string
    {
        $customer = $site->site_data['legacy_plan']['customer'] ?? null;
        if (! $customer || ! $this->enabled()) {
            return null;
        }
        try {
            return $this->api('billing_portal/sessions', ['customer' => $customer, 'return_url' => $returnUrl], 'post')['url'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Portail Stripe (ancien compte) : '.$e->getMessage());

            return null;
        }
    }
}
