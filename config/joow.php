<?php

return [
    // Emails opérateurs (voient tout le parc dans le dashboard).
    // Définir sur le VPS : JOOW_ADMIN_EMAILS="ops@exemple.fr,autre@exemple.fr"
    // Vide => chaque client ne voit que ses propres sites.
    'admins' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('JOOW_ADMIN_EMAILS', ''))
    ))),

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
];
