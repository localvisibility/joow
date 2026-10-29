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
        Vite::prefetch(concurrency: 3);

        // Paiements Stripe Connect (acomptes, empreintes) — Cashier vérifie la signature.
        // Les écouteurs de app/Listeners (webhooks Stripe, suivi des emails) sont découverts automatiquement :
        // ne pas les déclarer ici aussi, sinon chaque événement est traité deux fois.
    }
}
