<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Services\Notifier;
use App\Services\Payments\StripeConnect;
use Illuminate\Http\Request;

/**
 * Stripe Connect (onboarding du client) + pages publiques d'annulation / retour de paiement.
 */
class PaymentController extends Controller
{
    /** Démarre / reprend l'onboarding Stripe Express du site. */
    public function connect(Request $request, string $slug, StripeConnect $connect)
    {
        $site = $this->ownedSite($request, $slug);
        if (! $connect->configured()) {
            return redirect()->route('modules.index', ['site' => $slug])->with('error', 'Stripe n\'est pas encore configuré sur la plateforme.');
        }
        try {
            $connect->ensureAccount($site, $request->user()->email);
            $url = $connect->onboardingLink($site, route('modules.stripe.return', $slug), route('modules.stripe.connect', $slug));

            return redirect()->away($url);
        } catch (\Throwable $e) {
            return redirect()->route('modules.index', ['site' => $slug])->with('error', 'Stripe : '.$e->getMessage());
        }
    }

    /** Retour d'onboarding : rafraîchit le statut. */
    public function connectReturn(Request $request, string $slug, StripeConnect $connect)
    {
        $site = $this->ownedSite($request, $slug);
        try {
            $connect->refreshStatus($site);
        } catch (\Throwable) {
        }

        return redirect()->route('modules.index', ['site' => $slug]);
    }

    /** Annulation par le client (lien signé dans l'email/SMS). */
    public function cancelReservation(Reservation $reservation, Notifier $notifier, StripeConnect $connect)
    {
        $site = Site::where('slug', $reservation->site_slug)->first();
        if (! in_array($reservation->status, ['cancelled', 'noshow', 'seated'], true)) {
            $reservation->update(['status' => 'cancelled']);
            if ($reservation->stripe_payment_intent_id && $reservation->payment_status === 'authorized') {
                try { $connect->release($reservation->stripe_payment_intent_id); $reservation->update(['payment_status' => 'released']); } catch (\Throwable) {}
            }
            if ($site) {
                $notifier->reservationStatus($site, $reservation, \App\Services\Modules\ReservationAvailability::config($site));
            }
        }

        return view('public.simple', ['icon' => '🗓️', 'title' => 'Réservation annulée', 'message' => 'Votre réservation a bien été annulée. Merci de nous avoir prévenus, à bientôt !', 'url' => $site ? 'https://'.$site->slug.'.joow.fr' : null]);
    }

    public function cancelStay(RoomBooking $booking, Notifier $notifier)
    {
        $site = Site::where('slug', $booking->site_slug)->first();
        if ($booking->status !== 'cancelled') {
            $booking->update(['status' => 'cancelled']);
            if ($site) {
                $notifier->stayCancelled($site, $booking);
            }
        }

        return view('public.simple', ['icon' => '🛏️', 'title' => 'Séjour annulé', 'message' => 'Votre demande de séjour a bien été annulée. Au plaisir de vous accueillir une prochaine fois.', 'url' => $site ? 'https://'.$site->slug.'.joow.fr' : null]);
    }

    /** Retour après Stripe Checkout (le webhook fait foi pour le statut). */
    public function paymentReturn(Request $request)
    {
        $ok = $request->query('status') === 'ok';
        $kind = $request->query('kind') === 'hold' ? 'hold' : 'deposit';
        $site = Site::where('slug', (string) $request->query('site'))->first();

        return view('public.simple', [
            'icon'    => $ok ? '✅' : '↩️',
            'title'   => $ok ? ($kind === 'hold' ? 'Réservation garantie' : 'Acompte reçu') : 'Paiement non finalisé',
            'message' => $ok
                ? ($kind === 'hold' ? 'Votre empreinte bancaire est enregistrée : rien n\'est débité sauf en cas de no-show. Votre table est confirmée, à très vite !' : 'Merci ! Votre acompte est bien reçu et votre séjour est garanti.')
                : 'Le paiement n\'a pas été finalisé. Vous pouvez réessayer depuis le lien reçu par email, ou nous contacter.',
            'url'     => $site ? 'https://'.$site->slug.'.joow.fr' : null,
        ]);
    }

    private function ownedSite(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user->isAdmin() || ($site->user_id && $site->user_id === $user->id) || ($site->owner_email && strtolower($site->owner_email) === strtolower($user->email)), 403);

        return $site;
    }
}
