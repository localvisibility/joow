<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // HTTPS correct derrière le reverse-proxy de l'hôte (nginx/Caddy).
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\CaptureAttribution::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Cookies écrits par le navigateur (consentement, pixel Meta) : lus tels quels, non chiffrés
        $middleware->encryptCookies(except: ['joow_consent', '_fbp', '_fbc']);

        // Endpoints publics dispensés de CSRF :
        // - api/*    : demandes des sites générés (throttle + honeypot)
        // - stripe/* : webhooks Stripe (Cashier), signés côté Stripe
        $middleware->validateCsrfTokens(except: ['api/*', 'stripe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
