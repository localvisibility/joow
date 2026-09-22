<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Site;

/**
 * Crédits IA du Studio.
 *
 * - Une action de l'agent = 1 crédit ; la création d'une page = 2 crédits.
 * - Les modifications manuelles ne coûtent rien.
 * - Quota par formule (aperçu : 10 offerts, Pro : 100 / mois renouvelés, Liberté : 100 offerts)
 *   + portefeuille rechargeable (packs Stripe) qui ne s'épuise pas avec le temps.
 * - Consommation : quota de la période d'abord, puis portefeuille.
 */
class AiCredits
{
    public function quota(string $plan): int
    {
        return (int) (config("joow.credits.$plan") ?? config('joow.credits.free', 10));
    }

    /** Packs de recharge disponibles (clé => [credits, price_id, label]). */
    public function packs(): array
    {
        return collect(config('joow.credits.packs', []))
            ->map(fn ($p, $k) => $p + ['key' => (string) $k, 'ready' => ! empty($p['price_id'])])
            ->values()->all();
    }

    public function balance(Site $site): int
    {
        return max(0, (int) $site->ai_credits_monthly) + max(0, (int) $site->ai_credits_wallet);
    }

    /** Coût d'un tour de l'agent d'après les opérations appliquées. */
    public function costFor(array $ops, array $applied): int
    {
        if (! count($applied)) {
            return 0; // rien modifié (question, précision) : gratuit
        }
        $pages = count(array_filter($ops, fn ($o) => (($o['op'] ?? $o['type'] ?? '') === 'add_page')));

        return min(5, 1 + $pages); // 1 crédit + 1 par page créée, plafonné
    }

    /** Renouvelle le quota mensuel (Pro) si la période est écoulée. */
    public function refresh(Site $site): void
    {
        if ($site->hosting_plan !== 'pro' || ! in_array($site->status, ['paid', 'published'], true)) {
            return;
        }
        if (in_array($site->subscription_status, ['canceled', 'cancelled', 'past_due', 'unpaid'], true)) {
            return;
        }
        if ($site->ai_credits_reset_at && $site->ai_credits_reset_at->isFuture()) {
            return;
        }
        $quota = $this->quota('pro');
        $site->forceFill(['ai_credits_monthly' => $quota, 'ai_credits_reset_at' => now()->addMonth()])->save();
        $this->log($site, $quota, 'monthly_reset');
    }

    /** Débite (quota puis portefeuille). Renvoie le solde. */
    public function charge(Site $site, int $cost, array $meta = []): int
    {
        if ($cost <= 0) {
            return $this->balance($site);
        }
        $fromMonthly = min($cost, max(0, (int) $site->ai_credits_monthly));
        $fromWallet = min($cost - $fromMonthly, max(0, (int) $site->ai_credits_wallet));
        $site->forceFill([
            'ai_credits_monthly' => $site->ai_credits_monthly - $fromMonthly,
            'ai_credits_wallet'  => $site->ai_credits_wallet - $fromWallet,
            'ai_credits_used'    => $site->ai_credits_used + $fromMonthly + $fromWallet,
        ])->save();
        $this->log($site, -($fromMonthly + $fromWallet), 'ai_action', $meta);

        return $this->balance($site);
    }

    /** Crédite le portefeuille (achat, geste commercial). Idempotent par external_id. */
    public function grantWallet(Site $site, int $credits, string $reason, array $meta = [], ?string $externalId = null): bool
    {
        if ($externalId && CreditTransaction::where('external_id', $externalId)->exists()) {
            return false;
        }
        $site->forceFill(['ai_credits_wallet' => $site->ai_credits_wallet + $credits])->save();
        $this->log($site, $credits, $reason, $meta, $externalId);

        return true;
    }

    /** Mise en ligne : quota de la formule. */
    public function onPaid(Site $site, string $plan): void
    {
        $quota = $this->quota($plan);
        if ($plan === 'pro') {
            $site->forceFill(['ai_credits_monthly' => $quota, 'ai_credits_reset_at' => now()->addMonth()])->save();
        } else {
            // Liberté : crédits offerts une fois, dans le portefeuille (pas de renouvellement)
            $site->forceFill(['ai_credits_monthly' => 0, 'ai_credits_wallet' => $site->ai_credits_wallet + $quota, 'ai_credits_reset_at' => null])->save();
        }
        $this->log($site, $quota, 'plan_grant', ['plan' => $plan]);
    }

    /** État pour le Studio. */
    public function state(Site $site): array
    {
        return [
            'balance'  => $this->balance($site),
            'monthly'  => max(0, (int) $site->ai_credits_monthly),
            'wallet'   => max(0, (int) $site->ai_credits_wallet),
            'used'     => (int) $site->ai_credits_used,
            'reset_at' => optional($site->ai_credits_reset_at)->toDateString(),
            'plan'     => in_array($site->status, ['paid', 'published'], true) ? ($site->hosting_plan ?: 'pro') : 'free',
            'quota'    => $this->quota(in_array($site->status, ['paid', 'published'], true) ? ($site->hosting_plan ?: 'pro') : 'free'),
            'packs'    => $this->packs(),
        ];
    }

    private function log(Site $site, int $delta, string $reason, array $meta = [], ?string $externalId = null): void
    {
        CreditTransaction::create([
            'site_id' => $site->id, 'delta' => $delta, 'balance_after' => $this->balance($site),
            'reason' => $reason, 'meta' => $meta ?: null, 'external_id' => $externalId,
        ]);
    }
}
