<?php
// Rendu local de sites-vitrines de démonstration (données réalistes) pour captures showcase.
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\View;

$out = getenv('OUT') ?: sys_get_temp_dir().'/showcase';
@mkdir($out, 0777, true);

$U = fn($id, $w = 1600) => "https://images.unsplash.com/photo-$id?w=$w&q=80&auto=format&fit=crop";

$scenarios = [
    'restaurant' => [
        'name' => 'La Table d\'Émile', 'city' => 'Lyon', 'phone' => '04 78 12 34 56',
        'address' => '18 rue Mercière, 69002 Lyon', 'rating' => 4.8, 'reviews_count' => 327,
        'photos' => [
            $U('1517248135467-4c7edcad34c4'), $U('1414235077428-338989a2e8c0'),
            $U('1552566626-52f8b828add9'), $U('1600891964599-f61ba0e24092'),
            $U('1414235077428-338989a2e8c0'), $U('1559339352-11d035aa65de'),
        ],
        'reviews' => [
            ['author' => 'Camille R.', 'rating' => 5, 'text' => 'Une cuisine généreuse et raffinée, un service aux petits soins. Le meilleur repas depuis longtemps, on reviendra sans hésiter.'],
            ['author' => 'Thomas B.', 'rating' => 5, 'text' => 'Cadre chaleureux, produits frais et de saison. La carte change régulièrement, un vrai plaisir à chaque visite.'],
            ['author' => 'Nadia L.', 'rating' => 5, 'text' => 'Accueil formidable et assiettes magnifiques. Rapport qualité-prix imbattable pour ce niveau de gastronomie.'],
        ],
        'c' => [
            'hero_title' => 'La cuisine lyonnaise, sublimée', 'hero_subtitle' => 'Produits de saison, feu de bois et grands crus au cœur du Vieux-Lyon.',
            'tagline' => 'Bistronomie de saison',
            'about_p1' => 'Depuis 2009, La Table d\'Émile revisite les grands classiques lyonnais avec des produits sourcés chez nos producteurs voisins.',
            'about_p2' => 'Une carte courte qui évolue chaque semaine, une cave passionnée et un service qui prend le temps.',
            'badges' => ['Produits locaux', 'Fait maison', 'Cave à vins', 'Terrasse'],
            'stats' => [['v' => '+15 ans', 'l' => 'D\'existence'], ['v' => '4.8/5', 'l' => 'Note Google'], ['v' => '327', 'l' => 'Avis clients']],
            'services' => [
                ['name' => 'Déjeuner du marché', 'desc' => 'Menu 3 plats renouvelé chaque semaine selon le marché.', 'price' => 'dès 24€'],
                ['name' => 'Menu dégustation', 'desc' => '6 services pour un voyage complet à travers notre cuisine.', 'price' => '68€'],
                ['name' => 'Accord mets & vins', 'desc' => 'Chaque plat accompagné d\'un verre choisi par notre sommelier.', 'price' => '+32€'],
                ['name' => 'Privatisation', 'desc' => 'Salle privée jusqu\'à 20 convives pour vos événements.', 'price' => 'Sur devis'],
                ['name' => 'Brunch du dimanche', 'desc' => 'Buffet sucré-salé en continu de 11h à 15h.', 'price' => '29€'],
                ['name' => 'Traiteur', 'desc' => 'Notre cuisine chez vous pour vos réceptions.', 'price' => 'Sur devis'],
            ],
            'faq' => [
                ['q' => 'Faut-il réserver ?', 'a' => 'La réservation est fortement conseillée, surtout le week-end. Vous pouvez réserver par téléphone ou en ligne.'],
                ['q' => 'Proposez-vous des options végétariennes ?', 'a' => 'Oui, chaque menu comporte au moins une option végétarienne, et nous nous adaptons aux allergies.'],
                ['q' => 'Avez-vous une terrasse ?', 'a' => 'Oui, notre terrasse ombragée est ouverte d\'avril à octobre.'],
            ],
            'cta_text' => 'Réservez votre table en quelques secondes, nous nous occupons du reste.',
        ],
    ],
    'beaute' => [
        'name' => 'Studio Éclat', 'city' => 'Paris', 'phone' => '01 45 67 89 10',
        'address' => '42 rue des Martyrs, 75009 Paris', 'rating' => 4.9, 'reviews_count' => 214,
        'photos' => [
            $U('1560066984-138dadb4c035'), $U('1600948836101-f9ffda59d250'),
            $U('1522337660859-02fbefca4d79'), $U('1633681926022-84c23e8cb2d6'),
            $U('1595476108010-b4d1f102b1b1'), $U('1487412947147-5cebf100ffc2'),
        ],
        'reviews' => [
            ['author' => 'Sophie M.', 'rating' => 5, 'text' => 'Équipe adorable et à l\'écoute. Ma coupe et ma couleur sont parfaites, exactement ce que je voulais. Je recommande les yeux fermés.'],
            ['author' => 'Inès K.', 'rating' => 5, 'text' => 'Salon magnifique, ambiance zen et résultat au top. On ressort transformée et détendue.'],
            ['author' => 'Léa P.', 'rating' => 5, 'text' => 'Des vrais professionnels qui conseillent sans forcer. Les produits utilisés sont de grande qualité.'],
        ],
        'c' => [
            'hero_title' => 'Révélez votre plus bel éclat', 'hero_subtitle' => 'Coiffure, couleur et soins sur-mesure dans un écrin de douceur à Paris 9e.',
            'tagline' => 'L\'art de la beauté',
            'about_p1' => 'Studio Éclat réunit une équipe de coloristes et coiffeurs passionnés qui placent votre singularité au cœur de chaque prestation.',
            'about_p2' => 'Diagnostic personnalisé, produits premium et gestes experts pour un résultat qui vous ressemble et qui dure.',
            'badges' => ['Coloristes experts', 'Produits bio', 'Sans rendez-vous', 'Diagnostic offert'],
            'stats' => [['v' => '4.9/5', 'l' => 'Note Google'], ['v' => '+8 ans', 'l' => 'D\'expérience'], ['v' => '214', 'l' => 'Avis clients']],
            'services' => [
                ['name' => 'Coupe & Brushing', 'desc' => 'Diagnostic, coupe sur-mesure et coiffage adapté à votre style.', 'price' => 'dès 45€'],
                ['name' => 'Coloration', 'desc' => 'Couleur professionnelle éclatante et respectueuse du cheveu.', 'price' => 'dès 65€'],
                ['name' => 'Balayage & Ombré', 'desc' => 'Effets lumière naturels réalisés à main levée.', 'price' => 'dès 90€'],
                ['name' => 'Soins profonds', 'desc' => 'Rituels de reconstruction pour des cheveux réparés.', 'price' => 'dès 30€'],
                ['name' => 'Coiffure mariage', 'desc' => 'Essai et mise en beauté pour votre grand jour.', 'price' => 'Sur devis'],
                ['name' => 'Lissage', 'desc' => 'Lissage longue durée pour une chevelure disciplinée.', 'price' => 'dès 120€'],
            ],
            'faq' => [
                ['q' => 'Proposez-vous un diagnostic gratuit ?', 'a' => 'Oui, chaque prestation débute par un diagnostic offert pour définir ensemble le résultat souhaité.'],
                ['q' => 'Peut-on venir sans rendez-vous ?', 'a' => 'Nous accueillons sans rendez-vous selon les disponibilités, mais la réservation garantit votre créneau.'],
                ['q' => 'Vos produits sont-ils naturels ?', 'a' => 'Nous privilégions des gammes bio et vegan, respectueuses du cheveu et de l\'environnement.'],
            ],
            'cta_text' => 'Prenez rendez-vous et laissez notre équipe révéler votre beauté.',
        ],
    ],
    'renovation' => [
        'name' => 'ProRénov Bâtiment', 'city' => 'Bordeaux', 'phone' => '05 56 00 11 22',
        'address' => '7 avenue Thiers, 33100 Bordeaux', 'rating' => 4.7, 'reviews_count' => 156,
        'photos' => [
            $U('1504307651254-35680f356dfd'), $U('1523413651479-597eb2da0ad6'),
            $U('1581094794329-c8112a89af12'), $U('1503387762-592deb58ef4e'),
            $U('1541888946425-d81bb19240f5'), $U('1600585154340-be6161a56a0c'),
        ],
        'reviews' => [
            ['author' => 'Marc D.', 'rating' => 5, 'text' => 'Chantier impeccable, respect des délais et du budget. Équipe sérieuse et propre. Notre rénovation est une réussite totale.'],
            ['author' => 'Julie F.', 'rating' => 5, 'text' => 'Devis clair, conseils pertinents et finitions parfaites. On recommande vivement pour tous travaux.'],
            ['author' => 'Antoine G.', 'rating' => 4, 'text' => 'Très bon travail sur notre isolation. Réactifs et à l\'écoute du début à la fin du projet.'],
        ],
        'c' => [
            'hero_title' => 'Vos travaux, entre de bonnes mains', 'hero_subtitle' => 'Rénovation complète, isolation et aménagement en Gironde, sans mauvaise surprise.',
            'tagline' => 'Bâtir la confiance',
            'about_p1' => 'ProRénov accompagne particuliers et professionnels dans tous leurs projets de rénovation, de la première esquisse à la remise des clés.',
            'about_p2' => 'Un interlocuteur unique, des artisans qualifiés et un suivi de chantier rigoureux pour un résultat à la hauteur de vos attentes.',
            'badges' => ['Devis gratuit', 'Garantie décennale', 'Artisans RGE', 'Délais tenus'],
            'stats' => [['v' => '+12 ans', 'l' => 'D\'expérience'], ['v' => '500+', 'l' => 'Chantiers'], ['v' => '48h', 'l' => 'Devis rapide']],
            'services' => [
                ['name' => 'Rénovation complète', 'desc' => 'De la démolition aux finitions, un chantier clé en main.', 'price' => 'Sur devis'],
                ['name' => 'Isolation thermique', 'desc' => 'Réduisez vos factures avec une isolation performante RGE.', 'price' => 'dès 45€/m²'],
                ['name' => 'Cuisine & salle de bain', 'desc' => 'Conception et pose de vos pièces d\'eau sur-mesure.', 'price' => 'Sur devis'],
                ['name' => 'Peinture & revêtements', 'desc' => 'Murs, sols et plafonds pour une finition impeccable.', 'price' => 'dès 28€/m²'],
                ['name' => 'Extension & combles', 'desc' => 'Gagnez de la surface avec un aménagement optimisé.', 'price' => 'Sur devis'],
                ['name' => 'Électricité & plomberie', 'desc' => 'Mise aux normes et installations par nos experts.', 'price' => 'Sur devis'],
            ],
            'faq' => [
                ['q' => 'Le devis est-il gratuit ?', 'a' => 'Oui, nous nous déplaçons gratuitement pour évaluer votre projet et vous remettons un devis détaillé sous 48h.'],
                ['q' => 'Êtes-vous assurés ?', 'a' => 'Nous disposons de la garantie décennale et d\'une assurance responsabilité civile professionnelle.'],
                ['q' => 'Gérez-vous les aides financières ?', 'a' => 'Nous vous accompagnons dans le montage des dossiers MaPrimeRénov\' et CEE.'],
            ],
            'cta_text' => 'Décrivez-nous votre projet, nous revenons vers vous sous 48h avec un devis clair.',
        ],
    ],
    'sante' => [
        'name' => 'Cabinet Ostéo Concorde', 'city' => 'Nantes', 'phone' => '02 40 11 22 33',
        'address' => '3 place de la Concorde, 44000 Nantes', 'rating' => 4.9, 'reviews_count' => 189,
        'photos' => [
            $U('1519494026892-80bbd2d6fd0d'), $U('1571019613454-1cb2f99b2d8b'),
            $U('1600334129128-685c5582fd35'), $U('1512678080530-7760d81faba6'),
            $U('1519389950473-47ba0277781c'), $U('1538108149393-fbbd81895907'),
        ],
        'reviews' => [
            ['author' => 'Hélène V.', 'rating' => 5, 'text' => 'Praticien à l\'écoute et très compétent. Mes douleurs de dos ont nettement diminué dès la première séance. Merci !'],
            ['author' => 'Romain T.', 'rating' => 5, 'text' => 'Cabinet accueillant, prise de rendez-vous simple en ligne. Explications claires et suivi personnalisé.'],
            ['author' => 'Claire B.', 'rating' => 5, 'text' => 'Une approche douce et efficace. Je recommande pour toute la famille, même les nourrissons.'],
        ],
        'c' => [
            'hero_title' => 'Retrouvez votre équilibre', 'hero_subtitle' => 'Ostéopathie douce pour toute la famille, du nourrisson au sportif, au centre de Nantes.',
            'tagline' => 'Votre bien-être, notre métier',
            'about_p1' => 'Le Cabinet Ostéo Concorde vous accueille dans un espace apaisant pour une prise en charge globale et personnalisée.',
            'about_p2' => 'Une écoute attentive, un diagnostic précis et des techniques adaptées à chaque patient et chaque âge.',
            'badges' => ['RDV en ligne', 'Toute la famille', 'Remboursé mutuelle', 'Accès PMR'],
            'stats' => [['v' => '4.9/5', 'l' => 'Note patients'], ['v' => '+10 ans', 'l' => 'D\'expérience'], ['v' => '24h', 'l' => 'RDV rapide']],
            'services' => [
                ['name' => 'Ostéopathie adulte', 'desc' => 'Traitement des douleurs dorsales, cervicales et articulaires.', 'price' => '60€'],
                ['name' => 'Nourrisson & enfant', 'desc' => 'Accompagnement doux dès les premières semaines de vie.', 'price' => '55€'],
                ['name' => 'Femme enceinte', 'desc' => 'Soulagement des tensions liées à la grossesse.', 'price' => '60€'],
                ['name' => 'Sportif', 'desc' => 'Préparation, récupération et prévention des blessures.', 'price' => '65€'],
                ['name' => 'Troubles digestifs', 'desc' => 'Approche viscérale pour un meilleur confort au quotidien.', 'price' => '60€'],
                ['name' => 'Suivi personnalisé', 'desc' => 'Bilan complet et conseils posturaux adaptés.', 'price' => 'dès 60€'],
            ],
            'faq' => [
                ['q' => 'Comment prendre rendez-vous ?', 'a' => 'La prise de rendez-vous se fait en ligne 24h/24 ou par téléphone aux horaires d\'ouverture.'],
                ['q' => 'Les séances sont-elles remboursées ?', 'a' => 'La plupart des mutuelles prennent en charge tout ou partie des séances d\'ostéopathie.'],
                ['q' => 'Faut-il une ordonnance ?', 'a' => 'Non, l\'ostéopathie est accessible en accès direct, sans prescription médicale préalable.'],
            ],
            'cta_text' => 'Réservez votre consultation en ligne en moins d\'une minute.',
        ],
    ],
    'immobilier' => [
        'name' => 'Horizon Immobilier', 'city' => 'Aix-en-Provence', 'phone' => '04 42 00 55 66',
        'address' => '12 cours Mirabeau, 13100 Aix-en-Provence', 'rating' => 4.8, 'reviews_count' => 143,
        'photos' => [
            $U('1560518883-ce09059eeffa'), $U('1600585154340-be6161a56a0c'),
            $U('1512917774080-9991f1c4c750'), $U('1600607687939-ce8a6c25118c'),
            $U('1600566753086-00f18fb6b3ea'), $U('1600047509807-ba8f99d2cdde'),
        ],
        'reviews' => [
            ['author' => 'Patrick M.', 'rating' => 5, 'text' => 'Vente de notre appartement en 3 semaines au prix souhaité. Équipe professionnelle, disponible et de bon conseil.'],
            ['author' => 'Sandra L.', 'rating' => 5, 'text' => 'Accompagnement parfait pour notre achat. Ils ont compris nos besoins et trouvé la perle rare rapidement.'],
            ['author' => 'David R.', 'rating' => 5, 'text' => 'Estimation juste et honnête, photos superbes. Une agence qui tient ses promesses de A à Z.'],
        ],
        'c' => [
            'hero_title' => 'Votre projet immobilier réussi', 'hero_subtitle' => 'Vente, achat et location de biens d\'exception au cœur du Pays d\'Aix.',
            'tagline' => 'L\'immobilier de confiance',
            'about_p1' => 'Horizon Immobilier met son expertise du marché aixois au service de vos projets, avec exigence et transparence.',
            'about_p2' => 'Estimation précise, mise en valeur professionnelle et négociation dans votre intérêt : nous vous accompagnons à chaque étape.',
            'badges' => ['Estimation offerte', 'Mandats exclusifs', 'Photos pro', 'Réseau national'],
            'stats' => [['v' => '3 sem.', 'l' => 'Délai moyen'], ['v' => '4.8/5', 'l' => 'Note clients'], ['v' => '400+', 'l' => 'Ventes']],
            'services' => [
                ['name' => 'Estimation gratuite', 'desc' => 'Une évaluation précise de votre bien basée sur le marché réel.', 'price' => 'Offert'],
                ['name' => 'Vente accompagnée', 'desc' => 'De la mise en vente à la signature, un suivi complet.', 'price' => 'Sur devis'],
                ['name' => 'Recherche sur-mesure', 'desc' => 'Nous trouvons le bien qui correspond à vos critères.', 'price' => 'Sur devis'],
                ['name' => 'Gestion locative', 'desc' => 'Confiez-nous la gestion sereine de votre patrimoine.', 'price' => 'dès 6%'],
                ['name' => 'Home staging', 'desc' => 'Valorisez votre bien pour vendre plus vite et mieux.', 'price' => 'Sur devis'],
                ['name' => 'Conseil investissement', 'desc' => 'Optimisez votre placement immobilier avec nos experts.', 'price' => 'Offert'],
            ],
            'faq' => [
                ['q' => 'L\'estimation est-elle vraiment gratuite ?', 'a' => 'Oui, nous réalisons une estimation gratuite et sans engagement de votre bien sous 48h.'],
                ['q' => 'Quels sont vos honoraires ?', 'a' => 'Nos honoraires sont transparents et ne sont dus qu\'en cas de vente réussie.'],
                ['q' => 'Travaillez-vous en exclusivité ?', 'a' => 'Nous proposons des mandats exclusifs qui maximisent la visibilité et l\'efficacité de la vente.'],
            ],
            'cta_text' => 'Demandez votre estimation gratuite, nous vous répondons sous 48h.',
        ],
    ],
    'hebergement' => [
        'name' => 'Villa Belrose', 'city' => 'Saint-Tropez', 'phone' => '04 94 00 77 88',
        'address' => 'Route des Plages, 83990 Saint-Tropez', 'rating' => 4.9, 'reviews_count' => 268,
        'photos' => [
            $U('1566073771259-6a8506099945'), $U('1571003123894-1f0594d2b5d9'),
            $U('1582719478250-c89cae4dc85b'), $U('1520250497591-112f2f40a3f4'),
            $U('1618773928121-c32242e63f39'), $U('1590490360182-c33d57733427'),
        ],
        'reviews' => [
            ['author' => 'Élodie C.', 'rating' => 5, 'text' => 'Séjour de rêve, chambres somptueuses et personnel aux petits soins. La vue sur la mer est à couper le souffle.'],
            ['author' => 'Nicolas B.', 'rating' => 5, 'text' => 'Un havre de paix, calme absolu et prestations haut de gamme. Le petit-déjeuner est un régal. On reviendra.'],
            ['author' => 'Marina S.', 'rating' => 5, 'text' => 'Accueil chaleureux, piscine magnifique et emplacement idéal. Tout était parfait du début à la fin.'],
        ],
        'c' => [
            'hero_title' => 'L\'art de recevoir sur la Côte', 'hero_subtitle' => 'Chambres d\'exception, piscine et vue mer à quelques minutes des plages de Saint-Tropez.',
            'tagline' => 'Une parenthèse d\'exception',
            'about_p1' => 'La Villa Belrose vous ouvre les portes d\'un domaine intimiste où le luxe se conjugue avec la douceur de vivre provençale.',
            'about_p2' => 'Chaque détail est pensé pour votre confort : literie haut de gamme, service personnalisé et cadre enchanteur.',
            'badges' => ['Vue mer', 'Piscine chauffée', 'Petit-déj inclus', 'Parking privé'],
            'stats' => [['v' => '4.9/5', 'l' => 'Note voyageurs'], ['v' => '12', 'l' => 'Suites'], ['v' => '268', 'l' => 'Avis']],
            'services' => [
                ['name' => 'Suite Prestige', 'desc' => 'Espace généreux, terrasse privée et vue panoramique sur la mer.', 'price' => 'dès 320€'],
                ['name' => 'Chambre Deluxe', 'desc' => 'Confort raffiné et décoration provençale élégante.', 'price' => 'dès 210€'],
                ['name' => 'Petit-déjeuner', 'desc' => 'Buffet gourmand aux produits frais et locaux.', 'price' => 'Inclus'],
                ['name' => 'Spa & bien-être', 'desc' => 'Massages et soins pour une détente absolue.', 'price' => 'Sur devis'],
                ['name' => 'Événements privés', 'desc' => 'Mariages et réceptions dans un cadre idyllique.', 'price' => 'Sur devis'],
                ['name' => 'Conciergerie', 'desc' => 'Réservations, transferts et expériences sur-mesure.', 'price' => 'Offert'],
            ],
            'faq' => [
                ['q' => 'Le petit-déjeuner est-il inclus ?', 'a' => 'Oui, un généreux petit-déjeuner buffet est inclus dans toutes nos formules.'],
                ['q' => 'Proposez-vous des transferts ?', 'a' => 'Notre conciergerie organise vos transferts aéroport et vos déplacements sur demande.'],
                ['q' => 'Les animaux sont-ils acceptés ?', 'a' => 'Les animaux de petite taille sont les bienvenus, merci de nous prévenir à la réservation.'],
            ],
            'cta_text' => 'Réservez votre séjour et laissez-vous porter par l\'art de vivre méditerranéen.',
        ],
    ],
];

foreach ($scenarios as $sector => $s) {
    $cfg = config("sectors.$sector") ?? config('sectors.service');
    $b = [
        'name' => $s['name'], 'city' => $s['city'], 'phone' => $s['phone'], 'address' => $s['address'],
        'rating' => $s['rating'], 'reviews_count' => $s['reviews_count'],
        'photos' => $s['photos'], 'reviews' => $s['reviews'],
        'opening_hours' => ['lundi: 09:00 – 19:00', 'mardi: 09:00 – 19:00', 'mercredi: 09:00 – 19:00', 'jeudi: 09:00 – 19:00', 'vendredi: 09:00 – 19:00', 'samedi: 10:00 – 18:00', 'dimanche: Fermé'],
    ];
    $html = View::make('generated.site', [
        'b' => $b, 'c' => $s['c'], 'sector' => $sector,
        'label' => $cfg['label'], 'color' => $cfg['color'], 'icon' => $cfg['icon'], 'cta' => $cfg['cta'],
        'mapsKey' => '', 'slug' => $sector.'-demo',
    ])->render();
    file_put_contents("$out/$sector.html", $html);
    echo "rendered $sector (".strlen($html)." bytes)\n";
}
echo "DONE -> $out\n";
