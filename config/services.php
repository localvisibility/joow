<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Génération de sites
    'google_places' => [
        'key' => env('GOOGLE_PLACES_API_KEY'),
    ],
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],
    'sites_path' => env('SITES_PATH', '/var/www/sites'),

    // SMS transactionnels (Brevo) : confirmations et rappels de réservation.
    'brevo' => [
        'key'        => env('BREVO_API_KEY'),
        'sms_sender' => env('SMS_SENDER', 'Joow'), // 11 caractères alphanumériques max
    ],

    'stripe' => [
        // Commission plateforme sur les paiements encaissés via Stripe Connect (en %).
        'platform_fee_percent' => (float) env('STRIPE_PLATFORM_FEE_PERCENT', 0),
        // Formule Pro : abonnement mensuel (39€ HT/mois) avec essai gratuit.
        'price_pro'     => env('STRIPE_PRICE_PRO', env('STRIPE_PRICE_HOSTING')),
        'trial_days'    => (int) env('STRIPE_TRIAL_DAYS', 7),
        // Formule Liberté : paiement unique (349€ HT, site à vie).
        'price_liberte' => env('STRIPE_PRICE_LIBERTE'),
        // Ancien price récurrent (compatibilité).
        'price_hosting' => env('STRIPE_PRICE_HOSTING'),
    ],

];
