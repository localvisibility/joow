<?php
// Jeu de données local pour tester le Studio (DB SQLite locale). Usage : php scripts/seed_local.php [slug]
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Site;

$slug = $argv[1] ?? 'demo-table-emile';
$U = fn ($id, $w = 1600) => "https://images.unsplash.com/photo-$id?w=$w&q=80&auto=format&fit=crop";
$business = [
    'name' => "La Table d'Émile", 'city' => 'Lyon', 'phone' => '04 78 12 34 56', 'address' => '18 rue Mercière, 69002 Lyon',
    'rating' => 4.8, 'reviews_count' => 327, 'opening_hours' => ['lundi: Fermé', 'mardi: 12:00–14:00, 19:00–22:30', 'mercredi: 12:00–14:00, 19:00–22:30', 'jeudi: 12:00–14:00, 19:00–22:30', 'vendredi: 12:00–14:00, 19:00–23:00', 'samedi: 19:00–23:00', 'dimanche: Fermé'],
    'photos' => [$U('1517248135467-4c7edcad34c4'), $U('1414235077428-338989a2e8c0'), $U('1552566626-52f8b828add9'), $U('1600891964599-f61ba0e24092'), $U('1559339352-11d035aa65de'), $U('1555396273-367ea4eb4db5')],
    'reviews' => [
        ['author' => 'Camille R.', 'rating' => 5, 'text' => 'Une cuisine généreuse et raffinée, un service aux petits soins. Le meilleur repas depuis longtemps.'],
        ['author' => 'Thomas B.', 'rating' => 5, 'text' => 'Cadre chaleureux, produits frais et de saison. La carte change régulièrement, un vrai plaisir à chaque visite.'],
        ['author' => 'Nadia L.', 'rating' => 5, 'text' => 'Accueil formidable et assiettes magnifiques. Rapport qualité-prix imbattable pour ce niveau de gastronomie.'],
        ['author' => 'Julien M.', 'rating' => 4, 'text' => 'Très bonne adresse, terrasse agréable. Pensez à réserver le week-end.'],
    ],
];
$content = [
    'hero_title' => 'La cuisine lyonnaise, sublimée', 'hero_subtitle' => 'Produits de saison, feu de bois et grands crus au cœur du Vieux-Lyon.', 'tagline' => 'Bistronomie de saison',
    'about_p1' => "Depuis 2009, La Table d'Émile revisite les grands classiques lyonnais avec des produits sourcés chez nos producteurs voisins.",
    'about_p2' => 'Une carte courte qui évolue chaque semaine, une cave passionnée et un service qui prend le temps.',
    'badges' => ['Produits locaux', 'Fait maison', 'Cave à vins', 'Terrasse'],
    'stats' => [['v' => '+15 ans', 'l' => "D'existence"], ['v' => '4.8/5', 'l' => 'Note Google'], ['v' => '327', 'l' => 'Avis clients']],
    'services' => [
        ['name' => 'Déjeuner du marché', 'desc' => 'Menu 3 plats renouvelé chaque semaine selon le marché.', 'price' => 'dès 24€'],
        ['name' => 'Menu dégustation', 'desc' => '6 services pour un voyage complet à travers notre cuisine.', 'price' => '68€'],
        ['name' => 'Accord mets & vins', 'desc' => "Chaque plat accompagné d'un verre choisi par notre sommelier.", 'price' => '+32€'],
        ['name' => 'Privatisation', 'desc' => "Salle privée jusqu'à 20 convives pour vos événements.", 'price' => 'Sur devis'],
    ],
    'faq' => [
        ['q' => 'Faut-il réserver ?', 'a' => 'La réservation est fortement conseillée, surtout le week-end.'],
        ['q' => 'Proposez-vous des options végétariennes ?', 'a' => 'Oui, chaque menu comporte au moins une option végétarienne.'],
    ],
    'process' => [['title' => 'Vous réservez', 'desc' => 'En ligne ou par téléphone, en 30 secondes.'], ['title' => 'On prépare votre table', 'desc' => 'Vos préférences sont notées.'], ['title' => 'Vous savourez', 'desc' => "Le reste, c'est notre métier."]],
    'cta_text' => 'Réservez votre table en quelques secondes, nous nous occupons du reste.',
];

$site = Site::updateOrCreate(['slug' => $slug], [
    'name' => $business['name'], 'sector' => 'restaurant', 'status' => 'preview', 'city' => 'Lyon', 'rating' => 4.8, 'reviews_count' => 327,
    'place_id' => 'ChIJ-demo', 'preview_url' => "https://$slug.joow.fr", 'source' => 'joow-public',
    'site_data' => ['business' => $business, 'content' => $content, 'images' => [], 'booking' => [], 'pages' => []],
    'modules' => ['booking' => true, 'reviews' => true],
]);
echo json_encode(['slug' => $site->slug, 'token' => $site->editToken()]), "\n";
