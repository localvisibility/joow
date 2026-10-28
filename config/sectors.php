<?php

/*
|--------------------------------------------------------------------------
| Catalogue des métiers : design system + brief IA par secteur
|--------------------------------------------------------------------------
| label    nom lisible                      color   accent principal (hex)
| icon     Font Awesome (fa-…)              cta     libellé du bouton principal
| font     police des titres (Google Fonts) theme   dark | light
| hero     editorial | split | center       order   ordre par défaut des sections de l'accueil
| modules  modules activés à la création    stock   photos de secours haut de gamme (Unsplash)
| keywords mots-clés (nom + types Google, sans accents) pour la détection
| brief    consignes métier pour le rédacteur IA (positionnement, services typiques, ce que
|          cherche le visiteur, vocabulaire) — c'est ce qui rend chaque modèle « pensé ».
| Le contenu texte, lui, est généré par l'IA à partir de la fiche Google et des photos.
*/
$u = fn (string $id) => "https://images.unsplash.com/photo-$id?w=1920&q=82&auto=format&fit=crop";

$svc = ['services', 'process', 'about', 'gallery', 'reviews', 'faq', 'booking', 'cta', 'contact'];        // artisans & services : prestations → méthode → preuve
$shop = ['about', 'services', 'gallery', 'reviews', 'faq', 'booking', 'cta', 'contact'];                   // commerces : l'univers d'abord
$care = ['services', 'about', 'process', 'reviews', 'faq', 'booking', 'cta', 'contact'];                   // santé / bien-être : soins → praticien → rassurance
$food = ['menu', 'services', 'gallery', 'about', 'reviews', 'booking', 'faq', 'cta', 'contact'];           // restauration : la carte et l'ambiance
$stay = ['rooms', 'gallery', 'services', 'about', 'reviews', 'booking', 'faq', 'cta', 'contact'];          // hébergement : chambres et lieu
$b2b  = ['services', 'gallery', 'process', 'about', 'reviews', 'faq', 'booking', 'cta', 'contact'];        // agences & prestataires B2B : offre → réalisations → méthode

$bookingOn = ['booking' => true, 'bot' => ['enabled' => true], 'reviews' => ['enabled' => true, 'min_rating' => 4, 'count' => 6]];
$tableOn = ['booking' => true, 'restaurant' => ['enabled' => true], 'bot' => ['enabled' => true], 'reviews' => ['enabled' => true, 'min_rating' => 4, 'count' => 6]];

