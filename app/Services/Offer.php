<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

/**
 * Offre de relance : -30 % pendant 72 h, envoyée par email aux prospects qui n'ont pas encore acheté.
 * Pro : -30 % pendant 3 mois. Liberté : -30 % sur le paiement unique. Appliquée automatiquement au paiement.
 */
class Offer
{
    public const PERCENT = 30;

    public const HOURS = 72;

    public const COUPONS = [
        'pro' => ['id' => 'joow-relance-pro-30', 'duration' => 'repeating', 'months' => 3, 'name' => 'Offre de bienvenue -30 % (3 mois)'],
        'liberte' => ['id' => 'joow-relance-liberte-30', 'duration' => 'once', 'months' => null, 'name' => 'Offre de bienvenue -30 %'],
    ];

    /** Offre en cours pour ce site, ou null. */
    public static function active(Site $site): ?array
    {
        $until = $site->site_data['emails']['offer_until'] ?? null;
        if (! $until || Carbon::parse($until)->isPast() || in_array($site->status, ['paid', 'published'], true)) {
            return null;
        }

        return [
            'percent' => self::PERCENT,
            'until' => Carbon::parse($until)->toIso8601String(),
            'pro' => round(39 * (100 - self::PERCENT) / 100, 2),
            'liberte' => round(349 * (100 - self::PERCENT) / 100, 2),
            'months' => self::COUPONS['pro']['months'],
        ];
    }

    public static function start(Site $site, Accounts $accounts, array $extra = []): Carbon
    {
        $until = now()->addHours(self::HOURS);
        $accounts->mark($site, 'offer_at', ['offer_until' => $until->toIso8601String()] + $extra);

        return $until;
    }

    /** Identifiant du coupon Stripe (créé à la première utilisation). */
    public static function coupon(string $plan): ?string
    {
        $c = self::COUPONS[$plan] ?? null;
        if (! $c) {
            return null;
        }
        try {
            Cashier::stripe()->coupons->retrieve($c['id']);
        } catch (\Throwable) {
            try {
                Cashier::stripe()->coupons->create(array_filter([
                    'id' => $c['id'], 'name' => $c['name'], 'percent_off' => self::PERCENT,
                    'duration' => $c['duration'], 'duration_in_months' => $c['months'],
                ]));
            } catch (\Throwable $e) {
                Log::warning('Offre : coupon Stripe impossible à créer : '.$e->getMessage());

                return null;
            }
        }

        return $c['id'];
    }

    public static function euros(float $v): string
    {
        return str_replace(',00', '', number_format($v, 2, ',', ' ')).' €';
    }
}
