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
            'email' => ['required', 'email'],
            'plan'  => ['nullable', 'in:pro,liberte'],
        ]);
        $plan = $data['plan'] ?? 'pro';

        $price = $plan === 'liberte'
            ? config('services.stripe.price_liberte')
            : config('services.stripe.price_pro');

        if (! $price) {
            return back()->withErrors(['email' => "Cette formule n'est pas encore configurée. Réessayez bientôt."]);
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

        $urls = [
            'success_url' => route('public.paid', $slug).'?plan='.$plan.'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('public.site', $slug),
            'metadata'    => ['site_slug' => $slug, 'user_id' => $user->id, 'plan' => $plan],
        ];

        // Liberté : paiement unique. Pro : abonnement mensuel avec essai gratuit.
        if ($plan === 'liberte') {
            return $user->checkout([$price => 1], $urls)->redirect();
        }

        $sub = $user->newSubscription('hosting', $price);
        if (($days = (int) config('services.stripe.trial_days')) > 0) {
            $sub->trialDays($days);
        }

        return $sub->checkout($urls)->redirect();
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
            $site->update([
                'status'              => 'paid',
                'paid_at'             => now(),
                'published_at'        => now(),
                'hosting_plan'        => $plan,
                'subscription_status' => $plan === 'liberte' ? 'lifetime' : 'active',
                'purchase_type'       => $plan === 'liberte' ? 'one_time' : 'subscription',
            ]);
            if ($site->user) {
                Auth::login($site->user);
            }
        }

        return Inertia::render('Public/Thanks', [
            'site' => $site->only(['slug', 'name', 'preview_url']),
            'paid' => $paid,
        ]);
    }
}