return [

    /* ───────────── Restauration & hospitalité ───────────── */

    'restaurant' => ['label' => 'Restaurant', 'color' => '#e11d48', 'icon' => 'fa-utensils', 'cta' => 'Réserver une table',
        'font' => 'Playfair Display', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $food, 'modules' => $tableOn,
        'keywords' => ['restaurant', 'bistro', 'brasserie', 'pizz', 'trattoria', 'sushi', 'bouillon', 'auberge', 'grill', 'meal_takeaway', 'meal_delivery', 'food', 'creperie', 'crêperie', 'kebab', 'burger', 'tacos', 'cantine', 'gastronom'],
        'brief' => "Site de restaurant : on vend une expérience (ambiance, cuisine, moments) avant des plats. Services = formules et moments (déjeuner, dîner, brunch, menu dégustation, groupes/privatisation, vente à emporter, terrasse) avec des prix réalistes. FAQ : réservation, groupes, allergènes/options végétariennes, parking, enfants, terrasse, moyens de paiement. Ton : gourmand, précis, jamais pompeux. Parler de carte, de produits, de saison, du chef si les avis le citent.",
        'stock' => [$u('1517248135467-4c7edcad34c4'), $u('1414235077428-338989a2e8c0'), $u('1552566626-52f8b828add9'), $u('1559339352-11d035aa65de')]],

    'bar' => ['label' => 'Bar & café', 'color' => '#b45309', 'icon' => 'fa-martini-glass-citrus', 'cta' => 'Réserver / privatiser',
        'font' => 'Bricolage Grotesque', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $food, 'modules' => $tableOn,
        'keywords' => ['bar', 'cafe', 'café', 'pub', 'coffee', 'night_club', 'cocktail', 'brewery', 'brasserie artisanale', 'salon de the', 'tea', 'lounge', 'bar à vin', 'wine_bar'],
        'brief' => "Bar, café ou pub : convivialité, horaires (happy hour, soirées), carte des boissons (cocktails, bières, cafés de spécialité), petite restauration, événements (concerts, quiz, retransmissions), privatisation et groupes. FAQ : happy hour, privatisation, carte d'identité, animaux, terrasse, réservation. Ton : vivant, direct, un peu complice.",
        'stock' => [$u('1514362545857-3bc16c4c7d1b'), $u('1470337458703-46ad1756a187'), $u('1572116469696-31de0f17cc34'), $u('1559925393-8be0ec4767c8')]],

    'boulangerie' => ['label' => 'Boulangerie & pâtisserie', 'color' => '#d97706', 'icon' => 'fa-bread-slice', 'cta' => 'Commander',
        'font' => 'Fraunces', 'theme' => 'light', 'hero' => 'bright', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['boulanger', 'patisser', 'pâtisser', 'bakery', 'viennoiserie', 'chocolat', 'confiserie', 'glacier', 'ice_cream', 'biscuiterie', 'traiteur sucre'],
        'brief' => "Boulangerie-pâtisserie : savoir-faire artisanal, fournil, farines, fermentation, horaires d'ouverture tôt, gâteaux sur commande (anniversaires, mariages), pièces montées, viennoiseries, sandwichs du midi, snacking. Services = gammes et commandes (pain spécial, gâteau personnalisé, buffet/traiteur, formule déjeuner). FAQ : commandes et délais, allergènes, sans gluten, livraison, horaires, paiement. Ton : chaleureux, gourmand, concret (produits nommés).",
        'stock' => [$u('1509440159596-0249088772ff'), $u('1549931319-a545dcf3bc73'), $u('1517433670267-08bbd4be890f'), $u('1558961363-fa8fdf82db35')]],

    'traiteur' => ['label' => 'Traiteur & événementiel culinaire', 'color' => '#9f1239', 'icon' => 'fa-cake-candles', 'cta' => 'Demander un devis',
        'font' => 'Cormorant Garamond', 'theme' => 'light', 'hero' => 'editorial', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['traiteur', 'caterer', 'catering', 'food truck', 'chef a domicile', 'chef à domicile', 'cocktail dinatoire', 'buffet'],
        'brief' => "Traiteur : mariages, séminaires, cocktails, repas d'entreprise, chef à domicile, plateaux-repas. Services = formats d'événements avec fourchette de prix par personne. Parcours = devis → dégustation → jour J. FAQ : nombre d'invités minimum, dégustation, service et personnel, vaisselle et matériel, régimes spéciaux, zone d'intervention, acompte. Ton : raffiné et rassurant (on confie un jour important).",
        'stock' => [$u('1555244162-803834f70033'), $u('1464366400600-7168b8af9bc3'), $u('1414235077428-338989a2e8c0'), $u('1478145046317-39f10e56b5e9')]],

    'hebergement' => ['label' => 'Hébergement', 'color' => '#0d9488', 'icon' => 'fa-bed', 'cta' => 'Réserver un séjour',
        'font' => 'Cormorant Garamond', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $stay, 'modules' => $bookingOn,
        'keywords' => ['hotel', 'hôtel', 'lodging', 'gite', 'gîte', 'camping', 'chambre d', 'chambres d', 'maison d', 'bed_and_breakfast', 'guest_house', 'auberge de jeunesse', 'hostel', 'residence', 'villa', 'location saisonniere', 'rv_park', 'campground'],
        'brief' => "Hôtel, gîte, chambres d'hôtes ou camping : le lieu, les chambres, le petit-déjeuner, les alentours (que faire, à quelle distance), les équipements (parking, piscine, spa, wifi, animaux). Services = types de chambres/séjours, petit-déjeuner, offres (week-end, longue durée, séminaire). FAQ : horaires d'arrivée/départ, annulation, animaux, parking, petit-déjeuner, accès PMR. Ton : élégant, apaisant, concret sur le confort et la localisation.",
        'stock' => [$u('1566073771259-6a8506099945'), $u('1582719478250-c89cae4dc85b'), $u('1520250497591-112f2f40a3f4'), $u('1571896349842-33c89424de2d')]],

    /* ───────────── Beauté, bien-être, sport ───────────── */

    'coiffeur' => ['label' => 'Salon de coiffure', 'color' => '#c026d3', 'icon' => 'fa-scissors', 'cta' => 'Prendre rendez-vous',
        'font' => 'DM Serif Display', 'theme' => 'light', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['coiffeur', 'coiffure', 'hair_care', 'hair_salon', 'hairdresser', 'salon de coiffure', 'colorist', 'extension'],
        'brief' => "Salon de coiffure : coupe femme/homme/enfant, couleur, balayage, mèches, lissage, soins (kératine, botox capillaire), coiffure mariage, barbe. Prix réalistes « à partir de ». FAQ : sans rendez-vous, durée des prestations, produits utilisés (vegan, sans ammoniaque), diagnostic offert, enfants, parking. Ton : stylé, bienveillant, expert sans jargon.",
        'stock' => [$u('1560066984-138dadb4c035'), $u('1562322140-8baeececf3df'), $u('1521590832167-7bcbfaa6381f'), $u('1600948836101-f9ffda59d250')]],

    'barbier' => ['label' => 'Barbier', 'color' => '#92400e', 'icon' => 'fa-user-tie', 'cta' => 'Réserver un créneau',
        'font' => 'Bricolage Grotesque', 'theme' => 'dark', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['barbier', 'barber', 'barbershop', 'barber_shop', 'rasage'],
        'brief' => "Barbier : coupe homme, dégradé, taille et rasage de barbe à l'ancienne (serviette chaude), soins visage, forfaits père-fils, abonnements. FAQ : sans rendez-vous, durée, produits, enfants, tarifs forfaits. Ton : masculin, détendu, précis sur le geste et l'ambiance (musique, café offert si les avis le disent).",
        'stock' => [$u('1503951914875-452162b0f3f1'), $u('1621605815971-fbc98d665033'), $u('1585747860715-2ba37e788b70'), $u('1599351431202-1e0f0137899a')]],

    'beaute' => ['label' => 'Institut de beauté', 'color' => '#db2777', 'icon' => 'fa-hand-sparkles', 'cta' => 'Prendre rendez-vous',
        'font' => 'DM Serif Display', 'theme' => 'light', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['esthet', 'beauty_salon', 'institut', 'onglerie', 'nail', 'manucure', 'pedicure', 'epilation', 'épilation', 'cils', 'sourcils', 'maquillage', 'make up', 'make-up', 'bronzage', 'minceur'],
        'brief' => "Institut de beauté / onglerie : soins visage, épilation (cire, lumière pulsée), manucure et pose d'ongles (gel, semi-permanent), extensions de cils, maquillage, forfaits mariée, bons cadeaux. FAQ : durée, contre-indications, hygiène, bons cadeaux, annulation, produits/marques. Ton : doux, premium, précis sur les techniques.",
        'stock' => [$u('1570172619644-dfd03ed5d881'), $u('1596462502278-27bfdc403348'), $u('1487412947147-5cebf100ffc2'), $u('1519415387722-a1c3bbef716c')]],

    'tatoueur' => ['label' => 'Studio de tatouage', 'color' => '#111827', 'icon' => 'fa-pen-nib', 'cta' => 'Proposer un projet',
        'font' => 'Bricolage Grotesque', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['tatou', 'tattoo', 'piercing', 'encre'],
        'brief' => "Studio de tatouage / piercing : styles (fin, réaliste, old school, japonais, géométrique…), projets sur mesure, flashs, cover, piercing, hygiène et normes. Parcours = projet → rendez-vous conseil → séance → cicatrisation. FAQ : prix (minimum, taux horaire), acompte, douleur, âge minimum, cicatrisation et soins, retouches. Ton : artistique, franc, sécurisant sur l'hygiène.",
        'stock' => [$u('1598371839696-5c5bb00bdc28'), $u('1565058379802-bbe93b2f703a'), $u('1611501275019-9b5cda994e8d'), $u('1562962230-16e4623d36e6')]],

    'bienetre' => ['label' => 'Bien-être & relaxation', 'color' => '#7c3aed', 'icon' => 'fa-spa', 'cta' => 'Réserver une séance',
        'font' => 'Cormorant Garamond', 'theme' => 'light', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['spa', 'massage', 'masseur', 'sophrolog', 'hypno', 'naturopath', 'reflexolog', 'réflexolog', 'reiki', 'relaxation', 'bien-etre', 'bien etre', 'hammam', 'sauna', 'thalasso', 'ayurved'],
        'brief' => "Bien-être : massages (suédois, deep tissue, prénatal, pierres chaudes), rituels spa, sophrologie, naturopathie, réflexologie. Services = séances avec durée et prix. FAQ : première séance, contre-indications, tenue, remboursement mutuelle (si applicable), bons cadeaux, annulation. Ton : apaisant, sensoriel, sans promesse médicale.",
        'stock' => [$u('1544161515-4ab6ce6db874'), $u('1540555700478-4be289fbecef'), $u('1519823551278-64ac92734fb1'), $u('1600334129128-685c5582fd35')]],

    'sport' => ['label' => 'Salle de sport & coaching', 'color' => '#ea580c', 'icon' => 'fa-dumbbell', 'cta' => 'Séance d\'essai',
        'font' => 'Sora', 'theme' => 'dark', 'hero' => 'poster', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['gym', 'fitness', 'salle de sport', 'crossfit', 'coach sportif', 'personal trainer', 'musculation', 'pilates', 'yoga', 'boxe', 'boxing', 'escalade', 'danse', 'dance', 'arts martiaux', 'judo', 'karate', 'natation', 'swimming', 'tennis', 'padel', 'golf', 'equitation', 'équitation', 'stadium'],
        'brief' => "Salle de sport, studio ou coach : cours collectifs, coaching personnalisé, programmes (remise en forme, perte de poids, préparation), horaires d'ouverture larges, abonnements sans engagement, séance d'essai. Services = formules et programmes avec prix mensuels. FAQ : séance d'essai, engagement, horaires, niveau débutant, vestiaires/douches, parking. Ton : énergique, motivant, inclusif.",
        'stock' => [$u('1534438327276-14e5300c3a48'), $u('1571019613454-1cb2f99b2d8b'), $u('1517836357463-d25dfeac3438'), $u('1574680096145-d05b474e2155')]],

    /* ───────────── Santé ───────────── */

    'sante' => ['label' => 'Cabinet médical', 'color' => '#0d9488', 'icon' => 'fa-stethoscope', 'cta' => 'Prendre rendez-vous',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['medecin', 'médecin', 'doctor', 'generaliste', 'généraliste', 'cabinet medical', 'infirmier', 'sage-femme', 'sage femme', 'podolog', 'pedicure', 'dermato', 'cardio', 'pediatr', 'pédiatr', 'orthophon', 'orthopti', 'dieteticien', 'diététicien', 'nutrition', 'laboratoire', 'radiolog', 'health', 'hospital', 'clinique', 'centre medical', 'medical'],
        'brief' => "Cabinet de santé : spécialités, motifs de consultation, modalités (sur rendez-vous, urgences, téléconsultation, visites à domicile), conventionnement et remboursement, accessibilité. Services = consultations et actes, sans prix inventés (indiquer « secteur 1 / tarif conventionné » seulement si les avis ou la fiche le disent, sinon « sur demande »). FAQ : carte Vitale, délais, nouveaux patients, annulation, documents à apporter. Ton : clair, rassurant, sobre ; aucune promesse de résultat, respect des règles déontologiques (pas de publicité comparative).",
        'stock' => [$u('1519494026892-80bbd2d6fd0d'), $u('1576091160550-2173dba999ef'), $u('1512678080530-7760d81faba6'), $u('1538108149393-fbbd81895907')]],

    'dentiste' => ['label' => 'Cabinet dentaire', 'color' => '#0284c7', 'icon' => 'fa-tooth', 'cta' => 'Prendre rendez-vous',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['dentist', 'dentaire', 'orthodont', 'implant', 'stomato', 'parodont'],
        'brief' => "Cabinet dentaire : soins courants, prévention et détartrage, esthétique (blanchiment, facettes), implants, orthodontie (adultes/enfants), urgences dentaires, prise en charge de la peur du dentiste. FAQ : urgence, devis et remboursement (mutuelle, 100 % santé), douleur, première visite, enfants. Ton : rassurant, pédagogue, propre ; pas de prix inventés.",
        'stock' => [$u('1588776814546-1ffcf47267a5'), $u('1606811841689-23dfddce3e95'), $u('1629909613654-28e377c37b09'), $u('1598256989800-fe5f95da9787')]],

    'kine' => ['label' => 'Kinésithérapie & ostéopathie', 'color' => '#059669', 'icon' => 'fa-person-walking', 'cta' => 'Prendre rendez-vous',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['kine', 'kiné', 'kinesi', 'kinési', 'physiotherapist', 'physio', 'osteo', 'ostéo', 'chiropract', 'etiopath', 'étiopath', 'posturolog'],
        'brief' => "Kiné / ostéo : motifs (dos, sport, post-opératoire, pédiatrie, périnée, respiratoire), techniques (thérapie manuelle, rééducation, ondes de choc…), prise en charge sportifs, à domicile. FAQ : ordonnance ou non, remboursement, durée, tenue, première séance, délais. Ton : expert et humain, sans jargon excessif, aucune promesse de guérison.",
        'stock' => [$u('1518611012118-696072aa579a'), $u('1571019614242-c5c5dee9f50b'), $u('1600334129128-685c5582fd35'), $u('1552196563-55cd4e45efb3')]],

    'psy' => ['label' => 'Psychologie & thérapie', 'color' => '#6366f1', 'icon' => 'fa-brain', 'cta' => 'Prendre rendez-vous',
        'font' => 'Lora', 'theme' => 'light', 'hero' => 'center', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['psycho', 'psychiat', 'therapeute', 'thérapeute', 'psychanaly', 'tcc', 'emdr', 'coach de vie', 'psychomot'],
        'brief' => "Psychologue / thérapeute : approches (TCC, EMDR, systémique, humaniste…), publics (adultes, ados, enfants, couples), motifs (anxiété, burn-out, deuil, confiance…), cadre (cabinet, visio), confidentialité. FAQ : première séance, durée et fréquence, tarif et remboursement (MonPsy, mutuelles), annulation, visio. Ton : doux, respectueux, non intrusif, jamais culpabilisant.",
        'stock' => [$u('1573497019940-1c28c88b4f3e'), $u('1497215728101-856f4ea42174'), $u('1516534775068-ba3e7458af70'), $u('1493612276216-ee3925520721')]],

    'veterinaire' => ['label' => 'Clinique vétérinaire', 'color' => '#16a34a', 'icon' => 'fa-paw', 'cta' => 'Prendre rendez-vous',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['veterinaire', 'vétérinaire', 'veterinary', 'veterinary_care', 'clinique vet', 'cabinet vet', 'animal_hospital'],
        'brief' => "Vétérinaire (ou toilettage, pension, éducateur) : consultations, vaccins, chirurgie, urgences, imagerie, dentisterie, NAC, conseils nutrition, toilettage. FAQ : urgences et horaires, première visite, tarifs indicatifs ou devis, assurance animaux, stérilisation, transport de l'animal. Ton : empathique (le visiteur est inquiet pour son compagnon), pratique.",
        'stock' => [$u('1548767797-d8c844163c4c'), $u('1548199973-03cce0bbc87b'), $u('1583337130417-3346a1be7dee'), $u('1450778869180-41d0601e046e')]],

    'opticien' => ['label' => 'Opticien', 'color' => '#1d4ed8', 'icon' => 'fa-glasses', 'cta' => 'Prendre rendez-vous',
        'font' => 'Outfit', 'theme' => 'light', 'hero' => 'split', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['opticien', 'optique', 'optician', 'lunette', 'audioprothes', 'audioprothés', 'audition', 'lentille'],
        'brief' => "Opticien (ou audioprothésiste) : montures et marques, verres (progressifs, anti-lumière bleue), lentilles, examen de vue, 100 % santé, tiers payant, ajustage et SAV, solaires. FAQ : ordonnance, remboursement et mutuelle, délais, essai lentilles, garantie casse. Ton : conseil, mode et technique, rassurant sur le budget.",
        'stock' => [$u('1574258495973-f010dfbb5371'), $u('1511499767150-a48a237f0083'), $u('1577803645773-f96470509666'), $u('1591076482161-42ce6da69f67')]],

    'pharmacie' => ['label' => 'Pharmacie', 'color' => '#15803d', 'icon' => 'fa-prescription-bottle-medical', 'cta' => 'Nous contacter',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['pharmac', 'pharmacy', 'parapharmac', 'drugstore', 'herborist', 'orthoped'],
        'brief' => "Pharmacie : horaires et gardes, ordonnances et renouvellements, vaccination, tests (antigéniques, glycémie), parapharmacie et dermo-cosmétique, matériel médical et orthopédie, conseils, préparation, livraison. FAQ : garde, vaccination sans rendez-vous, commande de produits, tiers payant, retour de médicaments. Ton : serviable, précis, santé publique sans promesse.",
        'stock' => [$u('1576602976047-174e57a47881'), $u('1587854692152-cbe660dbde88'), $u('1631549916768-4119b2e5f926'), $u('1585435557343-3b092031a831')]],

    /* ───────────── Maison, bâtiment, artisans ───────────── */

    'renovation' => ['label' => 'Rénovation & énergie', 'color' => '#ea580c', 'icon' => 'fa-house-chimney', 'cta' => 'Devis gratuit',
        'font' => 'Sora', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['isolation', 'chaudiere', 'chaudière', 'pompe a chaleur', 'pompe à chaleur', 'pac', 'vmc', 'climatisation', 'chauffage', 'panneaux', 'solaire', 'photovolta', 'renovation', 'rénovation', 'energ', 'énerg', 'rge', 'fenetre', 'fenêtre', 'ravalement', 'cuisiniste', 'salle de bain', 'general_contractor'],
        'brief' => "Rénovation énergétique et travaux : isolation (combles, murs, ITE), pompe à chaleur, chaudière, photovoltaïque, menuiseries, rénovation globale. Mettre en avant les aides (MaPrimeRénov', CEE) et la certification RGE seulement si la fiche/les avis l'indiquent, sinon parler d'accompagnement aux aides. Parcours = visite technique gratuite → devis détaillé → chantier → suivi. FAQ : aides et financement, délais, garanties (décennale), zone d'intervention, nuisances du chantier, économies attendues (prudence). Ton : sérieux, chiffré, rassurant.",
        'stock' => [$u('1504307651254-35680f356dfd'), $u('1541976590-713941681591'), $u('1600585154340-be6161a56a0c'), $u('1513694203232-719a280e022f')]],

    'plombier' => ['label' => 'Plomberie & chauffage', 'color' => '#0369a1', 'icon' => 'fa-faucet', 'cta' => 'Appeler · devis gratuit',
        'font' => 'Sora', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['plombier', 'plomberie', 'plumber', 'chauffagiste', 'sanitaire', 'fuite', 'debouchage', 'débouchage', 'chauffe-eau', 'chauffe eau'],
        'brief' => "Plombier-chauffagiste : dépannage urgent (fuite, débouchage, chauffe-eau en panne) avec délai d'intervention, installation sanitaire, salle de bain clé en main, chaudière et entretien annuel, recherche de fuite. Services = urgences + installations avec « à partir de » ou « sur devis ». FAQ : délai d'intervention, tarif de déplacement, devis gratuit, garanties, paiement, zone. Ton : réactif, honnête sur les prix, rassurant (pas de mauvaise surprise).",
        'stock' => [$u('1607472586893-edb57bdc0e39'), $u('1585704032915-c3400ca199e7'), $u('1581244277943-fe4a9c777189'), $u('1504148455328-c376907d081c')]],

    'electricien' => ['label' => 'Électricité', 'color' => '#ca8a04', 'icon' => 'fa-bolt', 'cta' => 'Devis gratuit',
        'font' => 'Sora', 'theme' => 'dark', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['electricien', 'électricien', 'electrician', 'electricite', 'électricité', 'domotique', 'tableau electrique', 'borne de recharge', 'irve', 'alarme', 'videosurveillance', 'vidéosurveillance'],
        'brief' => "Électricien : mise aux normes et tableau électrique, dépannage, rénovation complète, domotique et éclairage, borne de recharge véhicule électrique (IRVE), alarme/vidéo, interphone. FAQ : diagnostic et mise en conformité (Consuel), délais, devis, garanties, coupure pendant les travaux, qualification IRVE. Ton : technique mais lisible, sécurité d'abord.",
        'stock' => [$u('1621905251189-08b45d6a269e'), $u('1558618666-fcd25c85cd64'), $u('1565608438257-fac3c27beb36'), $u('1581092160562-40aa08e78837')]],

    'serrurier' => ['label' => 'Serrurerie & sécurité', 'color' => '#334155', 'icon' => 'fa-key', 'cta' => 'Appeler maintenant',
        'font' => 'Sora', 'theme' => 'dark', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['serrurier', 'serrurerie', 'locksmith', 'ouverture de porte', 'blindage', 'coffre-fort', 'coffre fort', 'porte blindee', 'porte blindée', 'rideau metallique', 'rideau métallique'],
        'brief' => "Serrurier : ouverture de porte sans dégât, remplacement de serrure et cylindre, blindage, porte blindée, après cambriolage, double de clés, rideaux métalliques. Afficher le prix de déplacement et « devis avant intervention » pour rassurer (secteur à arnaques). FAQ : délai d'arrivée, tarif de nuit/week-end, devis écrit, justificatif de domicile, assurance, marques. Ton : urgent mais honnête et transparent.",
        'stock' => [$u('1558002038-1055907df827'), $u('1582139329536-e7284fece509'), $u('1621905251189-08b45d6a269e')]],

    'batiment' => ['label' => 'Maçonnerie, toiture & gros œuvre', 'color' => '#78350f', 'icon' => 'fa-trowel-bricks', 'cta' => 'Devis gratuit',
        'font' => 'Sora', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['macon', 'maçon', 'maconnerie', 'maçonnerie', 'couvreur', 'couverture', 'toiture', 'roofing', 'charpent', 'zingu', 'terrassement', 'batiment', 'bâtiment', 'btp', 'construction', 'extension', 'facade', 'façade', 'demolition', 'démolition', 'piscinist', 'carreleur', 'carrelage', 'platrier', 'plâtrier', 'placo'],
        'brief' => "Entreprise du bâtiment : gros œuvre, extension, toiture et charpente, façade, carrelage, plâtrerie, terrassement, construction neuve. Mettre en avant réalisations (avant/après), respect des délais, décennale, interlocuteur unique. Parcours = visite → devis → planning → réception. FAQ : garanties, délais, permis/déclaration, paiement par étapes, assurance, propreté du chantier. Ton : solide, carré, concret.",
        'stock' => [$u('1541888946425-d81bb19240f5'), $u('1503387762-592deb58ef4e'), $u('1504917595217-d4dc5ebe6122'), $u('1621905252507-b35492cc74b4')]],

    'peintre' => ['label' => 'Peinture & décoration', 'color' => '#7c3aed', 'icon' => 'fa-paint-roller', 'cta' => 'Devis gratuit',
        'font' => 'Outfit', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['peintre', 'peinture', 'painter', 'decorat', 'décorat', 'papier peint', 'enduit', 'revetement', 'revêtement', 'sol souple', 'parquet'],
        'brief' => "Peintre-décorateur : peinture intérieure/extérieure, enduits décoratifs, papier peint, ravalement, conseils couleurs, sols. Insister sur la préparation des supports, la propreté, le respect des délais, les finitions. FAQ : conseil couleur, peintures écologiques, durée du chantier, meubles à déplacer, devis au m², garanties. Ton : soigné, visuel, créatif mais fiable.",
        'stock' => [$u('1562259949-e8e7689d7828'), $u('1589939705384-5185137a7f0f'), $u('1513694203232-719a280e022f'), $u('1600210492486-724fe5c67fb0')]],

    'menuisier' => ['label' => 'Menuiserie & agencement', 'color' => '#a16207', 'icon' => 'fa-ruler-combined', 'cta' => 'Parler de votre projet',
        'font' => 'Fraunces', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['menuis', 'ebenist', 'ébénist', 'agencement', 'cuisine sur mesure', 'dressing', 'placard', 'escalier', 'woodwork', 'carpenter', 'parquet', 'volet', 'stores', 'pergola', 'veranda', 'véranda', 'portail', 'cloture', 'clôture'],
        'brief' => "Menuisier / agenceur / cuisiniste : sur-mesure (cuisines, dressings, bibliothèques, escaliers), menuiseries extérieures (fenêtres, volets, portails, pergolas, vérandas), rénovation de l'existant, essences de bois et matériaux. Parcours = prise de cotes → plans 3D → fabrication → pose. FAQ : délais de fabrication, matériaux, entretien, garanties, pose comprise, devis. Ton : artisanal, précis, chaleureux.",
        'stock' => [$u('1504148455328-c376907d081c'), $u('1416879595882-3373a0480b5b'), $u('1600566752355-35792bedcfea'), $u('1556909114-f6e7ad7d3136')]],

    'paysagiste' => ['label' => 'Paysagiste & jardins', 'color' => '#15803d', 'icon' => 'fa-leaf', 'cta' => 'Devis gratuit',
        'font' => 'Fraunces', 'theme' => 'light', 'hero' => 'poster', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['paysag', 'jardin', 'garden', 'landscap', 'elagage', 'élagage', 'elagueur', 'élagueur', 'arrosage', 'cloture vegetale', 'gazon', 'terrasse bois', 'piscine naturelle', 'pepinier', 'pépinier'],
        'brief' => "Paysagiste : création et aménagement de jardins, terrasses, entretien (tonte, taille, élagage), arrosage automatique, clôtures et allées, plantations locales. Services avec « sur devis » et éventuellement contrat d'entretien. FAQ : saison idéale, entretien et crédit d'impôt (services à la personne, si applicable), délais, évacuation des déchets verts, devis. Ton : nature, apaisant, concret sur le résultat.",
        'stock' => [$u('1558904541-efa843a96f01'), $u('1416879595882-3373a0480b5b'), $u('1585320806297-9794b3e4eeae'), $u('1523348837708-15d4a09cfac2')]],

    'nettoyage' => ['label' => 'Nettoyage & services à domicile', 'color' => '#0891b2', 'icon' => 'fa-broom', 'cta' => 'Devis gratuit',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['nettoyage', 'cleaning', 'menage', 'ménage', 'femme de menage', 'aide a domicile', 'aide à domicile', 'conciergerie', 'laveur de vitres', 'vitrerie', 'proprete', 'propreté', 'desinfection', 'désinfection', 'repassage', 'pressing', 'laundry', 'blanchisserie', 'retouche', 'cordonn'],
        'brief' => "Nettoyage / services à domicile : ménage régulier ou ponctuel, fin de chantier, vitres, bureaux et copropriétés, remise en état, pressing/repassage. Mettre en avant la régularité, la confiance (personnel formé, assuré), le crédit d'impôt 50 % quand c'est du service à la personne. FAQ : produits, matériel, fréquence, engagement, remplacement en cas d'absence, devis, crédit d'impôt. Ton : net, fiable, simple.",
        'stock' => [$u('1581578731548-c64695cc6952'), $u('1527515637462-cff94eecc1ac'), $u('1584622650111-993a426fbf0a'), $u('1563453392212-326f5e854473')]],

    'demenagement' => ['label' => 'Déménagement & transport', 'color' => '#1d4ed8', 'icon' => 'fa-truck-moving', 'cta' => 'Devis gratuit',
        'font' => 'Sora', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['demenag', 'déménag', 'moving', 'movers', 'garde-meuble', 'garde meuble', 'stockage', 'storage', 'transport', 'coursier', 'livraison', 'logisti', 'taxi', 'vtc', 'chauffeur'],
        'brief' => "Déménagement (ou transport, garde-meuble) : formules (économique, standard, clé en main), emballage, monte-meubles, garde-meuble, longue distance, entreprises. Parcours = visite ou visio d'estimation → devis fixe → jour J. FAQ : assurance et casse, délais, objets fragiles/piano, cartons fournis, stationnement et autorisations, acompte. Ton : organisé, rassurant, transparent.",
        'stock' => [$u('1600518464441-9154a4dea21b'), $u('1595246140625-573b715d11dc'), $u('1603796846097-bee99e4a601f'), $u('1600585154526-990dced4db0d')]],

    /* ───────────── Automobile ───────────── */

    'automobile' => ['label' => 'Garage automobile', 'color' => '#2563eb', 'icon' => 'fa-car', 'cta' => 'Prendre rendez-vous',
        'font' => 'Sora', 'theme' => 'dark', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['garage', 'car_repair', 'mecanique', 'mécanique', 'pneu', 'vidange', 'controle technique', 'contrôle technique', 'moto', 'scooter', 'car_dealer', 'concession', 'occasion', 'depannage auto', 'remorquage', 'pare-brise', 'pare brise', 'car_wash', 'lavage'],
        'brief' => "Garage : entretien et révision toutes marques, diagnostic électronique, pneus, freins, distribution, climatisation, contrôle technique (préparation/contre-visite), carrosserie, véhicule de courtoisie, vente d'occasion. Services avec « à partir de » réalistes. FAQ : devis avant travaux, délais, garantie pièces/main-d'œuvre, véhicule de prêt, paiement en plusieurs fois, marques. Ton : franc, mécanique expliquée simplement, confiance.",
        'stock' => [$u('1486262715619-67b85e0b08d3'), $u('1530046339160-ce3e530c7d2f'), $u('1625047509248-ec889cbff17f'), $u('1492144534655-ae79c964c9d7')]],

    'carrosserie' => ['label' => 'Carrosserie & esthétique auto', 'color' => '#dc2626', 'icon' => 'fa-car-burst', 'cta' => 'Devis gratuit',
        'font' => 'Sora', 'theme' => 'dark', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['carrosserie', 'carrossier', 'body shop', 'debosselage', 'débosselage', 'peinture auto', 'detailing', 'esthetique auto', 'esthétique auto', 'covering', 'vitre teintee', 'vitre teintée', 'jantes'],
        'brief' => "Carrosserie : réparation après sinistre, débosselage sans peinture, peinture, remplacement pare-brise, detailing et rénovation esthétique, covering, gestion avec l'assurance (franchise, véhicule de remplacement). FAQ : prise en charge assurance, délais, teinte exacte, garantie, véhicule de courtoisie, devis. Ton : précis, rassurant après un accrochage.",
        'stock' => [$u('1625047509248-ec889cbff17f'), $u('1601362840469-51e4d8d58785'), $u('1530046339160-ce3e530c7d2f'), $u('1492144534655-ae79c964c9d7')]],

    'autoecole' => ['label' => 'Auto-école', 'color' => '#0284c7', 'icon' => 'fa-id-card', 'cta' => 'S\'inscrire',
        'font' => 'Outfit', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['auto-ecole', 'auto ecole', 'auto-école', 'auto école', 'driving', 'permis', 'conduite accompagnee', 'conduite accompagnée', 'moto-ecole', 'moto école', 'code de la route'],
        'brief' => "Auto-école : permis B (classique, conduite accompagnée AAC, boîte auto), code en ligne et en salle, permis moto/scooter, stages accélérés, taux de réussite, financement (CPF, permis à 1 €). FAQ : délais d'inscription, nombre d'heures, prix des forfaits, CPF, horaires des leçons, voiture double commande, taux de réussite. Ton : pédagogue, encourageant, transparent sur les tarifs.",
        'stock' => [$u('1449965408869-eaa3f722e40d'), $u('1502877338535-766e1452684a'), $u('1471444928139-48c5bf5173f8'), $u('1489824904134-891ab64532f1')]],

    /* ───────────── Commerces ───────────── */

    'commerce' => ['label' => 'Boutique & commerce', 'color' => '#16a34a', 'icon' => 'fa-store', 'cta' => 'Nous rendre visite',
        'font' => 'Outfit', 'theme' => 'light', 'hero' => 'split', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['boutique', 'magasin', 'marche', 'supermarket', 'convenience', 'jouet', 'toy', 'electronics', 'sport shop', 'sporting_goods', 'cadeaux', 'gift', 'concept store', 'general_store', 'department_store'],
        'brief' => "Commerce de proximité : l'univers et la sélection (produits, marques, provenance), le conseil en boutique, les services (commande, retrait, livraison locale, cartes cadeaux, ateliers), horaires. Services = rayons/gammes et services plutôt que prestations. FAQ : horaires, livraison/click & collect, paiement, retours, commandes spéciales, parking. Ton : accueillant, concret, fier des produits.",
        'stock' => [$u('1441986300917-64674bd600d8'), $u('1472851294608-062f824d29cc'), $u('1556742049-0cfed4f6a45d'), $u('1534452203293-494d7ddbf7e0')]],

    'fleuriste' => ['label' => 'Fleuriste', 'color' => '#db2777', 'icon' => 'fa-seedling', 'cta' => 'Commander un bouquet',
        'font' => 'Cormorant Garamond', 'theme' => 'light', 'hero' => 'poster', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['fleur', 'florist', 'flower', 'bouquet', 'plante', 'jardinerie', 'horticult'],
        'brief' => "Fleuriste : bouquets du jour et sur mesure, mariages et événements, deuil, plantes d'intérieur, abonnement floral, livraison locale, fleurs de saison et producteurs. FAQ : livraison (zones, délais, dimanche), commande par téléphone, mariage (devis, rendez-vous), deuil (livraison en cérémonie), entretien des plantes, paiement. Ton : poétique mais efficace, saisonnier.",
        'stock' => [$u('1487070183336-b863922373d4'), $u('1526047932273-341f2a7631f9'), $u('1563241527-3004b7be0ffd'), $u('1455659817273-f96807779a8a')]],

    'caviste' => ['label' => 'Cave & épicerie fine', 'color' => '#7f1d1d', 'icon' => 'fa-wine-bottle', 'cta' => 'Nous rendre visite',
        'font' => 'Playfair Display', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['cavist', 'cave a vin', 'cave à vin', 'liquor_store', 'vin', 'spiritueux', 'whisky', 'epicerie fine', 'épicerie fine', 'delicatessen', 'fromagerie', 'torrefact', 'torréfact', 'the', 'thé'],
        'brief' => "Caviste / épicerie fine : sélection (vignerons, régions, bio/nature, spiritueux, bières), conseils accords, dégustations et ateliers, cadeaux d'entreprise, coffrets, livraison. FAQ : conseil pour un repas, budget, dégustations, commandes cadeaux, livraison, fidélité. Ton : passionné, accessible, jamais snob.",
        'stock' => [$u('1510812431401-41d2bd2722f3'), $u('1474722883778-792e7990302f'), $u('1528823872057-9c018a7a7553'), $u('1506377247377-2a5b3b417ebb')]],

    'mode' => ['label' => 'Mode & accessoires', 'color' => '#0f172a', 'icon' => 'fa-shirt', 'cta' => 'Découvrir la boutique',
        'font' => 'DM Serif Display', 'theme' => 'light', 'hero' => 'bright', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['clothing', 'vetement', 'vêtement', 'pret-a-porter', 'prêt-à-porter', 'mode', 'fashion', 'chaussure', 'shoe', 'maroquinerie', 'lingerie', 'friperie', 'vintage', 'couturi', 'tailleur', 'retouch'],
        'brief' => "Boutique de mode : sélection et marques, style (casual, chic, créateurs, vintage), conseil et personal shopping, retouches, nouveautés par saison, cartes cadeaux, click & collect. FAQ : tailles et retours/échanges, marques, retouches, paiement, horaires, nouveautés. Ton : éditorial mode, inspirant, chaleureux.",
        'stock' => [$u('1441986300917-64674bd600d8'), $u('1445205170230-053b83016050'), $u('1483985988355-763728e1935b'), $u('1469334031218-e382a71b716b')]],

    'bijouterie' => ['label' => 'Bijouterie & horlogerie', 'color' => '#b45309', 'icon' => 'fa-gem', 'cta' => 'Prendre rendez-vous',
        'font' => 'Cormorant Garamond', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['bijou', 'jewelry', 'joailli', 'horlog', 'watch', 'montre', 'orfevre', 'orfèvre', 'alliance', 'bague'],
        'brief' => "Bijouterie-joaillerie-horlogerie : créations et sur-mesure (alliances, bagues de fiançailles), réparation et transformation, remise à taille, gravure, rachat d'or, montres et entretien. FAQ : délais sur-mesure, pierres et certificats, réparation et devis, garantie, nettoyage, rendez-vous privé. Ton : précieux, précis, confiance.",
        'stock' => [$u('1515562141207-7a88fb7ce338'), $u('1573408301185-9146fe634ad0'), $u('1611591437281-460bfbe1220a'), $u('1599643478518-a784e5dc4c8f')]],

    'informatique' => ['label' => 'Informatique & téléphonie', 'color' => '#2563eb', 'icon' => 'fa-laptop', 'cta' => 'Demander un diagnostic',
        'font' => 'Space Grotesk', 'theme' => 'dark', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['informatique', 'computer', 'ordinateur', 'reparation telephone', 'réparation téléphone', 'smartphone', 'cell_phone', 'phone', 'iphone', 'depannage informatique', 'dépannage informatique', 'electronics_store', 'high tech', 'imprimante', 'reseau', 'réseau'],
        'brief' => "Réparation informatique et téléphonie : écran et batterie smartphone, dépannage PC/Mac, récupération de données, suppression de virus, installation et réseau, vente d'occasion reconditionné, dépannage à domicile et aux entreprises. Services avec prix « à partir de » réalistes et délais (écran en 1 h). FAQ : délais, garantie des réparations, pièces d'origine, devis gratuit, données personnelles, déplacement. Ton : clair, rapide, pédagogue.",
        'stock' => [$u('1517336714731-489689fd1ca8'), $u('1588508065123-287b28e013da'), $u('1531297484001-80022131f5a1'), $u('1498050108023-c5249f4df085')]],

    /* ───────────── Professions libérales & services aux entreprises ───────────── */

    'avocat' => ['label' => 'Cabinet d\'avocats', 'color' => '#334155', 'icon' => 'fa-scale-balanced', 'cta' => 'Demander une consultation',
        'font' => 'Playfair Display', 'theme' => 'light', 'hero' => 'center', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['avocat', 'lawyer', 'legal', 'juridique', 'huissier', 'commissaire de justice', 'mediateur', 'médiateur', 'maitre ', 'maître '],
        'brief' => "Cabinet d'avocats : domaines (droit de la famille, du travail, immobilier, pénal, affaires, étrangers…), première consultation, honoraires (forfait, taux horaire, convention d'honoraires, aide juridictionnelle), déroulé d'un dossier, confidentialité. FAQ : coût de la première consultation, honoraires, délais, documents à apporter, aide juridictionnelle, visio. Ton : sobre, précis, humain ; respecter la déontologie (pas de promesse de résultat, pas de comparaison).",
        'stock' => [$u('1589829545856-d10d557cf95f'), $u('1505664194779-8beaceb93744'), $u('1521791055366-0d553872125f'), $u('1450101499163-c8848c66ca85')]],

    'notaire' => ['label' => 'Office notarial', 'color' => '#1e3a8a', 'icon' => 'fa-stamp', 'cta' => 'Prendre rendez-vous',
        'font' => 'Libre Baskerville', 'theme' => 'light', 'hero' => 'center', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['notaire', 'notary', 'office notarial', 'etude notariale', 'étude notariale'],
        'brief' => "Office notarial : immobilier (vente, achat, compromis), famille (mariage, PACS, donation, succession), entreprise, expertise et négociation immobilière, authenticité des actes. FAQ : frais de notaire (explication), délais d'une vente, documents, rendez-vous, succession, visio. Ton : institutionnel, pédagogue, rassurant ; déontologie stricte.",
        'stock' => [$u('1450101499163-c8848c66ca85'), $u('1521791136064-7986c2920216'), $u('1497366811353-6870744d04b2'), $u('1554224155-6726b3ff858f')]],

    'comptable' => ['label' => 'Expertise comptable', 'color' => '#4f46e5', 'icon' => 'fa-calculator', 'cta' => 'Devis gratuit',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['comptab', 'accounting', 'expert-compt', 'expert compt', 'fiduciaire', 'gestion de paie', 'commissaire aux comptes', 'fiscal'],
        'brief' => "Expert-comptable : tenue et bilan, TVA et liasses, paie et social, création d'entreprise (choix du statut), conseil fiscal, prévisionnel, accompagnement des indépendants/professions libérales/commerçants, outils en ligne. Services avec forfaits mensuels « à partir de ». FAQ : tarif, changement de cabinet (reprise sans rupture), outils et dématérialisation, réactivité, secteurs, première rencontre gratuite. Ton : fiable, concret, orienté tranquillité du dirigeant.",
        'stock' => [$u('1554224155-8d04cb21cd6c'), $u('1454165804606-c3d57bc86b40'), $u('1553877522-43269d4ea984'), $u('1460925895917-afdab827c52f')]],

    'assurance' => ['label' => 'Assurance & courtage', 'color' => '#0284c7', 'icon' => 'fa-shield-halved', 'cta' => 'Devis gratuit',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['assur', 'insurance', 'mutuelle', 'courtier', 'courtage', 'prevoyance', 'prévoyance', 'credit', 'crédit', 'pret', 'prêt', 'financement', 'banque', 'bank', 'patrimoine', 'gestion de patrimoine', 'placement'],
        'brief' => "Assurance, courtage ou conseil financier : auto, habitation, santé/mutuelle, prévoyance, pro (RC, multirisque), emprunteur, crédit immobilier, gestion de patrimoine. Mettre en avant la comparaison, l'accompagnement sinistre, l'interlocuteur local. FAQ : résiliation et changement d'assureur (loi Hamon), délais de devis, sinistre, franchise, documents, frais de courtage. Ton : clair, protecteur, sans jargon.",
        'stock' => [$u('1450101499163-c8848c66ca85'), $u('1554224155-6726b3ff858f'), $u('1521791136064-7986c2920216'), $u('1560472354-b33ff0c44a43')]],

    'immobilier' => ['label' => 'Agence immobilière', 'color' => '#059669', 'icon' => 'fa-building', 'cta' => 'Estimation gratuite',
        'font' => 'Playfair Display', 'theme' => 'light', 'hero' => 'editorial', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['immobili', 'real_estate', 'agence immo', 'syndic', 'gestion locative', 'promoteur', 'diagnostiqueur', 'chasseur immobilier', 'home staging', 'conciergerie airbnb'],
        'brief' => "Agence immobilière : vente, achat, location, gestion locative, syndic, estimation gratuite, home staging, connaissance du quartier, chiffres (biens vendus, délai moyen) seulement s'ils sont crédibles et prudents. Parcours = estimation → mise en valeur (photos, visite virtuelle) → visites → signature. FAQ : honoraires (qui paie), estimation, délai de vente, mandat exclusif ou simple, documents, gestion locative. Ton : expert local, chaleureux, rassurant.",
        'stock' => [$u('1560518883-ce09059eeffa'), $u('1600607687939-ce8a6c25118c'), $u('1600566753086-00f18fb6b3ea'), $u('1512917774080-9991f1c4c750')]],

    'architecte' => ['label' => 'Architecture & décoration d\'intérieur', 'color' => '#0f172a', 'icon' => 'fa-compass-drafting', 'cta' => 'Parler du projet',
        'font' => 'Space Grotesk', 'theme' => 'light', 'hero' => 'bright', 'order' => ['about', 'services', 'gallery', 'process', 'reviews', 'faq', 'booking', 'cta', 'contact'], 'modules' => $bookingOn,
        'keywords' => ['architect', 'maitre d\'oeuvre', 'maître d\'œuvre', 'decorateur', 'décorateur', 'interior', 'design d\'interieur', 'design d\'intérieur', 'amenagement interieur', 'aménagement intérieur', 'urbanis'],
        'brief' => "Architecte / architecte d'intérieur / décorateur : conception, permis de construire, extension et rénovation, aménagement intérieur, suivi de chantier, visualisations 3D, style et approche (lumière, matériaux, durable). Parcours = rencontre → esquisse → projet détaillé → chantier. FAQ : honoraires (% ou forfait), quand faire appel, permis, délais, budget minimum, zone. Ton : créatif, précis, inspirant, sobre.",
        'stock' => [$u('1503387762-592deb58ef4e'), $u('1600585154526-990dced4db0d'), $u('1618221195710-dd6b41faaea6'), $u('1600210492486-724fe5c67fb0')]],

    'conseil' => ['label' => 'Conseil & formation', 'color' => '#7c3aed', 'icon' => 'fa-lightbulb', 'cta' => 'Prendre contact',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['conseil', 'consulting', 'consultant', 'formation', 'formateur', 'training', 'bureau d\'etudes', 'bureau d\'études', 'business_management_consultant', 'management', 'strategie', 'stratégie', 'expert-conseil', 'audit'],
        'brief' => "Conseil, coaching, formation ou agence : offres claires (audit, accompagnement, formation, abonnement), pour qui (TPE, PME, particuliers), méthode et livrables, résultats attendus formulés prudemment, références/témoignages. FAQ : premier échange gratuit, tarifs et formats, distanciel/présentiel, financement (OPCO, CPF, Qualiopi si indiqué), durée. Ton : expert, direct, orienté résultats concrets.",
        'stock' => [$u('1600880292203-757bb62b4baf'), $u('1552664730-d307ca884978'), $u('1556761175-b413da4baf72'), $u('1522071820081-009f0129c71c')]],

    'photographe' => ['label' => 'Photographe & vidéaste', 'color' => '#0f172a', 'icon' => 'fa-camera', 'cta' => 'Demander un devis',
        'font' => 'DM Serif Display', 'theme' => 'dark', 'hero' => 'editorial', 'order' => ['gallery', 'services', 'about', 'process', 'reviews', 'faq', 'booking', 'cta', 'contact'], 'modules' => $bookingOn,
        'keywords' => ['photograph', 'photo', 'videaste', 'vidéaste', 'video', 'vidéo', 'studio photo', 'portrait', 'mariage', 'drone', 'shooting'],
        'brief' => "Photographe / vidéaste : spécialités (mariage, portrait, famille, naissance, corporate, immobilier, produit, événementiel), formules avec durée et nombre de photos livrées, studio ou déplacement, délais de livraison, galerie en ligne, tirages. FAQ : délais, nombre de photos retouchées, déplacement, droits d'utilisation, acompte, météo/report, tenue conseillée. Ton : artistique, humain, précis sur la prestation.",
        'stock' => [$u('1452587925148-ce544e77e70d'), $u('1471341971476-ae15ff5dd4ea'), $u('1554048612-b6a482bc67e5'), $u('1500051638674-ff996a0ec29e')]],

    'evenementiel' => ['label' => 'Événementiel & animation', 'color' => '#c026d3', 'icon' => 'fa-champagne-glasses', 'cta' => 'Demander un devis',
        'font' => 'Bricolage Grotesque', 'theme' => 'dark', 'hero' => 'poster', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['evenement', 'événement', 'event', 'wedding planner', 'organisat', 'dj', 'animation', 'animateur', 'magicien', 'location de salle', 'salle de reception', 'salle de réception', 'chateau', 'château', 'domaine', 'sonorisation', 'eclairage', 'éclairage', 'photobooth', 'musicien', 'orchestre'],
        'brief' => "Événementiel (wedding planner, DJ, salle de réception, animation) : types d'événements (mariage, anniversaire, séminaire, soirée d'entreprise), prestations (organisation complète, DJ et sonorisation, lumière, animations, photobooth), capacité et équipements si lieu. Parcours = brief → proposition → organisation → jour J. FAQ : budget indicatif, acompte et annulation, déplacement, matériel inclus, horaires de fin, démo/playlist. Ton : festif, pro, rassurant sur l'organisation.",
        'stock' => [$u('1492684223066-81342ee5ff30'), $u('1519671482749-fd09be7ccebf'), $u('1511795409834-ef04bbd61622'), $u('1470229722913-7c0e2dbbafd3')]],

    'formation' => ['label' => 'École, cours & soutien', 'color' => '#0369a1', 'icon' => 'fa-graduation-cap', 'cta' => 'Demander des infos',
        'font' => 'Outfit', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['ecole', 'école', 'school', 'cours', 'soutien scolaire', 'professeur', 'tutor', 'langue', 'language', 'musique', 'conservatoire', 'piano', 'guitare', 'theatre', 'théâtre', 'atelier', 'stage', 'universit', 'college', 'lycee', 'lycée', 'bts', 'alternance'],
        'brief' => "École, cours ou soutien : programmes et niveaux, pédagogie, petits groupes ou individuel, enseignants, résultats (prudents), horaires, tarifs et formules (à l'heure, trimestre, stage), cours d'essai. FAQ : niveau requis, cours d'essai, tarifs et aides (crédit d'impôt, CPF), matériel, horaires, suivi des progrès. Ton : pédagogue, encourageant, structuré.",
        'stock' => [$u('1523240795612-9a054b0db644'), $u('1503676260728-1c00da094a0b'), $u('1427504494785-3a9ca7044f45'), $u('1509062522246-3755977927d7')]],

    'enfance' => ['label' => 'Petite enfance & famille', 'color' => '#f59e0b', 'icon' => 'fa-baby', 'cta' => 'Demander une place',
        'font' => 'Outfit', 'theme' => 'light', 'hero' => 'poster', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['creche', 'crèche', 'micro-creche', 'micro crèche', 'child_care', 'garde d', 'nounou', 'assistante maternelle', 'baby-sitting', 'babysitting', 'halte', 'periscolaire', 'périscolaire', 'centre de loisirs', 'ludotheque', 'ludothèque', 'parc de jeux', 'anniversaire enfant'],
        'brief' => "Crèche, garde d'enfants ou loisirs enfants : projet pédagogique, équipe et taux d'encadrement, horaires et flexibilité, repas et couches, sécurité, inscriptions et places disponibles, tarifs et aides (CAF, PAJE, crédit d'impôt). FAQ : inscription et délais, période d'adaptation, tarifs et aides, horaires, repas, maladie. Ton : bienveillant, rassurant pour les parents, concret.",
        'stock' => [$u('1587654780291-39c9404d746b'), $u('1503454537195-1dcabb73ffb9'), $u('1596464716127-f2a82984de30'), $u('1544776193-352d25ca82cd')]],

    'funeraire' => ['label' => 'Pompes funèbres', 'color' => '#1f2937', 'icon' => 'fa-dove', 'cta' => 'Nous appeler 24h/24',
        'font' => 'Lora', 'theme' => 'light', 'hero' => 'center', 'order' => ['about', 'services', 'process', 'reviews', 'faq', 'booking', 'cta', 'contact'], 'modules' => $bookingOn,
        'keywords' => ['funeraire', 'funéraire', 'pompes funebres', 'pompes funèbres', 'funeral', 'marbrerie', 'marbrier', 'obseques', 'obsèques', 'cremat', 'crémat', 'chambre funeraire', 'chambre funéraire'],
        'brief' => "Pompes funèbres : disponibilité 24h/24, organisation complète des obsèques (inhumation, crémation), démarches administratives, chambre funéraire, marbrerie, contrats de prévoyance, fleurs. Parcours = premier appel → rendez-vous → organisation → cérémonie → après. FAQ : que faire en premier, délais légaux, coût et devis, prévoyance, transport du défunt, démarches. Ton : digne, apaisé, très clair, sans démarchage commercial.",
        'stock' => [$u('1468327768560-75b778cbb551'), $u('1473448912268-2022ce9509d8'), $u('1455218873509-8097305ee378'), $u('1490750967868-88aa4486c946')]],


    /* ───────────── Agences, digital & services aux entreprises ───────────── */

    'agence_web' => ['label' => 'Agence web & digital', 'color' => '#4f46e5', 'icon' => 'fa-code', 'cta' => 'Parler de mon projet',
        'font' => 'Space Grotesk', 'theme' => 'dark', 'hero' => 'split', 'order' => $b2b, 'modules' => $bookingOn,
        'keywords' => ['agence web', 'web agency', 'agence digitale', 'digital agency', 'creation de site', 'création de site', 'site internet', 'site web', 'webdesign', 'web design', 'website_designer', 'web_designer', 'internet_marketing_service', 'seo', 'referencement', 'référencement', 'e-commerce', 'ecommerce', 'wordpress', 'shopify', 'webmaster', 'studio digital', 'studio web', 'ux', 'ui design'],
        'brief' => "Agence web / digitale : on vend des résultats business (visibilité, contacts, ventes) avant de la technique. Services = création de site vitrine, e-commerce, refonte, SEO / référencement local, identité visuelle, maintenance, publicité en ligne ; prix « dès … » ou « sur devis ». Parcours en 3 étapes (échange & cadrage → conception & réalisation → mise en ligne & suivi). La galerie = réalisations / portfolio (titre du type « Nos réalisations »). FAQ : délais, tarifs, propriété du site, hébergement, accompagnement après livraison, référencement. Interdits : jargon creux (« solutions innovantes », « 360° »), promesses de position Google. Ton : expert, concret, orienté client, vouvoiement.",
        'stock' => [$u('1531403009284-440f080d1e12'), $u('1499951360447-b19be8fe80f5'), $u('1460925895917-afdab827c52f'), $u('1522202176988-66273c2fd55f')]],

    'communication' => ['label' => 'Communication & marketing', 'color' => '#db2777', 'icon' => 'fa-bullhorn', 'cta' => 'Demander un devis',
        'font' => 'Bricolage Grotesque', 'theme' => 'light', 'hero' => 'center', 'order' => $b2b, 'modules' => $bookingOn,
        'keywords' => ['communication', 'marketing', 'marketing_agency', 'advertising_agency', 'agence de communication', 'agence de pub', 'publicite', 'publicité', 'community manager', 'reseaux sociaux', 'réseaux sociaux', 'social media', 'relations presse', 'rp', 'branding', 'stratégie de marque', 'strategie de marque', 'public_relations_firm', 'influence', 'contenu', 'content'],
        'brief' => "Agence ou consultant en communication / marketing : clarté sur les livrables (stratégie, réseaux sociaux, campagnes, relations presse, contenus, publicité, marque). Services = offres packagées ou accompagnements mensuels, avec un ordre de prix si connu. Galerie = campagnes et réalisations. Parcours : brief → stratégie → production → mesure des résultats. FAQ : budget minimum, durée d'engagement, mesure des résultats, secteurs servis. Ton : créatif mais précis, zéro superlatif creux.",
        'stock' => [$u('1557804506-669a67965ba0'), $u('1504384308090-c894fdcc538d'), $u('1560472354-b33ff0c44a43'), $u('1523240795612-9a054b0db644')]],

    'graphiste' => ['label' => 'Design graphique & identité visuelle', 'color' => '#ea580c', 'icon' => 'fa-pen-nib', 'cta' => 'Demander un devis',
        'font' => 'Fraunces', 'theme' => 'light', 'hero' => 'editorial', 'order' => $b2b, 'modules' => $bookingOn,
        'keywords' => ['graphiste', 'graphic_designer', 'graphisme', 'design graphique', 'designer', 'studio de design', 'studio créatif', 'studio creatif', 'identite visuelle', 'identité visuelle', 'logo', 'illustrat', 'motion design', 'direction artistique', 'da freelance', 'packaging design', 'maquettiste'],
        'brief' => "Graphiste / studio de design : le portfolio fait vendre, les mots doivent rester sobres. Services = identité visuelle & logo, supports print, packaging, illustration, motion, webdesign, avec des prix « dès … » ou « sur devis ». Galerie = réalisations (titre du type « Sélection de projets »). Parcours : brief → pistes créatives → finalisation & fichiers. FAQ : délais, nombre d'allers-retours, cession des droits, fichiers livrés, impression. Ton : précis, élégant, phrases courtes.",
        'stock' => [$u('1450101499163-c8848c66ca85'), $u('1504868584819-f8e8b4b6d7e3'), $u('1553877522-43269d4ea984'), $u('1499951360447-b19be8fe80f5')]],

    'developpeur' => ['label' => 'Développement logiciel & applications', 'color' => '#0f766e', 'icon' => 'fa-laptop-code', 'cta' => 'Discuter de mon projet',
        'font' => 'Manrope', 'theme' => 'dark', 'hero' => 'split', 'order' => $b2b, 'modules' => $bookingOn,
        'keywords' => ['developpeur', 'développeur', 'developer', 'software_company', 'logiciel', 'software', 'application mobile', 'app mobile', 'applications', 'saas', 'startup', 'start-up', 'esn', 'ssii', 'intégrateur', 'integrateur', 'erp', 'crm', 'automatisation', 'intelligence artificielle', 'data', 'devops', 'cloud', 'freelance dev'],
        'brief' => "Studio ou freelance en développement : expliquer simplement ce qu'on construit (applications web, mobiles, logiciels métier, intégrations, automatisations, IA) et pour qui. Services = types de projets avec « sur devis » ou tarif jour si connu. Galerie = projets livrés (captures). Parcours : cadrage → développement par itérations → livraison & maintenance. FAQ : méthodologie, délais, propriété du code, maintenance, sécurité, hébergement. Ton : clair, rassurant, sans jargon inutile.",
        'stock' => [$u('1581091226825-a6a2a5aee158'), $u('1461749280684-dccba630e2f6'), $u('1517245386807-bb43f82c33c4'), $u('1559526324-4b87b5e36e44')]],

    'coach' => ['label' => 'Coaching & développement personnel', 'color' => '#c2410c', 'icon' => 'fa-seedling', 'cta' => 'Réserver un premier échange',
        'font' => 'Cormorant Garamond', 'theme' => 'light', 'hero' => 'bright', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['coach', 'coaching', 'life_coach', 'coach de vie', 'coach professionnel', 'developpement personnel', 'développement personnel', 'hypnose', 'hypnotherap', 'sophrolog', 'pnl', 'mentor', 'accompagnement', 'preparateur mental', 'préparateur mental', 'bilan de competences', 'bilan de compétences', 'reconversion', 'confiance en soi'],
        'brief' => "Coach (vie, professionnel, mental, hypnose, sophrologie) : parler des situations du client (transition, stress, confiance, objectif) avant des méthodes. Services = formats d'accompagnement (séance découverte, pack de séances, programme, atelier) avec prix et durée. Parcours : premier échange offert → séances → autonomie. FAQ : différence coaching/thérapie, nombre de séances, visio ou présentiel, confidentialité, tarifs, remboursement éventuel. Pas de promesse de résultat garanti. Ton : chaleureux, posé, vouvoiement.",
        'stock' => [$u('1493962853295-0fd70327578a'), $u('1573164713714-d95e436ab8d6'), $u('1507003211169-0a1dd7228f2d'), $u('1522071820081-009f0129c71c')]],

    'rh' => ['label' => 'Recrutement & ressources humaines', 'color' => '#1d4ed8', 'icon' => 'fa-people-group', 'cta' => 'Nous confier un recrutement',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $b2b, 'modules' => $bookingOn,
        'keywords' => ['recrutement', 'cabinet de recrutement', 'employment_agency', 'interim', 'intérim', 'ressources humaines', 'rh', 'chasseur de tete', 'chasseur de têtes', 'portage salarial', 'outplacement', 'paie', 'gestion de la paie', 'talent'],
        'brief' => "Cabinet de recrutement / RH / intérim : deux publics (entreprises qui recrutent, candidats). Services = recrutement par profil ou secteur, intérim, conseil RH, paie, outplacement, avec modalités (honoraires au succès, délais moyens). Parcours : brief de poste → sourcing & entretiens → intégration & garantie. FAQ : coût, délai moyen, garantie de remplacement, secteurs et régions couverts, confidentialité. Ton : professionnel, direct, humain.",
        'stock' => [$u('1542744173-8e7e53415bb0'), $u('1553729459-efe14ef6055d'), $u('1522202176988-66273c2fd55f'), $u('1497215728101-856f4ea42174')]],

    'traducteur' => ['label' => 'Traduction & rédaction', 'color' => '#4338ca', 'icon' => 'fa-language', 'cta' => 'Demander un devis',
        'font' => 'Fraunces', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['traduct', 'translator', 'translation', 'interpret', 'redacteur', 'rédacteur', 'redaction web', 'rédaction web', 'copywrit', 'correcteur', 'relecture', 'ecrivain public', 'écrivain public', 'biographe', 'sous-titrage', 'transcription'],
        'brief' => "Traducteur, interprète ou rédacteur : langues et domaines de spécialité (juridique, technique, marketing, assermenté), formats (documents, sites, sous-titres, interprétation). Services avec tarif au mot, à l'heure ou sur devis. Parcours : envoi du document → devis sous 24 h → livraison relue. FAQ : délais, traduction assermentée, confidentialité, langues, formats acceptés. Ton : précis, sobre, fiable.",
        'stock' => [$u('1544717305-2782549b5136'), $u('1455390582262-044cdead277a'), $u('1454165804606-c3d57bc86b40'), $u('1497366216548-37526070297c')]],

    'imprimerie' => ['label' => 'Imprimerie & signalétique', 'color' => '#0369a1', 'icon' => 'fa-print', 'cta' => 'Demander un devis',
        'font' => 'Bricolage Grotesque', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['imprimerie', 'imprimeur', 'print_shop', 'commercial_printer', 'reprographie', 'copy', 'impression', 'signaletique', 'signalétique', 'enseigne', 'enseigniste', 'flocage', 'serigraphie', 'sérigraphie', 'broderie', 'objets publicitaires', 'goodies', 'covering', 'marquage vehicule', 'marquage véhicule', 'stickers', 'adhesif', 'adhésif'],
        'brief' => "Imprimerie, enseigniste, flocage : produits concrets (cartes, flyers, affiches, bâches, enseignes, vitrophanie, textile, covering véhicule) avec délais et quantités. Services = familles de produits « dès … ». Galerie = réalisations. Parcours : fichier ou création → BAT → fabrication & livraison. FAQ : formats de fichiers, délais express, pose, quantités minimum, création graphique possible. Ton : pratique, rapide, rassurant sur la qualité.",
        'stock' => [$u('1590650153855-d9e808231d41'), $u('1450101499163-c8848c66ca85'), $u('1504868584819-f8e8b4b6d7e3'), $u('1472851294608-062f824d29cc')]],

    'securite' => ['label' => 'Sécurité & gardiennage', 'color' => '#334155', 'icon' => 'fa-shield-halved', 'cta' => 'Demander un devis',
        'font' => 'Manrope', 'theme' => 'dark', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['securite privee', 'sécurité privée', 'gardiennage', 'security_guard_service', 'security_service', 'agent de securite', 'agent de sécurité', 'surveillance', 'telesurveillance', 'télésurveillance', 'alarme', 'videosurveillance', 'vidéosurveillance', 'controle d\'acces', 'contrôle d\'accès', 'ssiap', 'securite incendie', 'sécurité incendie', 'rondes', 'protection rapprochée', 'maître-chien', 'maitre-chien'],
        'brief' => "Société de sécurité : sérieux, agréments (CNAPS), réactivité 24h/24. Services = gardiennage, rondes, événementiel, télésurveillance, alarme & vidéo, sécurité incendie, avec « sur devis ». Parcours : audit du site → dispositif proposé → mise en place & reporting. FAQ : agréments, délai de mise en place, zone d'intervention, astreinte, tarifs. Ton : sobre, ferme, fiable.",
        'stock' => [$u('1542838132-92c53300491e'), $u('1497215728101-856f4ea42174'), $u('1553729459-efe14ef6055d'), $u('1556745757-8d76bdb6984b')]],

    /* ───────────── Autres métiers de proximité ───────────── */

    'voyage' => ['label' => 'Agence de voyages', 'color' => '#0891b2', 'icon' => 'fa-plane-departure', 'cta' => 'Préparer mon voyage',
        'font' => 'Playfair Display', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['agence de voyage', 'agence de voyages', 'travel_agency', 'tour_operator', 'voyagiste', 'voyages', 'sejour', 'séjour', 'circuit', 'croisiere', 'croisière', 'billetterie', 'tourisme', 'tour_agency', 'guide touristique', 'tourist_information_center'],
        'brief' => "Agence de voyages : faire rêver avec des destinations concrètes et rassurer (accompagnement, garanties, assistance). Services = types de voyages (sur mesure, circuits, séjours, croisières, voyages de noces, groupes) avec « à partir de … ». Galerie = destinations. Parcours : envies & budget → proposition personnalisée → départ serein. FAQ : garanties et assurances, modification/annulation, acompte, formalités, assistance sur place. Ton : évocateur mais précis.",
        'stock' => [$u('1507525428034-b723cf961d3e'), $u('1476514525535-07fb3b4ae5f1'), $u('1500530855697-b586d89ba3ee'), $u('1469474968028-56623f02e42e')]],

    'agricole' => ['label' => 'Producteur, ferme & terroir', 'color' => '#4d7c0f', 'icon' => 'fa-wheat-awn', 'cta' => 'Commander / nous rendre visite',
        'font' => 'Fraunces', 'theme' => 'light', 'hero' => 'editorial', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['ferme', 'farm', 'producteur', 'maraich', 'maraîch', 'agricult', 'eleveur', 'éleveur', 'vigneron', 'domaine viticole', 'winery', 'vineyard', 'cave cooperative', 'fromager', 'fromagerie', 'apicult', 'miel', 'cueillette', 'vente directe', 'amap', 'circuit court', 'brasserie artisanale', 'brewery', 'distillerie', 'distillery', 'huile d\'olive', 'moulin'],
        'brief' => "Producteur / ferme / domaine : le lieu, les saisons, la méthode (bio, raisonné, élevage plein air), les produits et où les trouver (vente à la ferme, marchés, livraison, paniers). Services = produits et formules (paniers, visites, dégustations, événements). Galerie = ferme, animaux, produits. FAQ : horaires de vente, commandes, labels, visites, livraison. Ton : authentique, concret, sans folklore.",
        'stock' => [$u('1500382017468-9049fed747ef'), $u('1464226184884-fa280b87c399'), $u('1500937386664-56d1dfef3854'), $u('1574943320219-553eb213f72d')]],

    'animaux' => ['label' => 'Toilettage, pension & services animaliers', 'color' => '#b45309', 'icon' => 'fa-paw', 'cta' => 'Prendre rendez-vous',
        'font' => 'Bricolage Grotesque', 'theme' => 'light', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['toilett', 'pet_groomer', 'pension canine', 'pension pour chien', 'pension pour chat', 'pet_boarding', 'dog_day_care', 'educateur canin', 'éducateur canin', 'dog_trainer', 'comportementaliste', 'pet sitter', 'petsitt', 'garde d\'animaux', 'promenade de chien', 'dog walker', 'animalerie', 'pet_store', 'elevage canin', 'élevage canin', 'chatterie', 'osteopathe animalier', 'ostéopathe animalier'],
        'brief' => "Services animaliers (toilettage, pension, éducation, garde) : bien-être et sécurité de l'animal, hygiène, douceur, photos des pensionnaires. Services = prestations par taille/race ou par formule avec prix. Parcours : prise de contact → rencontre & évaluation → prestation & suivi. FAQ : vaccins exigés, durée, tarifs, animaux acceptés, urgences, horaires de dépôt. Ton : affectueux et professionnel.",
        'stock' => [$u('1548199973-03cce0bbc87b'), $u('1587300003388-59208cc962cb'), $u('1601758228041-f3b2795255f1'), $u('1516627145497-ae6968895b74')]],

    'musique' => ['label' => 'Musique & arts du spectacle', 'color' => '#7e22ce', 'icon' => 'fa-music', 'cta' => 'Réserver / nous contacter',
        'font' => 'Space Grotesk', 'theme' => 'dark', 'hero' => 'editorial', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['musique', 'music', 'ecole de musique', 'école de musique', 'music_school', 'professeur de piano', 'cours de guitare', 'cours de chant', 'studio d\'enregistrement', 'recording_studio', 'dj', 'groupe de musique', 'orchestre', 'musicien', 'band', 'theatre', 'théâtre', 'performing_arts_theater', 'compagnie', 'ecole de danse', 'école de danse', 'dance_school', 'cirque', 'conservatoire', 'luthier', 'magasin de musique'],
        'brief' => "Musique / spectacle (école, professeur, studio, artiste, troupe, DJ) : l'univers artistique d'abord, puis l'offre concrète (cours par niveau et instrument, enregistrement, prestations événementielles, représentations). Services avec prix et durée. Galerie = scènes, studio, élèves. FAQ : niveau requis, instruments, tarifs, essai gratuit, déplacements, matériel fourni. Ton : vivant, précis, accessible.",
        'stock' => [$u('1511379938547-c1f69419868d'), $u('1507838153414-b4b713384a76'), $u('1529333166437-7750a6dd5a70'), $u('1517457373958-b7bdd4587205')]],

    'loisirs' => ['label' => 'Loisirs, culture & activités', 'color' => '#059669', 'icon' => 'fa-ticket', 'cta' => 'Réserver une activité',
        'font' => 'Bricolage Grotesque', 'theme' => 'light', 'hero' => 'split', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['escape game', 'escape_room', 'laser game', 'bowling', 'karting', 'paintball', 'accrobranche', 'parc de loisirs', 'amusement_park', 'amusement_center', 'trampoline', 'aire de jeux', 'playground', 'centre equestre', 'centre équestre', 'equitation', 'équitation', 'club nautique', 'voile', 'plongee', 'plongée', 'surf', 'location de velo', 'location de vélo', 'bike_rental', 'canoe', 'canoë', 'musee', 'musée', 'museum', 'galerie d\'art', 'art_gallery', 'atelier creatif', 'atelier créatif', 'poterie', 'cours de cuisine', 'oenologie', 'œnologie', 'tourist_attraction'],
        'brief' => "Loisirs et activités : donner envie et répondre vite aux questions pratiques (âge minimum, durée, prix, réservation, groupes, anniversaires, météo). Services = activités / formules avec prix par personne. Galerie = l'expérience vécue. Parcours : réservation → accueil & briefing → l'activité. FAQ : âges, tenue, annulation, accessibilité, parking. Ton : enthousiaste, net, orienté réservation.",
        'stock' => [$u('1582213782179-e0d53f98f2ca'), $u('1528605248644-14dd04022da1'), $u('1529156069898-49953e39b3ac'), $u('1476514525535-07fb3b4ae5f1')]],

    'association' => ['label' => 'Association & club', 'color' => '#2563eb', 'icon' => 'fa-hands-holding-circle', 'cta' => 'Nous rejoindre',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'center', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['association', 'asso', 'club', 'club de sport', 'sports_club', 'football club', 'rugby', 'tennis club', 'judo', 'ong', 'non_profit', 'benevol', 'bénévol', 'solidarite', 'solidarité', 'entraide', 'caritatif', 'fondation', 'collectif', 'comite des fetes', 'comité des fêtes', 'amicale', 'mjc', 'maison de quartier', 'community_center'],
        'brief' => "Association ou club : la mission et les gens d'abord, puis comment participer (adhérer, s'inscrire, donner, bénévolat). Services = activités / sections / programmes avec horaires, publics et cotisation. Parcours : découvrir → essayer (séance d'essai) → adhérer. Galerie = la vie de l'association. FAQ : inscription, tarifs et aides, âges, lieux et créneaux, bénévolat, dons et reçus fiscaux. Ton : chaleureux, inclusif, concret.",
        'stock' => [$u('1529156069898-49953e39b3ac'), $u('1556155092-490a1ba16284'), $u('1522202176988-66273c2fd55f'), $u('1582213782179-e0d53f98f2ca')]],

    'nutrition' => ['label' => 'Diététique & nutrition', 'color' => '#16a34a', 'icon' => 'fa-apple-whole', 'cta' => 'Prendre rendez-vous',
        'font' => 'Manrope', 'theme' => 'light', 'hero' => 'split', 'order' => $care, 'modules' => $bookingOn,
        'keywords' => ['dietet', 'diétét', 'nutrition', 'nutritionist', 'dietitian', 'naturopath', 'micronutrition', 'reequilibrage alimentaire', 'rééquilibrage alimentaire', 'perte de poids', 'coach alimentaire', 'troubles alimentaires'],
        'brief' => "Diététicien / nutritionniste / naturopathe : approche bienveillante, personnalisée, sans régime miracle. Services = bilan, suivi, programmes (sport, grossesse, pathologies, enfants), ateliers, avec prix et durée. Parcours : bilan → plan personnalisé → suivi. FAQ : différence diététicien/nutritionniste, remboursement mutuelle, visio, durée du suivi, tarifs. Pas de promesse chiffrée de perte de poids. Ton : rassurant, concret, vouvoiement.",
        'stock' => [$u('1512621776951-a57141f2eefd'), $u('1490645935967-10de6ba17061'), $u('1498837167922-ddd27525d352'), $u('1543362906-acfc16c67564')]],

    'pressing' => ['label' => 'Pressing, retouche & cordonnerie', 'color' => '#0e7490', 'icon' => 'fa-shirt', 'cta' => 'Nous déposer un article',
        'font' => 'Bricolage Grotesque', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['pressing', 'dry_cleaner', 'laverie', 'laundry', 'blanchisserie', 'retouche', 'retoucherie', 'couturi', 'tailleur', 'tailor', 'cordonn', 'shoe_repair', 'cles minute', 'clés minute', 'repassage', 'nettoyage a sec', 'nettoyage à sec'],
        'brief' => "Pressing, retoucherie, cordonnerie : rapidité, soin, prix clairs. Services = prestations avec tarifs unitaires (nettoyage, repassage, ourlets, cuir, clés) et délais (express, 48 h). Parcours : dépôt → traitement → retrait ou livraison. FAQ : délais, textiles délicats, tarifs, horaires, collecte à domicile. Ton : pratique, net, de confiance.",
        'stock' => [$u('1489987707025-afc232f7ea0f'), $u('1517445312882-bc9910d016b7'), $u('1558618666-fcd25c85cd64'), $u('1472851294608-062f824d29cc')]],


    /* ───────────── Commerces alimentaires & de détail spécialisés ───────────── */

    'boucherie' => ['label' => 'Boucherie, charcuterie & poissonnerie', 'color' => '#b91c1c', 'icon' => 'fa-drumstick-bite', 'cta' => 'Commander / réserver',
        'font' => 'Fraunces', 'theme' => 'light', 'hero' => 'split', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['boucher', 'boucherie', 'butcher', 'butcher_shop', 'charcut', 'traiteur boucher', 'volaill', 'rotisserie', 'rôtisserie', 'poissonn', 'seafood', 'fish_store', 'ecailler', 'écailler', 'tripier', 'viande', 'halal'],
        'brief' => "Boucherie / charcuterie / poissonnerie : l'origine et la sélection (élevages, labels, pêche du jour), le savoir-faire (découpe, maturation, préparations maison), les conseils de cuisson, les commandes pour les fêtes et les événements (barbecue, plateaux, pièces à rôtir). Services = rayons et préparations avec prix au kilo quand c'est pertinent, commandes spéciales, plateaux traiteur. FAQ : commande à l'avance, provenance, horaires, paiement, conservation. Ton : généreux, précis, fier du produit.",
        'stock' => [$u('1607623814075-e51df1bdc82f'), $u('1603048297172-c92544798d5a'), $u('1588168333986-5078d3ae3976'), $u('1551218808-94e220e084d2')]],

    'primeur' => ['label' => 'Primeur, épicerie & produits frais', 'color' => '#15803d', 'icon' => 'fa-carrot', 'cta' => 'Commander / nous rendre visite',
        'font' => 'Bricolage Grotesque', 'theme' => 'light', 'hero' => 'bright', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['primeur', 'fruits et legumes', 'fruits et légumes', 'greengrocer', 'epicerie', 'épicerie', 'grocery_store', 'grocery', 'fromager', 'fromagerie', 'cheese', 'cremerie', 'crèmerie', 'produits frais', 'bio', 'organic', 'vrac', 'marche couvert', 'marché couvert', 'halles', 'superette', 'supérette', 'alimentation generale', 'alimentation générale'],
        'brief' => "Primeur, épicerie, fromagerie, magasin bio : la fraîcheur et la saison, les producteurs partenaires, les arrivages, le vrac, les paniers et commandes, la livraison locale. Services = rayons (fruits et légumes de saison, fromages affinés, épicerie fine, vrac, paniers hebdomadaires, plateaux). FAQ : arrivages, bio ou local, commandes et paniers, livraison, horaires, paiement. Ton : chaleureux, concret, gourmand sans excès.",
        'stock' => [$u('1542838132-92c53300491e'), $u('1488459716781-31db52582fe9'), $u('1506617420156-8e4536971650'), $u('1534723452862-4c874018d66d')]],

    'librairie' => ['label' => 'Librairie, papeterie & presse', 'color' => '#92400e', 'icon' => 'fa-book-open', 'cta' => 'Commander / réserver un livre',
        'font' => 'Cormorant Garamond', 'theme' => 'light', 'hero' => 'bright', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['librairie', 'book_store', 'bookstore', 'libraire', 'papeterie', 'stationery', 'presse', 'tabac', 'tabac presse', 'bureau de tabac', 'loto', 'française des jeux', 'bd', 'bande dessinee', 'bande dessinée', 'comics', 'manga', 'jeux de societe', 'jeux de société', 'ludotheque', 'ludothèque'],
        'brief' => "Librairie / papeterie / tabac-presse : la sélection et les coups de cœur, les commandes de livres (délais), les rencontres et dédicaces, les services (presse, jeux, colis, cadeaux), les horaires étendus. Services = univers (littérature, jeunesse, BD, papeterie, presse, jeux) et services pratiques. FAQ : commande d'un titre, délais, horaires, click & collect, cartes cadeaux, événements. Ton : cultivé mais simple, accueillant.",
        'stock' => [$u('1507842217343-583bb7270b66'), $u('1512820790803-83ca734da794'), $u('1521587760476-6c12a4b040da'), $u('1481627834876-b7833e8f5570')]],

    'cycles' => ['label' => 'Vélo, cycles & mobilité douce', 'color' => '#0f766e', 'icon' => 'fa-bicycle', 'cta' => 'Prendre rendez-vous atelier',
        'font' => 'Space Grotesk', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['velo', 'vélo', 'bicycle', 'bicycle_store', 'bike shop', 'cycles', 'cycliste', 'vtt', 'vae', 'velo electrique', 'vélo électrique', 'trottinette', 'reparation velo', 'réparation vélo', 'atelier velo', 'atelier vélo', 'location de velo', 'location de vélo'],
        'brief' => "Magasin et atelier vélo : vente (ville, VTT, route, électrique, enfants), réparation et entretien avec délais, location, essais, accessoires et sécurité, conseils de taille et d'usage. Services = prestations d'atelier avec prix (révision, crevaison, freins, transmission, diagnostic VAE) et gammes de vélos. FAQ : délais atelier, rendez-vous, garantie, aides à l'achat VAE, location, essais. Ton : passionné, pratique, rassurant.",
        'stock' => [$u('1485965120184-e220f721d03e'), $u('1571068316344-75bc76f77890'), $u('1532298229144-0ec0c57515c7'), $u('1511994298241-608e28f14fde')]],

    'maison' => ['label' => 'Décoration, ameublement & bricolage', 'color' => '#a16207', 'icon' => 'fa-couch', 'cta' => 'Nous rendre visite',
        'font' => 'Outfit', 'theme' => 'light', 'hero' => 'editorial', 'order' => $shop, 'modules' => $bookingOn,
        'keywords' => ['decoration', 'décoration', 'home_goods_store', 'furniture_store', 'meuble', 'ameublement', 'literie', 'luminaire', 'cuisiniste', 'cuisines', 'salle de bain', 'carrelage', 'tile', 'quincaillerie', 'hardware_store', 'bricolage', 'droguerie', 'encadrement', 'antiquaire', 'brocante', 'antique'],
        'brief' => "Décoration, ameublement, cuisiniste, bricolage : l'univers et le style, les marques et le sur-mesure, le conseil et la prise de mesures, la livraison et la pose, le service après-vente. Services = univers produits et services (conseil déco, devis cuisine, livraison, montage, retouche). FAQ : délais de livraison, sur-mesure, devis, pose, retours, financement. Ton : inspirant, précis, serviable.",
        'stock' => [$u('1555041469-a586c61ea9bc'), $u('1484101403633-562f891dc89a'), $u('1493663284031-b7e3aefcae8e'), $u('1524758631624-e2822e304c36')]],

    'artisan' => ['label' => 'Artisan multiservices', 'color' => '#f59e0b', 'icon' => 'fa-screwdriver-wrench', 'cta' => 'Demander un devis',
        'font' => 'Sora', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => ['artisan', 'multiservice', 'homme toutes mains', 'bricoleur', 'handyman', 'depann', 'dépann', 'vitrier', 'miroiterie', 'ferronn', 'soudure', 'metalli', 'métalli', 'tapissier', 'reparation', 'réparation'],
        'brief' => "Artisan multiservices / dépannage : petits travaux, montage, réparations, vitrerie, ferronnerie… Mettre en avant la réactivité, les tarifs clairs (heure ou forfait), la propreté, l'assurance. FAQ : délais, tarif horaire et déplacement, devis, fournitures, garantie, zone. Ton : simple, fiable, proche.",
        'stock' => [$u('1504148455328-c376907d081c'), $u('1581244277943-fe4a9c777189'), $u('1523413651479-597eb2da0ad6'), $u('1530124566582-a618bc2615dc')]],

    'service' => ['label' => 'Services', 'color' => '#4f46e5', 'icon' => 'fa-handshake', 'cta' => 'Nous contacter',
        'font' => 'Space Grotesk', 'theme' => 'light', 'hero' => 'split', 'order' => $svc, 'modules' => $bookingOn,
        'keywords' => [],
        'brief' => "Prestataire de services local (secteur non identifié précisément) : décrire clairement l'offre à partir du nom, des types Google, des avis et des photos ; services concrets, parcours simple en 3 étapes, FAQ pratiques (tarifs, délais, zone, rendez-vous). Ton : professionnel, chaleureux, direct.",
        'stock' => [$u('1497366216548-37526070297c'), $u('1521737604893-d14cc237f11d'), $u('1556761175-5973dc0f32e7'), $u('1552664730-d307ca884978')]],
];
