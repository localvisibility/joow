<?php

namespace App\Services\Modules;

use App\Models\RoomBooking;
use App\Models\Site;
use App\Services\Notifier;
use App\Services\Payments\StripeConnect;
use Illuminate\Support\Facades\Log;

/**
 * Confirmation d'un séjour : crée la demande d'acompte Stripe si le module Paiement est actif,
 * puis prévient le client. Utilisé par l'espace client et par la réservation instantanée.
 */
class StayConfirmation
{
    public function __construct(private Notifier $notifier, private StripeConnect $connect) {}

    /** Renvoie le lien de paiement de l'acompte, ou null. */
    public function confirm(Site $site, RoomBooking $booking): ?string
    {
        $payUrl = null;
        $pay = $site->module('payment');
        $type = $pay['type'] ?? 'deposit';
        if ($site->moduleEnabled('payment') && in_array($type, ['deposit', 'full'], true) && $booking->total && $booking->payment_status !== 'paid' && $this->connect->ready($site)) {
            $amount = $type === 'full' ? (float) $booking->total : round((float) $booking->total * max(1, min(100, (float) ($pay['deposit_percent'] ?? 30))) / 100, 2);
            try {
                $ret = route('public.pay.return', ['kind' => 'deposit', 'site' => $site->slug]);
                $sess = $this->connect->checkoutForStay($site, $booking->load('room'), $amount, $ret.'&status=ok', $ret.'&status=cancel');
                $booking->update(['deposit_amount' => $amount, 'payment_status' => 'pending', 'payment_url' => $sess['url'], 'stripe_session_id' => $sess['id']]);
                $payUrl = $sess['url'];
            } catch (\Throwable $e) {
                Log::warning('Acompte Stripe : '.$e->getMessage());
            }
        }
        $this->notifier->stayConfirmed($site, $booking->fresh()->load('room'), $payUrl);

        return $payUrl;
    }
}
