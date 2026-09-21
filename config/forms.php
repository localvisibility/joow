<?php

// Modèles de formulaires multi-étapes par secteur (module "Formulaire de demande").
// Types de champs : cards (choix illustré), chips (multi-choix), toggle (bascule), select,
// text, textarea, date, time, number, phone, email. L'étape contact est ajoutée automatiquement.
// Le client peut tout modifier dans le Studio (étapes, champs, options, textes).

$contact = ['title' => 'Vos coordonnées', 'icon' => 'fa-user', 'contact' => true, 'fields' => [
    ['type' => 'text', 'key' => 'name', 'label' => 'Votre nom', 'required' => true, 'placeholder' => 'Prénom Nom'],
    ['type' => 'phone', 'key' => 'phone', 'label' => 'Téléphone', 'required' => true, 'placeholder' => '06 12 34 56 78'],
    ['type' => 'email', 'key' => 'email', 'label' => 'Email', 'required' => false, 'placeholder' => 'vous@exemple.fr'],
    ['type' => 'textarea', 'key' => 'message', 'label' => 'Un mot pour nous ? (optionnel)', 'required' => false, 'placeholder' => 'Précisions, questions…'],
]];

$slots = ['type' => 'chips', 'key' => 'creneaux', 'label' => 'Vos disponibilités', 'required' => false,
    'options' => ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Matin', 'Après-midi', 'Soir']];

return [
    'default' => [
        'type' => 'devis', 'cta' => 'Envoyer ma demande', 'delay' => 'Réponse sous 24h',
        'success' => 'Merci ! Votre demande est bien envoyée, nous revenons vers vous très vite.',
        'steps' => [
            ['title' => 'Votre besoin', 'icon' => 'fa-wand-magic-sparkles', 'fields' => [
                ['type' => 'cards', 'key' => 'besoin', 'label' => 'Que pouvons-nous faire pour vous ?', 'required' => true, 'options' => [
                    ['value' => 'devis', 'label' => 'Demander un devis', 'desc' => 'Estimation gratuite', 'icon' => 'fa-file-invoice'],
                    ['value' => 'rdv', 'label' => 'Prendre rendez-vous', 'desc' => 'Sur place ou à distance', 'icon' => 'fa-calendar-check'],
                    ['value' => 'info', 'label' => 'Une question', 'desc' => 'On vous répond vite', 'icon' => 'fa-circle-question'],
                ]],
                ['type' => 'textarea', 'key' => 'details', 'label' => 'Décrivez votre projet', 'required' => false, 'placeholder' => 'Le plus de détails possible…'],
            ]],
            ['title' => 'Quand ?', 'icon' => 'fa-calendar', 'fields' => [
                ['type' => 'toggle', 'key' => 'urgence', 'label' => 'Délai souhaité', 'options' => ['Dès que possible', 'Cette semaine', 'Ce mois-ci', 'Je me renseigne']],
                $slots,
            ]],
            $contact,
        ],
    ],

    'restaurant' => [
        'type' => 'reservation', 'cta' => 'Envoyer ma demande', 'delay' => 'Confirmation rapide',
        'success' => 'Merci ! Nous confirmons votre table très vite par téléphone ou email.',
        'steps' => [
            ['title' => 'Votre venue', 'icon' => 'fa-utensils', 'fields' => [
                ['type' => 'cards', 'key' => 'occasion', 'label' => 'Quelle occasion ?', 'required' => true, 'options' => [
                    ['value' => 'repas', 'label' => 'Déjeuner / dîner', 'desc' => 'Table classique', 'icon' => 'fa-utensils'],
                    ['value' => 'groupe', 'label' => 'Groupe', 'desc' => '8 personnes et +', 'icon' => 'fa-people-group'],
                    ['value' => 'evenement', 'label' => 'Événement', 'desc' => 'Anniversaire, pro…', 'icon' => 'fa-champagne-glasses'],
                    ['value' => 'privatisation', 'label' => 'Privatisation', 'desc' => 'Salle ou restaurant', 'icon' => 'fa-key'],
                ]],
                ['type' => 'number', 'key' => 'couverts', 'label' => 'Nombre de couverts', 'required' => true, 'placeholder' => '2', 'min' => 1],
            ]],
            ['title' => 'Date & préférences', 'icon' => 'fa-calendar', 'fields' => [
                ['type' => 'date', 'key' => 'date', 'label' => 'Date souhaitée', 'required' => true],
                ['type' => 'toggle', 'key' => 'service', 'label' => 'Service', 'options' => ['Midi', 'Soir']],
                ['type' => 'chips', 'key' => 'preferences', 'label' => 'Préférences', 'options' => ['Terrasse', 'Salle', 'Calme', 'Près de la fenêtre', 'Chaise bébé', 'Accès PMR']],
                ['type' => 'chips', 'key' => 'regime', 'label' => 'Régimes / allergies', 'options' => ['Végétarien', 'Végan', 'Sans gluten', 'Sans lactose', 'Halal', 'Allergie (préciser)']],
            ]],
            $contact,
        ],
    ],

    'hebergement' => [
        'type' => 'reservation', 'cta' => 'Envoyer ma demande de séjour', 'delay' => 'Réponse sous 24h',
        'success' => 'Merci ! Nous vérifions les disponibilités et revenons vers vous rapidement.',
        'steps' => [
            ['title' => 'Votre séjour', 'icon' => 'fa-bed', 'fields' => [
                ['type' => 'date', 'key' => 'arrivee', 'label' => 'Arrivée', 'required' => true],
                ['type' => 'date', 'key' => 'depart', 'label' => 'Départ', 'required' => true],
                ['type' => 'number', 'key' => 'adultes', 'label' => 'Adultes', 'required' => true, 'placeholder' => '2', 'min' => 1],
                ['type' => 'number', 'key' => 'enfants', 'label' => 'Enfants', 'required' => false, 'placeholder' => '0', 'min' => 0],
            ]],
            ['title' => 'Vos envies', 'icon' => 'fa-heart', 'fields' => [
                ['type' => 'cards', 'key' => 'motif', 'label' => 'Type de séjour', 'required' => false, 'options' => [
                    ['value' => 'detente', 'label' => 'Détente', 'desc' => 'Repos, nature', 'icon' => 'fa-spa'],
                    ['value' => 'couple', 'label' => 'En couple', 'desc' => 'Escapade romantique', 'icon' => 'fa-heart'],
                    ['value' => 'famille', 'label' => 'En famille', 'desc' => 'Avec enfants', 'icon' => 'fa-children'],
                    ['value' => 'pro', 'label' => 'Professionnel', 'desc' => 'Déplacement, séminaire', 'icon' => 'fa-briefcase'],
                ]],
                ['type' => 'chips', 'key' => 'options', 'label' => 'Options souhaitées', 'options' => ['Petit-déjeuner', 'Dîner', 'Lit bébé', 'Animaux', 'Parking', 'Arrivée tardive', 'Spa / massage']],
            ]],
            $contact,
        ],
    ],

    'sante' => [
        'type' => 'rdv', 'cta' => 'Demander un rendez-vous', 'delay' => 'Réponse rapide',
        'success' => 'Merci ! Nous vous recontactons très vite pour fixer votre rendez-vous.',
        'steps' => [
            ['title' => 'Motif', 'icon' => 'fa-stethoscope', 'fields' => [
                ['type' => 'cards', 'key' => 'motif', 'label' => 'Motif de consultation', 'required' => true, 'options' => [
                    ['value' => 'consultation', 'label' => 'Consultation', 'desc' => 'Symptômes, examen', 'icon' => 'fa-user-doctor'],
                    ['value' => 'suivi', 'label' => 'Suivi', 'desc' => 'Renouvellement, chronique', 'icon' => 'fa-notes-medical'],
                    ['value' => 'soins', 'label' => 'Soins', 'desc' => 'Séance, acte', 'icon' => 'fa-hand-holding-medical'],
                    ['value' => 'enfant', 'label' => 'Enfant', 'desc' => 'Pédiatrie, vaccins', 'icon' => 'fa-baby'],
                    ['value' => 'certificat', 'label' => 'Certificat', 'desc' => 'Sport, aptitude', 'icon' => 'fa-file-medical'],
                    ['value' => 'autre', 'label' => 'Autre', 'desc' => 'Autre demande', 'icon' => 'fa-ellipsis'],
                ]],
                ['type' => 'toggle', 'key' => 'patient', 'label' => 'Vous êtes', 'options' => ['Déjà patient', 'Nouveau patient']],
            ]],
            ['title' => 'Disponibilités', 'icon' => 'fa-calendar', 'fields' => [
                $slots,
                ['type' => 'toggle', 'key' => 'mode', 'label' => 'Mode de consultation', 'options' => ['Au cabinet', 'Téléconsultation', 'Sans préférence']],
                ['type' => 'textarea', 'key' => 'precisions', 'label' => 'Précisions (optionnel)', 'placeholder' => "Ne mentionnez pas d'informations médicales sensibles ici."],
            ]],
            $contact,
        ],
    ],

    'beaute' => [
        'type' => 'rdv', 'cta' => 'Demander mon rendez-vous', 'delay' => 'Réponse rapide',
        'success' => 'Merci ! Nous vous confirmons votre créneau très vite.',
        'steps' => [
            ['title' => 'Prestation', 'icon' => 'fa-scissors', 'fields' => [
                ['type' => 'cards', 'key' => 'prestation', 'label' => 'Quelle prestation ?', 'required' => true, 'options' => [
                    ['value' => 'coupe', 'label' => 'Coupe & coiffage', 'desc' => 'Diagnostic offert', 'icon' => 'fa-scissors'],
                    ['value' => 'couleur', 'label' => 'Couleur / balayage', 'desc' => 'Éclat sur-mesure', 'icon' => 'fa-palette'],
                    ['value' => 'soin', 'label' => 'Soin', 'desc' => 'Visage, cheveux, corps', 'icon' => 'fa-spa'],
                    ['value' => 'ongles', 'label' => 'Ongles', 'desc' => 'Manucure, pose', 'icon' => 'fa-hand-sparkles'],
                    ['value' => 'epilation', 'label' => 'Épilation', 'desc' => 'Cire, laser', 'icon' => 'fa-feather'],
                    ['value' => 'evenement', 'label' => 'Événement', 'desc' => 'Mariage, soirée', 'icon' => 'fa-champagne-glasses'],
                ]],
                ['type' => 'toggle', 'key' => 'client', 'label' => 'Vous êtes', 'options' => ['Déjà client(e)', 'Nouveau / nouvelle']],
            ]],
            ['title' => 'Disponibilités', 'icon' => 'fa-calendar', 'fields' => [
                $slots,
                ['type' => 'textarea', 'key' => 'envies', 'label' => 'Vos envies (optionnel)', 'placeholder' => 'Longueur, couleur, inspiration…'],
            ]],
            $contact,
        ],
    ],

    'bienetre' => [
        'type' => 'rdv', 'cta' => 'Réserver ma séance', 'delay' => 'Réponse rapide',
        'success' => 'Merci ! Nous vous confirmons votre séance très vite.',
        'steps' => [
            ['title' => 'Votre séance', 'icon' => 'fa-spa', 'fields' => [
                ['type' => 'cards', 'key' => 'seance', 'label' => 'Quelle séance ?', 'required' => true, 'options' => [
                    ['value' => 'massage', 'label' => 'Massage', 'desc' => 'Relaxant, profond', 'icon' => 'fa-hands'],
                    ['value' => 'soin', 'label' => 'Soin', 'desc' => 'Visage, corps', 'icon' => 'fa-spa'],
                    ['value' => 'cours', 'label' => 'Cours / atelier', 'desc' => 'Yoga, pilates…', 'icon' => 'fa-person-praying'],
                    ['value' => 'cadeau', 'label' => 'Carte cadeau', 'desc' => 'Offrir un moment', 'icon' => 'fa-gift'],
                ]],
                ['type' => 'toggle', 'key' => 'duree', 'label' => 'Durée', 'options' => ['30 min', '60 min', '90 min', 'Je ne sais pas']],
            ]],
            ['title' => 'Disponibilités', 'icon' => 'fa-calendar', 'fields' => [$slots]],
            $contact,
        ],
    ],

    'renovation' => [
        'type' => 'devis', 'cta' => 'Recevoir mon devis gratuit', 'delay' => 'Devis sous 48h',
        'success' => 'Merci ! Nous étudions votre projet et vous recontactons sous 48h avec un devis.',
        'steps' => [
            ['title' => 'Votre projet', 'icon' => 'fa-house-chimney', 'fields' => [
                ['type' => 'cards', 'key' => 'travaux', 'label' => 'Type de travaux', 'required' => true, 'options' => [
                    ['value' => 'renovation', 'label' => 'Rénovation complète', 'desc' => 'Clé en main', 'icon' => 'fa-house-chimney'],
                    ['value' => 'isolation', 'label' => 'Isolation', 'desc' => 'Combles, murs, RGE', 'icon' => 'fa-temperature-low'],
                    ['value' => 'cuisine_sdb', 'label' => 'Cuisine / salle de bain', 'desc' => 'Conception & pose', 'icon' => 'fa-bath'],
                    ['value' => 'peinture', 'label' => 'Peinture / sols', 'desc' => 'Finitions', 'icon' => 'fa-paint-roller'],
                    ['value' => 'extension', 'label' => 'Extension / combles', 'desc' => 'Gagner de la surface', 'icon' => 'fa-up-right-and-down-left-from-center'],
                    ['value' => 'autre', 'label' => 'Autre', 'desc' => 'Dites-nous tout', 'icon' => 'fa-ellipsis'],
                ]],
                ['type' => 'toggle', 'key' => 'bien', 'label' => 'Type de bien', 'options' => ['Maison', 'Appartement', 'Local pro']],
                ['type' => 'number', 'key' => 'surface', 'label' => 'Surface concernée (m²)', 'placeholder' => '40', 'min' => 1],
            ]],
            ['title' => 'Budget & délai', 'icon' => 'fa-coins', 'fields' => [
                ['type' => 'toggle', 'key' => 'budget', 'label' => 'Budget envisagé', 'options' => ['< 5 000 €', '5 – 15 000 €', '15 – 50 000 €', '> 50 000 €', 'À définir']],
                ['type' => 'toggle', 'key' => 'delai', 'label' => 'Démarrage souhaité', 'options' => ['Dès que possible', 'Sous 3 mois', 'Sous 6 mois', 'Je me renseigne']],
                ['type' => 'textarea', 'key' => 'details', 'label' => 'Décrivez votre projet', 'placeholder' => 'État actuel, attentes, contraintes…'],
            ]],
            $contact,
        ],
    ],

    'artisan' => [
        'type' => 'devis', 'cta' => 'Demander mon devis', 'delay' => 'Réponse sous 24h',
        'success' => 'Merci ! Nous vous rappelons rapidement pour votre devis.',
        'steps' => [
            ['title' => 'Votre besoin', 'icon' => 'fa-screwdriver-wrench', 'fields' => [
                ['type' => 'cards', 'key' => 'besoin', 'label' => 'De quoi s\'agit-il ?', 'required' => true, 'options' => [
                    ['value' => 'depannage', 'label' => 'Dépannage urgent', 'desc' => 'Intervention rapide', 'icon' => 'fa-bolt'],
                    ['value' => 'installation', 'label' => 'Installation', 'desc' => 'Neuf ou remplacement', 'icon' => 'fa-screwdriver-wrench'],
                    ['value' => 'entretien', 'label' => 'Entretien', 'desc' => 'Contrat, visite', 'icon' => 'fa-clipboard-check'],
                    ['value' => 'devis', 'label' => 'Devis travaux', 'desc' => 'Projet à chiffrer', 'icon' => 'fa-file-invoice'],
                ]],
                ['type' => 'textarea', 'key' => 'details', 'label' => 'Décrivez la situation', 'placeholder' => 'Ce qui se passe, depuis quand, où…'],
            ]],
            ['title' => 'Quand ?', 'icon' => 'fa-calendar', 'fields' => [
                ['type' => 'toggle', 'key' => 'urgence', 'label' => 'Délai', 'options' => ['Urgent (aujourd\'hui)', 'Cette semaine', 'Ce mois-ci', 'Pas pressé']],
                $slots,
            ]],
            $contact,
        ],
    ],

    'automobile' => [
        'type' => 'devis', 'cta' => 'Demander un rendez-vous', 'delay' => 'Réponse sous 24h',
        'success' => 'Merci ! Nous vous recontactons pour confirmer le rendez-vous atelier.',
        'steps' => [
            ['title' => 'Intervention', 'icon' => 'fa-car', 'fields' => [
                ['type' => 'cards', 'key' => 'intervention', 'label' => 'Que faut-il faire ?', 'required' => true, 'options' => [
                    ['value' => 'entretien', 'label' => 'Entretien / révision', 'desc' => 'Vidange, filtres', 'icon' => 'fa-oil-can'],
                    ['value' => 'freins', 'label' => 'Freins / pneus', 'desc' => 'Plaquettes, montage', 'icon' => 'fa-circle-dot'],
                    ['value' => 'diagnostic', 'label' => 'Diagnostic', 'desc' => 'Voyant, bruit, panne', 'icon' => 'fa-magnifying-glass'],
                    ['value' => 'carrosserie', 'label' => 'Carrosserie', 'desc' => 'Choc, rayure', 'icon' => 'fa-car-burst'],
                    ['value' => 'ct', 'label' => 'Contrôle technique', 'desc' => 'Préparation, contre-visite', 'icon' => 'fa-clipboard-check'],
                    ['value' => 'autre', 'label' => 'Autre', 'desc' => 'Précisez', 'icon' => 'fa-ellipsis'],
                ]],
                ['type' => 'text', 'key' => 'vehicule', 'label' => 'Véhicule', 'placeholder' => 'Marque, modèle, année'],
                ['type' => 'text', 'key' => 'immat', 'label' => 'Immatriculation (optionnel)', 'placeholder' => 'AB-123-CD'],
            ]],
            ['title' => 'Disponibilités', 'icon' => 'fa-calendar', 'fields' => [$slots, ['type' => 'toggle', 'key' => 'attente', 'label' => 'Pendant l\'intervention', 'options' => ['J\'attends sur place', 'Je dépose le véhicule', 'Véhicule de courtoisie']]]],
            $contact,
        ],
    ],

    'avocat' => [
        'type' => 'rdv', 'cta' => 'Demander une consultation', 'delay' => 'Réponse sous 24h',
        'success' => 'Merci ! Le cabinet vous recontacte rapidement pour fixer une consultation.',
        'steps' => [
            ['title' => 'Votre situation', 'icon' => 'fa-scale-balanced', 'fields' => [
                ['type' => 'cards', 'key' => 'domaine', 'label' => 'Domaine concerné', 'required' => true, 'options' => [
                    ['value' => 'famille', 'label' => 'Famille', 'desc' => 'Divorce, garde, succession', 'icon' => 'fa-people-roof'],
                    ['value' => 'travail', 'label' => 'Travail', 'desc' => 'Licenciement, contrat', 'icon' => 'fa-briefcase'],
                    ['value' => 'immobilier', 'label' => 'Immobilier', 'desc' => 'Bail, copropriété, vente', 'icon' => 'fa-building'],
                    ['value' => 'penal', 'label' => 'Pénal', 'desc' => 'Défense, plainte', 'icon' => 'fa-gavel'],
                    ['value' => 'affaires', 'label' => 'Affaires', 'desc' => 'Sociétés, contrats', 'icon' => 'fa-handshake'],
                    ['value' => 'autre', 'label' => 'Autre', 'desc' => 'Précisez', 'icon' => 'fa-ellipsis'],
                ]],
                ['type' => 'toggle', 'key' => 'urgence', 'label' => 'Urgence', 'options' => ['Procédure en cours', 'Délai proche', 'Simple conseil']],
                ['type' => 'textarea', 'key' => 'resume', 'label' => 'Résumé de la situation', 'placeholder' => 'Quelques lignes suffisent, échange confidentiel.'],
            ]],
            ['title' => 'Consultation', 'icon' => 'fa-calendar', 'fields' => [['type' => 'toggle', 'key' => 'mode', 'label' => 'Mode', 'options' => ['Au cabinet', 'Visioconférence', 'Téléphone']], $slots]],
            $contact,
        ],
    ],

    'immobilier' => [
        'type' => 'devis', 'cta' => 'Recevoir mon estimation', 'delay' => 'Estimation sous 48h',
        'success' => 'Merci ! Un conseiller vous recontacte sous 48h avec une première estimation.',
        'steps' => [
            ['title' => 'Votre projet', 'icon' => 'fa-building', 'fields' => [
                ['type' => 'cards', 'key' => 'projet', 'label' => 'Quel est votre projet ?', 'required' => true, 'options' => [
                    ['value' => 'vendre', 'label' => 'Vendre', 'desc' => 'Estimation offerte', 'icon' => 'fa-tag'],
                    ['value' => 'acheter', 'label' => 'Acheter', 'desc' => 'Recherche sur-mesure', 'icon' => 'fa-key'],
                    ['value' => 'louer', 'label' => 'Louer / gérer', 'desc' => 'Gestion locative', 'icon' => 'fa-file-signature'],
                    ['value' => 'investir', 'label' => 'Investir', 'desc' => 'Conseil patrimonial', 'icon' => 'fa-chart-line'],
                ]],
                ['type' => 'toggle', 'key' => 'bien', 'label' => 'Type de bien', 'options' => ['Appartement', 'Maison', 'Terrain', 'Local / bureau']],
                ['type' => 'text', 'key' => 'localisation', 'label' => 'Ville / quartier', 'placeholder' => 'Aix-en-Provence, centre'],
                ['type' => 'number', 'key' => 'surface', 'label' => 'Surface (m²)', 'placeholder' => '75', 'min' => 1],
            ]],
            ['title' => 'Délai', 'icon' => 'fa-calendar', 'fields' => [['type' => 'toggle', 'key' => 'delai', 'label' => 'Horizon', 'options' => ['Dès maintenant', 'Sous 3 mois', 'Sous 6 mois', 'Je me renseigne']], ['type' => 'textarea', 'key' => 'details', 'label' => 'Précisions', 'placeholder' => 'Étage, état, particularités…']]],
            $contact,
        ],
    ],

    'assurance' => [
        'type' => 'devis', 'cta' => 'Recevoir mon devis', 'delay' => 'Devis sous 24h',
        'success' => 'Merci ! Votre devis personnalisé arrive sous 24h.',
        'steps' => [
            ['title' => 'À assurer', 'icon' => 'fa-shield-halved', 'fields' => [
                ['type' => 'cards', 'key' => 'contrat', 'label' => 'Que souhaitez-vous assurer ?', 'required' => true, 'options' => [
                    ['value' => 'auto', 'label' => 'Auto / moto', 'desc' => 'Tous risques, tiers', 'icon' => 'fa-car'],
                    ['value' => 'habitation', 'label' => 'Habitation', 'desc' => 'Locataire, propriétaire', 'icon' => 'fa-house'],
                    ['value' => 'sante', 'label' => 'Santé / prévoyance', 'desc' => 'Mutuelle, TNS', 'icon' => 'fa-heart-pulse'],
                    ['value' => 'pro', 'label' => 'Professionnel', 'desc' => 'RC pro, multirisque', 'icon' => 'fa-briefcase'],
                ]],
                ['type' => 'toggle', 'key' => 'situation', 'label' => 'Situation', 'options' => ['Déjà assuré(e)', 'Nouveau contrat', 'Comparer']],
                ['type' => 'textarea', 'key' => 'details', 'label' => 'Précisions', 'placeholder' => 'Véhicule, surface, effectif…'],
            ]],
            $contact,
        ],
    ],

    'comptable' => [
        'type' => 'devis', 'cta' => 'Demander un devis', 'delay' => 'Réponse sous 24h',
        'success' => 'Merci ! Nous vous proposons un premier échange gratuit très rapidement.',
        'steps' => [
            ['title' => 'Votre entreprise', 'icon' => 'fa-calculator', 'fields' => [
                ['type' => 'cards', 'key' => 'besoin', 'label' => 'Votre besoin', 'required' => true, 'options' => [
                    ['value' => 'creation', 'label' => 'Création', 'desc' => 'Statuts, immatriculation', 'icon' => 'fa-rocket'],
                    ['value' => 'compta', 'label' => 'Comptabilité', 'desc' => 'Tenue, bilan, liasse', 'icon' => 'fa-book'],
                    ['value' => 'paie', 'label' => 'Paie / social', 'desc' => 'Bulletins, déclarations', 'icon' => 'fa-users'],
                    ['value' => 'conseil', 'label' => 'Conseil fiscal', 'desc' => 'Optimisation', 'icon' => 'fa-lightbulb'],
                ]],
                ['type' => 'toggle', 'key' => 'forme', 'label' => 'Forme juridique', 'options' => ['Micro / EI', 'SAS / SASU', 'SARL / EURL', 'Association', 'Pas encore créée']],
                ['type' => 'toggle', 'key' => 'effectif', 'label' => 'Effectif', 'options' => ['0', '1 à 5', '6 à 20', '20+']],
            ]],
            $contact,
        ],
    ],

    'architecte' => [
        'type' => 'devis', 'cta' => 'Parler de mon projet', 'delay' => 'Réponse sous 48h',
        'success' => 'Merci ! Nous vous recontactons pour un premier échange sur votre projet.',
        'steps' => [
            ['title' => 'Votre projet', 'icon' => 'fa-compass-drafting', 'fields' => [
                ['type' => 'cards', 'key' => 'projet', 'label' => 'Nature du projet', 'required' => true, 'options' => [
                    ['value' => 'construction', 'label' => 'Construction neuve', 'desc' => 'Maison, bâtiment', 'icon' => 'fa-house-chimney'],
                    ['value' => 'renovation', 'label' => 'Rénovation', 'desc' => 'Restructuration', 'icon' => 'fa-hammer'],
                    ['value' => 'extension', 'label' => 'Extension', 'desc' => 'Surélévation, agrandissement', 'icon' => 'fa-up-right-and-down-left-from-center'],
                    ['value' => 'interieur', 'label' => 'Architecture intérieure', 'desc' => 'Aménagement, design', 'icon' => 'fa-couch'],
                ]],
                ['type' => 'number', 'key' => 'surface', 'label' => 'Surface (m²)', 'placeholder' => '120', 'min' => 1],
                ['type' => 'toggle', 'key' => 'budget', 'label' => 'Budget', 'options' => ['< 100 k€', '100 – 300 k€', '300 k€ +', 'À définir']],
                ['type' => 'textarea', 'key' => 'details', 'label' => 'Décrivez votre projet', 'placeholder' => 'Terrain, contraintes, envies…'],
            ]],
            $contact,
        ],
    ],

    'conseil' => [
        'type' => 'rdv', 'cta' => 'Réserver un échange', 'delay' => 'Premier échange offert',
        'success' => 'Merci ! Nous vous proposons un créneau d\'échange très rapidement.',
        'steps' => [
            ['title' => 'Votre enjeu', 'icon' => 'fa-lightbulb', 'fields' => [
                ['type' => 'cards', 'key' => 'enjeu', 'label' => 'Sur quoi avancer ?', 'required' => true, 'options' => [
                    ['value' => 'strategie', 'label' => 'Stratégie', 'desc' => 'Croissance, positionnement', 'icon' => 'fa-chess'],
                    ['value' => 'organisation', 'label' => 'Organisation', 'desc' => 'Process, efficacité', 'icon' => 'fa-sitemap'],
                    ['value' => 'commercial', 'label' => 'Commercial / marketing', 'desc' => 'Acquisition, offre', 'icon' => 'fa-bullhorn'],
                    ['value' => 'rh', 'label' => 'RH / management', 'desc' => 'Équipe, recrutement', 'icon' => 'fa-people-group'],
                ]],
                ['type' => 'toggle', 'key' => 'taille', 'label' => 'Taille de l\'entreprise', 'options' => ['Solo', '2 – 10', '11 – 50', '50+']],
                ['type' => 'textarea', 'key' => 'contexte', 'label' => 'Contexte en quelques mots', 'placeholder' => 'Situation, objectif, échéance…'],
            ]],
            ['title' => 'Échange', 'icon' => 'fa-calendar', 'fields' => [['type' => 'toggle', 'key' => 'mode', 'label' => 'Format', 'options' => ['Visio', 'Téléphone', 'Sur site']], $slots]],
            $contact,
        ],
    ],

    'commerce' => [
        'type' => 'contact', 'cta' => 'Envoyer', 'delay' => 'Réponse rapide',
        'success' => 'Merci ! Nous vous répondons très vite.',
        'steps' => [
            ['title' => 'Votre demande', 'icon' => 'fa-store', 'fields' => [
                ['type' => 'cards', 'key' => 'demande', 'label' => 'Comment pouvons-nous vous aider ?', 'required' => true, 'options' => [
                    ['value' => 'dispo', 'label' => 'Disponibilité d\'un produit', 'desc' => 'Stock, commande', 'icon' => 'fa-box'],
                    ['value' => 'commande', 'label' => 'Commande / réservation', 'desc' => 'Mettre de côté', 'icon' => 'fa-cart-shopping'],
                    ['value' => 'conseil', 'label' => 'Conseil', 'desc' => 'Besoin d\'aide pour choisir', 'icon' => 'fa-comments'],
                    ['value' => 'sav', 'label' => 'SAV / retour', 'desc' => 'Après achat', 'icon' => 'fa-rotate-left'],
                ]],
                ['type' => 'textarea', 'key' => 'details', 'label' => 'Précisez', 'placeholder' => 'Produit, référence, quantité…'],
            ]],
            $contact,
        ],
    ],

    'service' => [
        'type' => 'devis', 'cta' => 'Demander un devis', 'delay' => 'Réponse sous 24h',
        'success' => 'Merci ! Nous revenons vers vous rapidement.',
        'steps' => [
            ['title' => 'Votre besoin', 'icon' => 'fa-handshake', 'fields' => [
                ['type' => 'cards', 'key' => 'besoin', 'label' => 'Votre besoin', 'required' => true, 'options' => [
                    ['value' => 'devis', 'label' => 'Devis', 'desc' => 'Estimation gratuite', 'icon' => 'fa-file-invoice'],
                    ['value' => 'rdv', 'label' => 'Rendez-vous', 'desc' => 'Échange, visite', 'icon' => 'fa-calendar-check'],
                    ['value' => 'info', 'label' => 'Renseignement', 'desc' => 'Une question', 'icon' => 'fa-circle-question'],
                ]],
                ['type' => 'textarea', 'key' => 'details', 'label' => 'Décrivez votre besoin', 'placeholder' => 'Le plus de détails possible…'],
            ]],
            ['title' => 'Quand ?', 'icon' => 'fa-calendar', 'fields' => [['type' => 'toggle', 'key' => 'delai', 'label' => 'Délai', 'options' => ['Dès que possible', 'Cette semaine', 'Ce mois-ci', 'Je me renseigne']], $slots]],
            $contact,
        ],
    ],
];
