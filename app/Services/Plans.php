<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

/**
 * Formules d'hébergement :
 *  - essentiel : site, domaine, hébergement, référencement, formulaire, WhatsApp, avis… (mensuel) ;
 *  - pro       : tout l'Essentiel + modules avancés (réservations, paiements, assistant IA…) (mensuel) ;
 *  - liberte   : paiement unique, tous les modules.
 */
class Plans
{
    /** Formules à abonnement mensuel. */
    public const MONTHLY = ['essentiel', 'pro'];

    public static function price(string $plan): float
    {
        return (float) (config("joow.plans.$plan.price") ?? 0);
    }

    public static function label(string $plan): string
    {
        return (string) (config("joow.plans.$plan.label") ?? ucfirst($plan));
    }

    public static function monthly(?string $plan): bool
    {
        return in_array($plan, self::MONTHLY, true);
    }

    /** Modules réservés à la formule Pro (et Liberté). */
    public static function proModules(): array
    {
        return (array) config('joow.plans.essentiel.pro_modules', []);
    }

    /** Le module est-il bloqué par la formule du site ? (seulement un site en ligne en Essentiel) */
    public static function blocks(Site $site, string $key): bool
    {
        return $site->hosting_plan === 'essentiel' && in_array($site->status, ['paid', 'published'], true)
            && in_array($key, self::proModules(), true);
    }

    /** Identifiant du prix Stripe d'une formule ; le prix Essentiel est créé automatiquement s'il n'est pas configuré. */
    public static function stripePrice(string $plan): ?string
    {
        if ($plan === 'pro') {
            return config('services.stripe.price_pro') ?: null;
        }
        if ($plan === 'liberte') {
            return config('services.stripe.price_liberte') ?: null;
        }
        if ($plan !== 'essentiel') {
            return null;
        }
        if ($id = config('services.stripe.price_essentiel')) {
            return $id;
        }
        if (! config('cashier.secret')) {
            return null;
        }

        return Cache::rememberForever('stripe-price-essentiel-'.(int) round(self::price('essentiel') * 100), function () {
            $key = 'joow-essentiel-'.(int) round(self::price('essentiel') * 100);
            try {
                $found = Cashier::stripe()->prices->all(['lookup_keys' => [$key], 'limit' => 1])->data[0] ?? null;
                if ($found) {
                    return $found->id;
                }
                // Même devise et même régime de TVA que la formule Pro
                $pro = config('services.stripe.price_pro') ? Cashier::stripe()->prices->retrieve(config('services.stripe.price_pro')) : null;
                $product = Cashier::stripe()->products->create(['name' => 'Joow Essentiel', 'description' => 'Site, nom de domaine, hébergement et référencement']);

                return Cashier::stripe()->prices->create(array_filter([
                    'product' => $product->id, 'lookup_key' => $key, 'currency' => $pro->currency ?? 'eur',
                    'unit_amount' => (int) round(self::price('essentiel') * 100), 'recurring' => ['interval' => 'month'],
                    'tax_behavior' => $pro->tax_behavior ?? null,
                ]))->id;
            } catch (\Throwable $e) {
                Log::error('Formule Essentiel : prix Stripe impossible à créer : '.$e->getMessage());

                return null;
            }
        }) ?: tap(null, fn () => Cache::forget('stripe-price-essentiel-'.(int) round(self::price('essentiel') * 100)));
    }

    /** Données des formules pour les interfaces. */
    public static function forUi(): array
    {
        return [
            'essentiel' => ['price' => self::price('essentiel'), 'pro_modules' => array_values(array_map(fn ($k) => ['key' => $k, 'name' => config("modules.$k.name") ?? $k], self::proModules()))],
            'pro' => ['price' => self::price('pro')],
            'liberte' => ['price' => self::price('liberte')],
            'refund_days' => (int) config('joow.refund_days', 0),
        ];
    }
}
