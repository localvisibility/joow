<?php

namespace App\Services;

use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Abonnement Pro : essai gratuit à la mise en ligne, puis souscription (à l'échéance de l'essai
 * ou avant, à la demande du client). Les crédits IA de la formule et le domaine inclus se
 * débloquent au premier paiement.
 */
class Subscription
{
    public function __construct(private AiCredits $credits) {}

    public static function trialDays(): int
    {
        return (int) config('services.stripe.trial_days', 0);
    }

    /** Fin de l'essai gratuit si l'abonnement Pro est encore en période d'essai, sinon null. */
    public function trialEndsAt(Site $site): ?Carbon
    {
        if ($site->hosting_plan !== 'pro' || ! in_array($site->status, ['paid', 'published'], true)) {
            return null;
        }
        if ($site->subscription_status === 'active') {
            return null; // déjà payé (fin d'essai anticipée ou échéance passée)
        }
        try {
            $sub = $site->user?->subscription('hosting');
            if ($sub && $sub->onTrial()) {
                return $sub->trial_ends_at;
            }
            if ($sub) {
                return null;
            }
            // Pas encore d'abonnement synchronisé : essai en cours pendant la durée configurée
            if ($site->paid_at && self::trialDays() > 0 && $site->paid_at->gt(now()->subDays(self::trialDays()))) {
                return $site->paid_at->copy()->addDays(self::trialDays());
            }
        } catch (\Throwable) {
            // Stripe indisponible : on ne bloque rien
        }

        return null;
    }

    public function isTrialing(Site $site): bool
    {
        return $this->trialEndsAt($site) !== null;
    }

    /** État exposé aux interfaces. */
    public function state(Site $site): array
    {
        $end = $this->trialEndsAt($site);

        return [
            'status'        => $site->subscription_status,
            'plan'          => $site->hosting_plan,
            'trialing'      => $end !== null,
            'trial_ends_at' => $end?->toDateString(),
            'trial_days_left' => $end ? max(0, (int) ceil(now()->floatDiffInDays($end, false))) : null,
            'price'         => 39,
            'quota'         => $this->credits->quota('pro'),
        ];
    }

    /**
     * Le client ne veut pas attendre la fin de l'essai : on termine l'essai maintenant, Stripe
     * facture le premier mois immédiatement, et les crédits / le domaine inclus se débloquent.
     *
     * @return array{paid: bool, pay_url: ?string}
     */
    public function startNow(Site $site): array
    {
        $sub = $site->user?->subscription('hosting');
        if (! $sub) {
            throw new \RuntimeException('Abonnement introuvable pour le moment. Réessayez dans quelques minutes.');
        }
        if ($sub->onTrial()) {
            $sub->endTrial(); // trial_end = now → facture du 1er mois émise et prélevée immédiatement
        }
        $paid = true;
        $payUrl = null;
        $stripeSub = $sub->asStripeSubscription(['latest_invoice']);
        $inv = $stripeSub->latest_invoice ?? null;
        if (is_object($inv) && ($inv->status ?? 'paid') !== 'paid') {
            $paid = false;
            $payUrl = $inv->hosted_invoice_url ?? null; // 3-D Secure ou carte refusée : le client finalise sur Stripe
        }
        if ($paid) {
            $this->activate($site, 'trial_ended_early');
        }

        return ['paid' => $paid, 'pay_url' => $payUrl];
    }

    /** Premier paiement encaissé (anticipé ou à l'échéance) : abonnement actif + crédits de la formule. */
    /**
     * Site payé (retour de Stripe ou webhook, idempotent) : statut, crédits, compte connecté,
     * facture, email « en ligne », conversion Purchase.
     */
    public function fulfil(Site $site, string $plan, ?string $sessionId, object|array|null $session = null, $request = null): bool
    {
        $site = $site->fresh();
        $billing = app(Billing::class);
        $user = $billing->owner($site);
        if ($site->status === 'paid' || $site->status === 'published') {
            // Déjà traité : on s'assure seulement que la facture existe (webhook arrivé après le retour)
            if ($session) {
                $billing->recordFromCheckoutSession($session, $site, $plan);
            }

            return false;
        }
        $trialing = $plan === 'pro' && self::trialDays() > 0;
        $site->update([
            'status'              => 'paid',
            'paid_at'             => now(),
            'published_at'        => now(),
            'hosting_plan'        => $plan,
            'subscription_status' => $plan === 'liberte' ? 'lifetime' : ($trialing ? 'trialing' : 'active'),
            'purchase_type'       => $plan === 'liberte' ? 'one_time' : 'subscription',
            'user_id'             => $site->user_id ?: $user?->id,
        ]);
        if (! $trialing) {
            $this->credits->onPaid($site->fresh(), $plan);
        }
        $invoice = $session ? $billing->recordFromCheckoutSession($session, $site, $plan) : null;
        if ($user) {
            app(Accounts::class)->live($user, $site->fresh(), $plan, $invoice);
        }
        try {
            app(MetaConversions::class)->event('Purchase', ['value' => MetaConversions::PRICES[$plan], 'currency' => 'EUR', 'content_ids' => [$plan], 'content_type' => 'product', 'order_id' => (string) $sessionId], $site->owner_email, $site->user_id, 'purchase-'.$sessionId, $request);
        } catch (\Throwable $e) {
            Log::info('Tracking Purchase : '.$e->getMessage());
        }

        return true;
    }

    public function activate(Site $site, string $reason = 'invoice_paid'): void
    {
        $wasActive = $site->subscription_status === 'active';
        $site->forceFill(['subscription_status' => 'active'])->save();
        if (! $wasActive || ! $site->ai_credits_reset_at || $site->ai_credits_reset_at->isPast()) {
            $this->credits->onPaid($site->fresh(), 'pro');
            Log::info("Abonnement {$site->slug} actif ($reason) : crédits Pro accordés");
        }
    }
}
