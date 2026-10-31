<?php

namespace App\Listeners;

use App\Models\Reservation;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Services\Modules\ReservationAvailability;
use App\Services\Notifier;
use App\Models\SiteStat;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Événements Stripe des paiements Connect (acomptes de séjour, empreintes de table).
 * Cashier vérifie la signature et déclenche WebhookReceived.
 */
class StripeWebhookListener
{
    public function __construct(private Notifier $notifier) {}

    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? '';
        $obj = $event->payload['data']['object'] ?? [];
        $meta = $obj['metadata'] ?? [];

        try {
            if ($type === 'checkout.session.completed') {
                $this->sessionCompleted($obj, $meta);
            } elseif ($type === 'checkout.session.expired') {
                $this->sessionExpired($obj, $meta);
            } elseif ($type === 'invoice.payment_succeeded' && ($ads = $this->adsSite($obj['subscription'] ?? null, (array) ($obj['subscription_details']['metadata'] ?? [])))) {
                // Module Joow Ads : facture du module, sans toucher à l'abonnement d'hébergement
                if ((int) ($obj['amount_paid'] ?? 0) > 0) {
                    app(\App\Services\Billing::class)->recordFromStripeInvoice($obj, $ads, 'ads');
                }
                $this->setAds($ads, true, $obj['subscription'] ?? null);
            } elseif ($type === 'invoice.payment_failed' && $this->adsSite($obj['subscription'] ?? null, (array) ($obj['subscription_details']['metadata'] ?? []))) {
                Log::info('Joow Ads : paiement du module en échec (Stripe relance automatiquement)');
            } elseif ($type === 'customer.subscription.deleted' && ($ads = $this->adsSite($obj['id'] ?? null, (array) ($obj['metadata'] ?? [])))) {
                $this->setAds($ads, false, $obj['id'] ?? null);
            } elseif ($type === 'invoice.payment_succeeded') {
                $this->invoicePaid($obj);
            } elseif ($type === 'invoice.payment_failed') {
                $this->subscriptionSites($obj['customer'] ?? null)->each(fn (Site $s) => $s->subscription_status !== 'lifetime' && $s->forceFill(['subscription_status' => 'past_due'])->save());
            } elseif ($type === 'customer.subscription.deleted') {
                $this->subscriptionSites($obj['customer'] ?? null)->each(function (Site $s) {
                    $s->forceFill(['subscription_status' => 'canceled'])->save();
                    app(\App\Services\Domains\DomainRenewal::class)->onCanceled($s->fresh());
                });
            }
        } catch (\Throwable $e) {
            Log::warning("Webhook $type : ".$e->getMessage());
        }
    }

    private function sessionCompleted(array $s, array $meta): void
    {
        $pi = $s['payment_intent'] ?? null;

        // Mise en ligne (Pro / Liberté) : traitée ici même si la personne a fermé l'onglet avant le retour
        if (! empty($meta['plan']) && ! empty($meta['site_slug']) && in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            if ($site = Site::where('slug', $meta['site_slug'])->first()) {
                app(\App\Services\Subscription::class)->fulfil($site, $meta['plan'] === 'liberte' ? 'liberte' : 'pro', (string) ($s['id'] ?? ''), $s);
            }
        }

        // Module Joow Ads souscrit
        if (($meta['type'] ?? '') === 'addon_ads' && ! empty($meta['site_slug']) && in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            if ($site = Site::where('slug', $meta['site_slug'])->first()) {
                $this->setAds($site, true, $s['subscription'] ?? null);
                app(\App\Services\Billing::class)->recordFromCheckoutSession($s, $site, 'ads');
            }
        }

        // Recharge de crédits IA (pack) : créditer le portefeuille du site (idempotent par session)
        if (($meta['type'] ?? '') === 'credits' && ! empty($meta['site_slug']) && ($s['payment_status'] ?? '') === 'paid') {
            if ($site = Site::where('slug', $meta['site_slug'])->first()) {
                app(\App\Services\AiCredits::class)->grantWallet($site, (int) ($meta['credits'] ?? 0), 'purchase', ['pack' => $meta['pack'] ?? null], (string) ($s['id'] ?? ''));
            }
        }

        if (($meta['type'] ?? '') === 'stay_deposit' && ! empty($meta['booking_id'])) {
            $bk = RoomBooking::find($meta['booking_id']);
            if ($bk && $bk->payment_status !== 'paid') {
                $bk->update(['payment_status' => 'paid', 'stripe_payment_intent_id' => $pi, 'status' => 'confirmed']);
                if ($site = Site::where('slug', $bk->site_slug)->first()) {
                    $this->notifier->stayPaid($site, $bk);
                }
            }
        }

        if (($meta['type'] ?? '') === 'table_hold' && ! empty($meta['reservation_id'])) {
            $r = Reservation::find($meta['reservation_id']);
            if ($r && $r->payment_status !== 'authorized') {
                $r->update(['payment_status' => 'authorized', 'stripe_payment_intent_id' => $pi, 'status' => 'confirmed']);
                if ($site = Site::where('slug', $r->site_slug)->first()) {
                    SiteStat::bump($site->slug, 'reservations');
                    $this->notifier->reservationCreated($site, $r, ReservationAvailability::config($site));
                }
            }
        }
    }

    /** Facture d'abonnement payée (fin d'essai, renouvellement mensuel) : abonnement actif + crédits Pro. */
    private function invoicePaid(array $inv): void
    {
        if (empty($inv['subscription']) || (int) ($inv['amount_paid'] ?? 0) <= 0) {
            return; // facture à 0 € (début d'essai) ou paiement unique : rien à activer
        }
        $sites = $this->subscriptionSites($inv['customer'] ?? null);
        $sites->each(function (Site $site) {
            app(\App\Services\Subscription::class)->activate($site, 'invoice_paid');
        });
        // Facture Joow pour chaque facture Stripe payée (première échéance comme renouvellements)
        app(\App\Services\Billing::class)->recordFromStripeInvoice($inv, $sites->first(), 'pro');
    }

    /**
     * Site concerné si l'abonnement Stripe est celui du module Joow Ads : métadonnées de l'événement,
     * sinon abonnement local Cashier (type « ads:<site> »), sinon métadonnées lues chez Stripe.
     */
    private function adsSite(?string $subId, array $meta = []): ?Site
    {
        if (($meta['type'] ?? '') === 'addon_ads' && ! empty($meta['site_slug'])) {
            return Site::where('slug', $meta['site_slug'])->first();
        }
        if (! $subId) {
            return null;
        }
        $local = \Illuminate\Support\Facades\DB::table('subscriptions')->where('stripe_id', $subId)->value('type');
        if ($local !== null) {
            return str_starts_with((string) $local, 'ads:') ? Site::where('slug', substr($local, 4))->first() : null;
        }
        try {
            $m = (array) \Laravel\Cashier\Cashier::stripe()->subscriptions->retrieve($subId)->metadata?->toArray();

            return ($m['type'] ?? '') === 'addon_ads' && ! empty($m['site_slug']) ? Site::where('slug', $m['site_slug'])->first() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function setAds(Site $site, bool $active, ?string $subId): void
    {
        $d = $site->site_data ?? [];
        $d['addons']['ads'] = array_merge((array) ($d['addons']['ads'] ?? []), ['active' => $active, 'subscription' => $subId ?: ($d['addons']['ads']['subscription'] ?? null), $active ? 'since' : 'ended_at' => ($active ? ($d['addons']['ads']['since'] ?? now()->toIso8601String()) : now()->toIso8601String())]);
        $site->forceFill(['site_data' => $d])->save();
        if (! $active) {
            // Module arrêté : les pages d'offre sont mises en pause (le site reste en ligne)
            foreach (\App\Models\LandingPage::where('site_id', $site->id)->where('status', 'live')->get() as $lp) {
                app(\App\Services\Ads\LandingPublisher::class)->unpublish($lp->setRelation('site', $site));
                $lp->forceFill(['status' => 'paused'])->save();
            }
        }
        Log::info("Joow Ads {$site->slug} : ".($active ? 'actif' : 'arrêté'));
    }

    /** Sites Pro rattachés au client Stripe. */
    private function subscriptionSites(?string $customerId)
    {
        if (! $customerId) {
            return collect();
        }
        $user = \App\Models\User::where('stripe_id', $customerId)->first();

        return $user ? Site::where('user_id', $user->id)->where('hosting_plan', 'pro')->whereIn('status', ['paid', 'published'])->get() : collect();
    }

    private function sessionExpired(array $s, array $meta): void
    {
        if (($meta['type'] ?? '') === 'table_hold' && ! empty($meta['reservation_id'])) {
            $r = Reservation::find($meta['reservation_id']);
            if ($r && $r->payment_status === 'pending') {
                $r->update(['status' => 'cancelled', 'payment_status' => 'none']); // créneau libéré
            }
        }
        if (($meta['type'] ?? '') === 'stay_deposit' && ! empty($meta['booking_id'])) {
            $bk = RoomBooking::find($meta['booking_id']);
            if ($bk && $bk->payment_status === 'pending') {
                $bk->update(['payment_status' => 'failed']);
            }
        }
    }
}
