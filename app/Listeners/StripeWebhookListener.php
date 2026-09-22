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
            }
        } catch (\Throwable $e) {
            Log::warning("Webhook $type : ".$e->getMessage());
        }
    }

    private function sessionCompleted(array $s, array $meta): void
    {
        $pi = $s['payment_intent'] ?? null;

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
