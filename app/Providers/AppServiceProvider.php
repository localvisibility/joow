<?php

namespace App\Providers;

use App\Listeners\StripeWebhookListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;

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
        Event::listen(WebhookReceived::class, StripeWebhookListener::class);
    }
}
