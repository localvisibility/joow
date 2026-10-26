<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;

/**
 * Tunnel de paiement : le prospect met son site en ligne (abonnement hébergement).
 * Paiement Stripe (Cashier) -> compte créé + site activé.
 */
class CheckoutController extends Controller
{
    /** Démarre le paiement : crée le compte si besoin, ouvre Stripe Checkout. */
    public function start(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'email'    => ['required', 'email'],
            'plan'     => ['nullable', 'in:pro,liberte'],
            'event_id' => ['nullable', 'string', 'max:64'],
        ]);
        $plan = $data['plan'] ?? 'pro';

        $price = $plan === 'liberte'
            ? config('services.stripe.price_liberte')
            : config('services.stripe.price_pro');

        // Stripe non configuré (clé secrète ou tarif absent) : message clair au lieu d'une erreur 500
        if (! $price || ! config('cashier.secret')) {
            return back()->withErrors(['email' => "Le paiement n'est pas encore disponible. Réessayez bientôt."]);
        }

        // Compte : réutilise l'existant ou en crée un (mot de passe défini plus tard via reset)
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $site->owner_name ?: Str::before($data['email'], '@'),
                'password' => bcrypt(Str::random(40)),
                'email_verified_at' => now(),
            ]
        );

        $site->forceFill(['owner_email' => $user->email, 'user_id' => $user->id])->save();
        app(\App\Services\Accounts::class)->checkoutStarted($site, $plan);
        // InitiateCheckout : le navigateur a déjà envoyé le pixel avec ce même event_id
        app(\App\Services\MetaConversions::class)->event('InitiateCheckout', ['value' => \App\Services\MetaConversions::PRICES[$plan], 'currency' => 'EUR', 'content_ids' => [$plan], 'content_type' => 'product'], $user->email, $user->id, $data['event_id'] ?? null, $request);
        \App\Services\Tracking::pull(); // déjà déclenché côté navigateur : ne pas le rejouer

        $urls = [
            'success_url' => route('public.paid', $slug).'?plan='.$plan.'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('public.site', $slug),
            'metadata'    => ['site_slug' => $slug, 'user_id' => $user->id, 'plan' => $plan],
        ];

        // Liberté : paiement unique. Pro : abonnement mensuel avec essai gratuit.
        return $user->withValidStripeCustomer(function () use ($user, $plan, $price, $urls) {
            if ($plan === 'liberte') {
                return $user->checkout([$price => 1], $urls)->redirect();
            }

            $sub = $user->newSubscription('hosting', $price);
            if (($days = (int) config('services.stripe.trial_days')) > 0) {
                $sub->trialUntil(now()->addDays($days)->endOfDay());
            }

            return $sub->checkout($urls)->redirect();
        });
    }

    /** Retour après paiement réussi : active le site et connecte le client. */
    public function paid(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $sessionId = $request->query('session_id');

        $paid = false;
        if ($sessionId) {
            try {
                $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
                $paid = ($session->payment_status === 'paid' || $session->status === 'complete');
            } catch (\Throwable) {
                $paid = false;
            }
        }

        $plan = $request->query('plan') === 'liberte' ? 'liberte' : 'pro';

        if ($paid && $site->status !== 'paid') {
            // Pro avec essai : statut « trialing » jusqu'au 1er paiement (anticipé ou à l'échéance)
            $trialing = $plan === 'pro' && \App\Services\Subscription::trialDays() > 0;
            $site->update([
                'status'              => 'paid',
                'paid_at'             => now(),
                'published_at'        => now(),
                'hosting_plan'        => $plan,
                'subscription_status' => $plan === 'liberte' ? 'lifetime' : ($trialing ? 'trialing' : 'active'),
                'purchase_type'       => $plan === 'liberte' ? 'one_time' : 'subscription',
            ]);
            // Crédits IA de la formule (Pro : 100 / mois dès le 1er paiement ; Liberté : 100 offerts).
            // Pendant l'essai, le client garde ses crédits d'aperçu et peut souscrire à tout moment.
            if (! $trialing) {
                app(\App\Services\AiCredits::class)->onPaid($site->fresh(), $plan);
            }
            if ($site->user) {
                Auth::login($site->user);
                app(\App\Services\Accounts::class)->live($site->user, $site->fresh(), $plan);
            }
            // Purchase : valeur de la formule, identifiant de commande = session Stripe (dédoublonnage)
            app(\App\Services\MetaConversions::class)->event('Purchase', ['value' => \App\Services\MetaConversions::PRICES[$plan], 'currency' => 'EUR', 'content_ids' => [$plan], 'content_type' => 'product', 'order_id' => (string) $sessionId], $site->owner_email, $site->user_id, 'purchase-'.$sessionId, $request);
        }

        return Inertia::render('Public/Thanks', [
            'site' => $site->only(['slug', 'name', 'preview_url']),
            'paid' => $paid,
        ]);
    }
}
