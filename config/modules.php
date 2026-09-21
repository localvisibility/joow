<?php

// Catalogue des modules (hérité de Local Visibility, étendu).
// key => libellé, description, gratuit/payant, secteurs ciblés (vide = tous), fonctionnalités mises en avant.
return [
    'restaurant' => [
        'name' => 'Réservation Restaurant', 'icon' => '🍽️', 'free' => false,
        'desc' => 'Réservation de table en ligne avec créneaux, capacité par service, confirmation et rappels. Remplace le simple formulaire.',
        'features' => ['Créneaux & services', 'Capacité par créneau', 'Confirmation email', 'Rappels'],
        'sectors' => ['restaurant'],
    ],
    'rooms' => [
        'name' => 'Chambres & Calendrier', 'icon' => '🛏️', 'free' => false,
        'desc' => 'Vos chambres / gîtes avec tarifs, disponibilités et demande de séjour. Synchronisation Airbnb, Booking, Abritel (iCal) : fini les doubles réservations.',
        'features' => ['Fiches chambres', 'Calendrier', 'Sync Airbnb / Booking', 'Demande de séjour'],
        'sectors' => ['hebergement'],
    ],
    'menu' => [
        'name' => 'Menu / Carte', 'icon' => '📋', 'free' => false,
        'desc' => 'Votre carte digitale : catégories, plats, prix, disponibilité. Consultable en ligne et via QR code, sans toucher à l\'éditeur.',
        'features' => ['Catégories', 'Prix & descriptions', 'QR code', 'Mise à jour instantanée'],
        'sectors' => ['restaurant', 'hebergement'],
    ],
    'booking' => [
        'name' => 'Formulaire de demande', 'icon' => '📩', 'free' => true,
        'desc' => 'Formulaire de réservation / rendez-vous / devis adapté à votre métier. Les demandes arrivent dans votre espace et par email.',
        'features' => ['Adapté au métier', 'Email instantané', 'Anti-spam'],
        'sectors' => [],
    ],
    'whatsapp' => [
        'name' => 'Chat WhatsApp', 'icon' => '💬', 'free' => true,
        'desc' => 'Une bulle WhatsApp flottante sur votre site : vos visiteurs vous écrivent en un clic, avec un message pré-rempli.',
        'features' => ['Bulle flottante', 'Message pré-rempli', 'Position au choix'],
        'sectors' => [],
    ],
    'reviews' => [
        'name' => 'Avis Clients', 'icon' => '⭐', 'free' => true,
        'desc' => 'Vos meilleurs avis Google affichés automatiquement pour rassurer vos visiteurs.',
        'features' => ['Avis Google', 'Note minimale', 'Nombre affiché'],
        'sectors' => [],
    ],
    'legal' => [
        'name' => 'Mentions Légales', 'icon' => '⚖️', 'free' => true,
        'desc' => 'Mentions légales et politique de confidentialité conformes RGPD, générées automatiquement. Obligatoire pour tout site professionnel.',
        'features' => ['Mentions légales', 'Politique de confidentialité', 'Conforme RGPD'],
        'sectors' => [],
    ],
    'bot' => [
        'name' => 'Assistant IA', 'icon' => '🤖', 'free' => true,
        'desc' => 'Un assistant qui répond à vos visiteurs 24h/24, formé sur votre établissement (services, horaires, FAQ, carte). Peut collecter les coordonnées des intéressés.',
        'features' => ['Réponses 24h/24', 'Formé sur votre site', 'Collecte de contacts'],
        'sectors' => [],
    ],
    'zenchef' => [
        'name' => 'ZenChef', 'icon' => '🧑‍🍳', 'free' => true,
        'desc' => 'Vous utilisez déjà ZenChef ? Affichez leur widget de réservation directement sur votre site.',
        'features' => ['Widget ZenChef', 'Sans double saisie'],
        'sectors' => ['restaurant'],
    ],
    'payment' => [
        'name' => 'Paiement en ligne', 'icon' => '💳', 'free' => false,
        'desc' => 'Acceptez les acomptes, arrhes ou paiements intégraux par carte lors d\'une réservation. Sécurisé par Stripe.',
        'features' => ['Acompte / arrhes / intégral', 'Stripe', 'Notification'],
        'sectors' => ['restaurant', 'hebergement'],
        'soon' => true,
    ],
    'stats' => [
        'name' => 'Statistiques', 'icon' => '📈', 'free' => true,
        'desc' => 'Visites, demandes, réservations : suivez la performance de votre site jour après jour.',
        'features' => ['Visites', 'Demandes & réservations', 'Graphiques'],
        'sectors' => [],
        'always_on' => true,
    ],
];
