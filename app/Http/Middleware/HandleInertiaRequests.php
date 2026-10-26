<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Offre : 0 = paiement à la mise en ligne, N = N jours d'essai gratuit avant le 1er prélèvement
            'trial_days' => (int) config('services.stripe.trial_days', 0),
            'google_enabled' => \App\Http\Controllers\Auth\GoogleAuthController::enabled(),
        ];
    }
}
