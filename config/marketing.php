<?php

/*
| Site vitrine joow.fr : navigation, fonctionnalités, tarifs, témoignages, FAQ.
| Les pages métiers viennent de config/sectors.php, les articles de resources/content/blog.
*/
return [
    'host' => env('MARKETING_HOST', 'joow.fr'),
    'app_url' => env('APP_URL', 'https://app.joow.fr'),

    'nav' => [
        ['label' => 'Fonctionnalités', 'route' => 'marketing.features'],
        ['label' => 'Métiers', 'route' => 'marketing.sectors'],
        ['label' => 'Exemples', 'route' => 'marketing.examples'],
        ['label' => 'Tarifs', 'route' => 'marketing.pricing'],
        ['label' => 'Blog', 'route' => 'marketing.blog'],
    ],

    'features' => [
        'generation-ia' => ['icon' => '✨', 'title' => 'Génération par IA en 90 secondes', 'tagline' => 'Votre fiche Google devient un site complet',
            'summary' => 'Tapez le nom de votre établissement : l\'IA lit votre fiche Google, vos avis et vos photos, détecte votre métier parmi 73 et rédige un site complet, cohérent avec ce que vos clients voient déjà de vous.',
            'bullets' => ['Détection du métier parmi 73 modèles pensés par secteur', 'Textes rédigés à partir de vos avis réels, jamais génériques', 'Photos analysées une par une : la bonne image au bon endroit', 'Couleurs extraites de votre logo ou de votre photo principale', 'Note et avis Google intégrés et mis à jour'],
            'image' => '/showcase/restaurant-full.webp'],
        'studio' => ['icon' => '✏️', 'title' => 'Le Studio : modifiez tout en quelques clics', 'tagline' => 'Cliquez un texte, une photo, une couleur : c\'est changé',
            'summary' => 'Pas d\'interface compliquée. Vous modifiez directement sur la page, comme sur un document. Textes, photos, couleurs, polices, ordre des sections, pages : tout est à portée de clic, sur mobile comme sur ordinateur.',
            'bullets' => ['Édition directe sur la page, sans compétence technique', 'Annulation en un clic de chaque modification', 'Pensé pour le mobile : vous retouchez votre site depuis votre téléphone', 'Pages supplémentaires : tarifs, réalisations, équipe, FAQ', 'Aperçu identique au site publié'],
            'image' => '/showcase/studio.webp'],
        'assistant-ia' => ['icon' => '🤖', 'title' => 'L\'assistant IA qui exécute vos demandes', 'tagline' => '« Ajoute une page Tarifs », « rends le ton plus chaleureux »',
            'summary' => 'Décrivez ce que vous voulez, en français, et l\'assistant le fait : créer une page, réécrire une section, passer en thème sombre, activer un module. Chaque action est annulable.',
            'bullets' => ['Compréhension de votre métier et de votre site', 'Création de pages complètes en une phrase', 'Réécriture ciblée : une section, un ton, une longueur', 'Historique et annulation de chaque action', '100 crédits par mois inclus en formule Pro'],
            'image' => '/showcase/studio.webp'],
        'reservation' => ['icon' => '🗓️', 'title' => 'Réservation et rendez-vous en ligne', 'tagline' => 'Vos clients réservent, vous êtes prévenu, ils reçoivent un rappel',
            'summary' => 'Table de restaurant, séjour, rendez-vous en cabinet ou en salon : le module de réservation s\'adapte à votre métier. Créneaux, confirmation, rappel automatique, annulation simple.',
            'bullets' => ['Formulaire en trois étapes adapté au métier', 'Confirmation immédiate par email, rappel la veille', 'Acompte ou empreinte bancaire possible via Stripe', 'Tableau des réservations dans votre espace', 'Synchronisation iCal pour les hébergements'],
            'image' => '/showcase/hebergement-full.webp'],
        'devis' => ['icon' => '📝', 'title' => 'Demande de devis intelligente', 'tagline' => 'Les bonnes questions, dans le bon ordre, pour chiffrer vite',
            'summary' => 'Un formulaire pensé par métier : type de prestation, urgence, disponibilités, coordonnées. Vous recevez une demande complète, prête à chiffrer, et votre client une confirmation claire.',
            'bullets' => ['Questions spécifiques à votre secteur', 'Réponse automatique et délai annoncé', 'Toutes les demandes dans votre espace, avec statut', 'Protection anti-spam intégrée', 'Modifiable dans le Studio : étapes, champs, options'],
            'image' => '/showcase/renovation-full.webp'],
        'chatbot' => ['icon' => '💬', 'title' => 'Un chatbot qui connaît votre activité', 'tagline' => 'Horaires, tarifs, prise de contact : il répond 24h/24',
            'summary' => 'Nourri par vos informations réelles (prestations, horaires, zone, FAQ), l\'assistant répond à vos visiteurs la nuit comme le dimanche, et transmet les demandes sérieuses.',
            'bullets' => ['Réponses basées uniquement sur vos informations', 'Consignes personnalisables : ton, limites, redirections', 'Capture des coordonnées quand c\'est pertinent', 'Activation en un clic, sans configuration', 'Position et couleurs adaptées au site'],
            'image' => '/showcase/sante-full.webp'],
        'avis-google' => ['icon' => '⭐', 'title' => 'Vos avis Google en vitrine', 'tagline' => 'La preuve sociale, mise à jour toute seule',
            'summary' => 'Votre note, le nombre d\'avis et les meilleurs témoignages apparaissent sur le site, en haut de page et dans une section dédiée. Vous choisissez le seuil et le nombre d\'avis affichés.',
            'bullets' => ['Note et nombre d\'avis en haut de page', 'Sélection des avis par note minimale', 'Mise à jour automatique depuis Google', 'Bouton « Laisser un avis » vers votre fiche', 'Compatible avec tous les métiers'],
            'image' => '/showcase/beaute-full.webp'],
        'refonte' => ['icon' => '↻', 'title' => 'Refonte de votre site existant', 'tagline' => 'Collez son adresse : on garde le fond, on refait la forme',
            'summary' => 'L\'IA lit vos pages, conserve vos textes, prix, photos et logo, retrouve votre fiche Google pour y ajouter vos avis, et reconstruit un site moderne avec ses sous-pages. Vous comparez avant / après.',
            'bullets' => ['Lecture automatique de jusqu\'à 10 pages', 'Textes réécrits, faits conservés', 'Photos et logo rapatriés et réutilisés', 'Sous-pages reconstruites : services, tarifs, équipe, réalisations', 'Comparatif avant / après immédiat'],
            'image' => '/showcase/immobilier-full.webp'],
        'sans-fiche-google' => ['icon' => '✎', 'title' => 'Pas de fiche Google ? Cinq questions suffisent', 'tagline' => 'Nom, métier, coordonnées, prestations, photos : une minute',
            'summary' => 'Vous démarrez votre activité ou vous n\'avez pas de fiche Google : un questionnaire court, des suggestions de prestations par l\'IA, vos photos ou des visuels professionnels de votre métier, et le site se génère.',
            'bullets' => ['Cinq écrans, une minute', 'Suggestions de prestations et points forts par métier', 'Photos téléversées depuis le téléphone', 'Banque d\'images professionnelle si vous n\'avez pas de photo', 'Rattachement de votre fiche Google plus tard, en un clic'],
            'image' => '/showcase/beaute-mobile.webp'],
        'domaine' => ['icon' => '🌐', 'title' => 'Votre nom de domaine, certificat inclus', 'tagline' => 'votre-nom.fr en quelques minutes',
            'summary' => 'Votre site est livré sur une adresse joow.fr. Branchez un domaine que vous possédez déjà ou commandez-en un depuis votre espace : DNS guidés, certificat SSL automatique, redirection www.',
            'bullets' => ['Domaine existant : deux enregistrements DNS à copier', 'Commande d\'un nouveau domaine en deux minutes', 'HTTPS automatique et renouvelé', 'Redirections propres, aucun contenu dupliqué', 'Email de confirmation à chaque étape'],
            'image' => '/showcase/immobilier.webp'],
        'seo-local' => ['icon' => '📍', 'title' => 'Référencement local intégré', 'tagline' => 'Trouvé par les gens de votre ville, pas par le monde entier',
            'summary' => 'Chaque site est construit pour être compris par Google : titres, descriptions, données structurées de votre établissement, horaires, carte, vitesse de chargement et affichage mobile exemplaires.',
            'bullets' => ['Balises et données structurées automatiques', 'Pages rapides, images optimisées', 'Lien direct avec votre fiche Google', 'Sitemap et indexation gérés', 'Contenus spécifiques, jamais dupliqués entre clients'],
            'image' => '/showcase/sante.webp'],
        'mobile' => ['icon' => '📱', 'title' => 'Mobile d\'abord', 'tagline' => '8 clients sur 10 vous découvrent sur un téléphone',
            'summary' => 'Bouton d\'appel toujours visible, formulaires pensés pour le pouce, photos compressées, chargement en moins de deux secondes : le site est conçu pour le mobile avant l\'ordinateur. Le Studio aussi.',
            'bullets' => ['Appel et réservation en un tap', 'Barre d\'actions fixe en bas d\'écran', 'Images adaptées à la taille de l\'écran', 'Studio utilisable entièrement sur mobile', 'Testé sur iPhone et Android'],
            'image' => '/showcase/restaurant-mobile.webp'],
    ],

    'pricing' => [
        'pro' => ['name' => 'Pro', 'price' => '39', 'period' => 'HT / mois', 'note' => 'Sans engagement, résiliable en un clic',
            'items' => ['Site complet en ligne sur votre adresse', 'Tous les modules inclus', '100 crédits IA par mois pour l\'assistant', 'Nom de domaine personnalisé', 'Hébergement, HTTPS, sauvegardes', 'Modifications illimitées dans le Studio', 'Support par email']],
        'liberte' => ['name' => 'Liberté', 'price' => '349', 'period' => 'HT, une fois', 'note' => 'Votre site pour toujours, sans abonnement',
            'items' => ['Tout ce que contient la formule Pro', '100 crédits IA offerts', 'Hébergement inclus sans mensualité', 'Nom de domaine personnalisé', 'Mises à jour de la plateforme incluses', 'Recharges de crédits IA à la demande']],
    ],

    'testimonials' => [
        ['q' => 'En 2 minutes j\'avais un site plus beau que celui que je payais 1 500 €. Mes clientes réservent en ligne, je ne rate plus un appel.', 'a' => 'Sophie M.', 'r' => 'Institut de beauté, Lyon'],
        ['q' => 'Je pensais que ce serait compliqué. J\'ai cherché mon restaurant, cliqué, c\'était fait. Les réservations tombent toutes seules.', 'a' => 'Karim B.', 'r' => 'Restaurant, Marseille'],
        ['q' => 'Les avis Google intégrés, le formulaire de devis, tout y était. J\'ai publié le soir même et reçu 3 demandes dans la semaine.', 'a' => 'Laurent D.', 'r' => 'Rénovation, Toulouse'],
    ],

    'faq' => [
        ['q' => 'Combien ça coûte ?', 'a' => 'La création et l\'aperçu sont 100 % gratuits, sans carte bancaire. Vous ne payez que si vous publiez : formule Pro à 39 € HT par mois, sans engagement, ou formule Liberté à 349 € HT une fois, sans abonnement.'],
        ['q' => 'Les modules (réservation, devis, assistant IA…) sont-ils inclus ?', 'a' => 'Oui, tous les modules sont inclus dans les deux formules et s\'activent en un clic depuis le Studio. Les paiements en ligne passent par votre propre compte Stripe.'],
        ['q' => 'Ai-je besoin de compétences techniques ?', 'a' => 'Aucune. Vous cherchez votre établissement, l\'IA fait le reste. Ensuite vous cliquez sur un texte ou une image pour le modifier, ou vous demandez à l\'assistant.'],
        ['q' => 'D\'où viennent les textes et les photos ?', 'a' => 'De votre fiche Google (avis, photos, horaires) et de votre site existant si vous en avez un, enrichis par une IA qui rédige pour votre métier. Sans photo, une banque d\'images professionnelle prend le relais. Vous remplacez tout ce que vous voulez.'],
        ['q' => 'Et si je n\'ai pas de fiche Google ?', 'a' => 'Un questionnaire de cinq écrans suffit : nom, métier, coordonnées, prestations, photos. Vous pourrez rattacher votre fiche plus tard pour afficher vos avis.'],
        ['q' => 'Puis-je utiliser mon propre nom de domaine ?', 'a' => 'Oui. Le site est livré sur une adresse joow.fr, et vous branchez votre domaine (.fr, .com…) à tout moment, certificat SSL inclus. Vous pouvez aussi en commander un depuis votre espace.'],
        ['q' => 'Le site m\'appartient-il ?', 'a' => 'Les textes, photos et contenus sont les vôtres. En formule Liberté, le site reste en ligne sans abonnement. En formule Pro, vous résiliez quand vous voulez.'],
        ['q' => 'Et si je ne suis pas satisfait ?', 'a' => 'Vous ne payez rien tant que vous n\'avez pas décidé de publier. La formule Pro est résiliable en un clic, sans frais.'],
    ],

    'examples' => [
        ['sector' => 'restaurant', 'name' => 'La Table d\'Émile', 'city' => 'Lyon', 'img' => '/showcase/restaurant.webp', 'full' => '/showcase/restaurant-full.webp', 'mobile' => '/showcase/restaurant-mobile.webp'],
        ['sector' => 'beaute', 'name' => 'Institut Léa', 'city' => 'Bordeaux', 'img' => '/showcase/beaute.webp', 'full' => '/showcase/beaute-full.webp', 'mobile' => '/showcase/beaute-mobile.webp'],
        ['sector' => 'renovation', 'name' => 'Rénov\'Habitat', 'city' => 'Toulouse', 'img' => '/showcase/renovation.webp', 'full' => '/showcase/renovation-full.webp', 'mobile' => '/showcase/renovation-mobile.webp'],
        ['sector' => 'sante', 'name' => 'Cabinet Dr Morel', 'city' => 'Nantes', 'img' => '/showcase/sante.webp', 'full' => '/showcase/sante-full.webp', 'mobile' => '/showcase/sante-mobile.webp'],
        ['sector' => 'immobilier', 'name' => 'Agence Horizon', 'city' => 'Lille', 'img' => '/showcase/immobilier.webp', 'full' => '/showcase/immobilier-full.webp', 'mobile' => '/showcase/immobilier-mobile.webp'],
        ['sector' => 'hebergement', 'name' => 'Le Clos des Vignes', 'city' => 'Beaune', 'img' => '/showcase/hebergement.webp', 'full' => '/showcase/hebergement-full.webp', 'mobile' => '/showcase/hebergement-mobile.webp'],
    ],

    // Argumentaires par famille de métiers (pages « Site internet pour … »)
    'families' => [
        'food' => ['pitch' => 'Vos clients choisissent où manger en trente secondes, sur leur téléphone, souvent depuis Google Maps. Un site qui montre l\'ambiance, la carte, les avis et un bouton « Réserver » fait la différence entre un passage et une table pleine.', 'proof' => 'photos de la salle et des plats, carte à jour, réservation en ligne, avis Google'],
        'stay' => ['pitch' => 'Un voyageur compare plusieurs adresses avant de réserver. Des photos qui donnent envie, des chambres claires, les alentours et une réservation directe sans commission : c\'est ce que votre site doit faire, et c\'est ce que Joow construit.', 'proof' => 'chambres et tarifs, galerie, disponibilités, réservation directe'],
        'care' => ['pitch' => 'Avant de prendre rendez-vous, on cherche à être rassuré : qui est le praticien, quels soins, où, à quel prix, que disent les patients. Votre site répond à tout cela en une page claire, avec la prise de rendez-vous en ligne.', 'proof' => 'présentation du praticien, soins et tarifs, prise de rendez-vous, avis'],
        'trade' => ['pitch' => 'Un particulier qui a une fuite ou un projet de travaux appelle le premier professionnel qui inspire confiance. Vos réalisations, votre zone d\'intervention, un devis en ligne et vos avis Google : votre site travaille pendant que vous êtes sur le chantier.', 'proof' => 'prestations et prix, réalisations en photos, zone d\'intervention, devis en ligne'],
        'shop' => ['pitch' => 'Vos clients veulent savoir si vous êtes ouvert, ce que vous proposez et pourquoi venir chez vous plutôt qu\'ailleurs. Un site qui montre votre sélection, vos horaires et vos avis, avec une commande ou un click & collect, fait venir en boutique.', 'proof' => 'horaires, sélection et nouveautés, commande ou click & collect, avis'],
        'b2b' => ['pitch' => 'Un prospect vous cherche sur Google avant de répondre à votre email. Une page claire sur vos offres, vos résultats, votre méthode et vos références transforme une recherche en premier échange.', 'proof' => 'offres et méthode, réalisations, témoignages, prise de contact'],
        'local' => ['pitch' => 'Être trouvé, inspirer confiance, être contacté facilement : votre site fait les trois, à partir de vos informations réelles, et reste simple à faire vivre.', 'proof' => 'prestations, photos, horaires, prise de contact'],
    ],
];
