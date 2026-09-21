<?php

namespace App\Services\Payments;

use App\Models\Reservation;
use App\Models\RoomBooking;
use App\Models\Site;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

/**
 * Paiements des clients finaux via Stripe Connect (comptes Express) :
 * acomptes sur séjours (charge à destination) et empreintes bancaires
 * anti no-show sur tables (capture différée).
 */
class StripeConnect
{
    public function configured(): bool
    {
        return (bool) config('cashier.secret');
    }

    private function stripe(): StripeClient
    {
        return Cashier::stripe();
    }

    /** Crée (si besoin) le compte Express du site et renvoie son id. */
    public function ensureAccount(Site $site, string $email): string
    {
        if ($site->stripe_account_id) {
            return $site->stripe_account_id;
        }
        $b = $site->site_data['business'] ?? [];
        $acc = $this->stripe()->accounts->create([
            'type'         => 'express',
            'country'      => 'FR',
            'email'        => $email,
            'capabilities' => ['card_payments' => ['requested' => true], 'transfers' => ['requested' => true]],
            'business_profile' => ['name' => $b['name'] ?? $site->name, 'url' => 'https://'.$site->slug.'.joow.fr'],
            'metadata'     => ['site_slug' => $site->slug],
        ]);
        $site->forceFill(['stripe_account_id' => $acc->id])->save();

        return $acc->id;
    }

    public function onboardingLink(Site $site, string $return, string $refresh): string
    {
        return $this->stripe()->accountLinks->create([
            'account' => $site->stripe_account_id, 'refresh_url' => $refresh, 'return_url' => $return, 'type' => 'account_onboarding',
        ])->url;
    }

    /** Met à jour charges_enabled depuis Stripe. */
    public function refreshStatus(Site $site): bool
    {
        if (! $site->stripe_account_id) {
            return false;
        }
        $acc = $this->stripe()->accounts->retrieve($site->stripe_account_id);
        $ok = (bool) ($acc->charges_enabled ?? false);
        $site->forceFill(['stripe_charges_enabled' => $ok])->save();

        return $ok;
    }

    /** Le site peut encaisser (module actif, compte connecté, clés présentes). */
    public function ready(Site $site): bool
    {
        return $this->configured() && $site->stripe_account_id && $site->stripe_charges_enabled;
    }

    /** Session de paiement d'un acompte de séjour. */
    public function checkoutForStay(Site $site, RoomBooking $bk, float $amount, string $success, string $cancel): array
    {
        $b = $site->site_data['business'] ?? [];
        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $bk->email ?: null,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => 'eur', 'unit_amount' => (int) round($amount * 100), 'product_data' => [
                    'name' => 'Acompte séjour — '.($b['name'] ?? $site->name),
                    'description' => 'Du '.$bk->check_in->format('d/m/Y').' au '.$bk->check_out->format('d/m/Y').' · '.$bk->nights.' nuit(s)'.($bk->room ? ' · '.$bk->room->name : ''),
                ]],
            ]],
            'payment_intent_data' => $this->destination($site, $amount),
            'metadata' => ['type' => 'stay_deposit', 'booking_id' => $bk->id, 'site_slug' => $site->slug],
            'success_url' => $success, 'cancel_url' => $cancel,
        ]);

        return ['id' => $session->id, 'url' => $session->url];
    }

    /** Empreinte bancaire (autorisation, capture différée) pour une table. */
    public function checkoutForHold(Site $site, Reservation $r, float $amount, string $success, string $cancel): array
    {
        $b = $site->site_data['business'] ?? [];
        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $r->email ?: null,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => 'eur', 'unit_amount' => (int) round($amount * 100), 'product_data' => [
                    'name' => 'Empreinte bancaire — '.($b['name'] ?? $site->name),
                    'description' => 'Réservation du '.$r->date->format('d/m/Y').' à '.substr((string) $r->time, 0, 5).' · '.$r->covers.' couvert(s). Débitée uniquement en cas de no-show.',
                ]],
            ]],
            'payment_intent_data' => ['capture_method' => 'manual'] + $this->destination($site, $amount),
            'metadata' => ['type' => 'table_hold', 'reservation_id' => $r->id, 'site_slug' => $site->slug],
            'success_url' => $success, 'cancel_url' => $cancel,
        ]);

        return ['id' => $session->id, 'url' => $session->url];
    }

    public function capture(string $paymentIntentId): void
    {
        $this->stripe()->paymentIntents->capture($paymentIntentId);
    }

    public function release(string $paymentIntentId): void
    {
        try {
            $this->stripe()->paymentIntents->cancel($paymentIntentId);
        } catch (\Throwable) {
            // déjà capturé / annulé / expiré : rien à faire
        }
    }

    private function destination(Site $site, float $amount): array
    {
        $fee = (float) config('services.stripe.platform_fee_percent', 0);
        $out = ['transfer_data' => ['destination' => $site->stripe_account_id]];
        if ($fee > 0) {
            $out['application_fee_amount'] = (int) round($amount * 100 * $fee / 100);
        }

        return $out;
    }
}
