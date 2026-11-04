<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Comptage des appels aux API payantes (back-office → IA & coûts)
        \Illuminate\Support\Facades\Http::globalMiddleware(fn ($handler) => fn ($request, $options) => $handler($request, $options)->then(function ($response) use ($request) {
            \App\Services\ApiUsage::record($request, $response);

            return $response;
        }));

        // Joow Ads : un compteur par action (et non un compteur commun à toutes les actions), sans limite pour les admins
        \Illuminate\Support\Facades\RateLimiter::for('ads', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user?->isAdmin()) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }
            $route = (string) $request->route()?->getName();
            [$max, $minutes] = match ($route) {
                'ads.pack', 'ads.campaign' => [15, 60],
                'ads.creative' => [60, 10],
                'ads.visual' => [30, 10],
                default => [30, 60],
            };

            return \Illuminate\Cache\RateLimiting\Limit::perMinutes($minutes, $max)->by($route.'|'.($user?->id ?: $request->ip()))
                ->response(fn () => response()->json(['error' => 'Vous avez lancé beaucoup de créations d\'un coup : patientez quelques minutes avant de recommencer.'], 429));
        });

        Vite::prefetch(concurrency: 3);

        // Paiements Stripe Connect (acomptes, empreintes) — Cashier vérifie la signature.
        // Les écouteurs de app/Listeners (webhooks Stripe, suivi des emails) sont découverts automatiquement :
        // ne pas les déclarer ici aussi, sinon chaque événement est traité deux fois.
    }
}
