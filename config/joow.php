<?php

return [
    // Aperçus gratuits par 24 h, par adresse IP et par email (clients payants non limités)
    // Joow Ads : visuels publicitaires générés par l'IA inclus chaque mois (au-delà : 2 crédits IA chacun)
    'ads_visuals_per_month' => (int) env('JOOW_ADS_VISUALS_PER_MONTH', 20),
    'free_sites_per_day' => (int) env('JOOW_FREE_SITES_PER_DAY', 5),

    // Emails opérateurs (voient tout le parc dans le dashboard).
    // Définir sur le VPS : JOOW_ADMIN_EMAILS="ops@exemple.fr,autre@exemple.fr"
    // Vide => chaque client ne voit que ses propres sites.
    'admins' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('JOOW_ADMIN_EMAILS', ''))
    ))),

    // Landing : compteur « sites créés » affiché au minimum à cette valeur (parc historique
    // Local Visibility + Joow). Le compteur réel prend le relais dès qu'il dépasse ce plancher.
    'landing' => [
        'sites_min' => (int) env('JOOW_SITES_DISPLAY_MIN', 1000),
    ],

    // Domaines personnalisés : IP publique du VPS (par défaut : résolution de APP_URL).
    'domains' => [
        'server_ip' => env('JOOW_SERVER_IP'),
    ],

    // Crédits IA du Studio (1 action = 1 crédit, création d'une page = 2).
    // Les modifications manuelles sont illimitées.
    'credits' => [
        'free'    => (int) env('JOOW_CREDITS_FREE', 10),      // aperçu gratuit, offerts à la génération
        'pro'     => (int) env('JOOW_CREDITS_PRO', 100),      // par mois, renouvelés
        'liberte' => (int) env('JOOW_CREDITS_LIBERTE', 100),  // offerts une fois
        // Packs de recharge (Stripe Checkout, paiement unique). Créer les prix dans Stripe puis :
        // STRIPE_PRICE_CREDITS_50=price_… (9 € HT) · STRIPE_PRICE_CREDITS_200=price_… (29 € HT)
        'packs' => [
            '50'  => ['credits' => 50,  'label' => '9 € HT',  'price_id' => env('STRIPE_PRICE_CREDITS_50')],
            '200' => ['credits' => 200, 'label' => '29 € HT', 'price_id' => env('STRIPE_PRICE_CREDITS_200'), 'best' => true],
        ],
    ],
    // Tarifs publics indicatifs des API (USD) pour l'estimation des coûts du back-office :
    // usd = prix pour `per` appels (1000) ou par image (per = 1), free = gratuité mensuelle. À ajuster selon vos factures.
    'usd_eur' => (float) env('JOOW_USD_EUR', 0.88),
    'api_prices' => [
        'places.autocomplete' => ['per' => 1000, 'usd' => 2.83, 'free' => 10000],
        'places.text_ids' => ['per' => 1000, 'usd' => 0, 'free' => 0],
        'places.text_pro' => ['per' => 1000, 'usd' => 32, 'free' => 5000],
        'places.text_enterprise' => ['per' => 1000, 'usd' => 35, 'free' => 1000],
        'places.details_essentials' => ['per' => 1000, 'usd' => 5, 'free' => 10000],
        'places.details_pro' => ['per' => 1000, 'usd' => 17, 'free' => 5000],
        'places.details_enterprise' => ['per' => 1000, 'usd' => 20, 'free' => 1000],
        'places.details_atmosphere' => ['per' => 1000, 'usd' => 25, 'free' => 1000],
        'places.photo' => ['per' => 1000, 'usd' => 7, 'free' => 1000],
        'gemini.text' => ['per' => 1000, 'usd' => 0, 'free' => 0],
        'gemini.image_pro' => ['per' => 1, 'usd' => 0.134, 'free' => 0],
        'gemini.image_flash' => ['per' => 1, 'usd' => 0.039, 'free' => 0],
        'openai.image' => ['per' => 1, 'usd' => 0.2, 'free' => 0],
    ],

];
