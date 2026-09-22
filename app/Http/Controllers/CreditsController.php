<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\AiCredits;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;

/**
 * Recharge de crédits IA (packs, paiement unique Stripe Checkout).
 * Réservée aux sites rattachés à un compte (facturation), donc après la mise en ligne
 * ou l'inscription.
 */
class CreditsController extends Controller
{
    public function checkout(Request $request, string $slug, AiCredits $credits)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user && $site->editableBy($user), 403);

        $data = $request->validate(['pack' => ['required', 'string', 'max:10']]);
        $pack = collect($credits->packs())->firstWhere('key', $data['pack']);
        abort_unless($pack && $pack['ready'], 422, 'Ce pack n\'est pas disponible pour le moment.');

        // Le site doit être rattaché au compte qui paie (facture, historique)
        if (! $site->user_id) {
            $site->forceFill(['user_id' => $user->id, 'owner_email' => $site->owner_email ?: $user->email])->save();
        }

        return $user->checkout([$pack['price_id'] => 1], [
            'success_url' => route('credits.return', $slug).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('sites.editor', $slug),
            'metadata'    => ['type' => 'credits', 'site_slug' => $slug, 'pack' => $pack['key'], 'credits' => $pack['credits'], 'user_id' => $user->id],
        ])->redirect();
    }

    /** Retour Stripe : crédite si payé (idempotent ; le webhook fait de même). */
    public function return(Request $request, string $slug, AiCredits $credits)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $sessionId = (string) $request->query('session_id');
        $added = 0;

        if ($sessionId) {
            try {
                $s = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
                $meta = (array) ($s->metadata ?? []);
                if (($s->payment_status ?? '') === 'paid' && ($meta['type'] ?? '') === 'credits' && ($meta['site_slug'] ?? '') === $slug) {
                    $n = (int) ($meta['credits'] ?? 0);
                    if ($n > 0 && $credits->grantWallet($site, $n, 'purchase', ['pack' => $meta['pack'] ?? null], $sessionId)) {
                        $added = $n;
                    }
                }
            } catch (\Throwable) {
                // on laisse le webhook créditer si besoin
            }
        }

        return redirect()->route('sites.editor', $slug)->with('credits_added', $added);
    }
}
