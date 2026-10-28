<?php
// Vitrine v2 : 10 sites exemples rendus avec le VRAI gabarit, designs volontairement contrastés.
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\View;

$out = getenv('OUT') ?: sys_get_temp_dir().'/showcase2';
@mkdir($out, 0777, true);
$S = fn ($k) => config("sectors.$k.stock") ?? [];
$hours = fn ($o = '08:00', $c = '19:00', $sun = 'Fermé') => ["lundi: $o – $c", "mardi: $o – $c", "mercredi: $o – $c", "jeudi: $o – $c", "vendredi: $o – $c", "samedi: $o – $c", "dimanche: $sun"];
$rev = fn (...$r) => array_map(fn ($x) => ['author' => $x[0], 'rating' => $x[1] ?? 5, 'text' => $x[2]], $r);

$sites = [
  'boulangerie' => ['sector' => 'boulangerie', 'design' => ['theme' => 'light', 'hero' => 'bright', 'font' => 'Fraunces', 'accent' => '#b45309'],
    'b' => ['name' => 'Maison Fournil', 'city' => 'Annecy', 'phone' => '04 50 27 18 64', 'address' => '12 rue Sainte-Claire, 74000 Annecy', 'rating' => 4.9, 'reviews_count' => 612, 'photos' => array_merge($S('boulangerie'), $S('traiteur'))],
    'c' => ['tagline' => 'Boulangerie au levain naturel', 'hero_title' => 'Le pain qui se mérite, chaque matin.', 'hero_subtitle' => 'Levain de 48 h, farines bio de Haute-Savoie et viennoiseries pur beurre, au cœur de la vieille ville.',
      'badges' => ['Levain naturel', 'Farines bio locales', 'Pur beurre', 'Commande en ligne'], 'stats' => [['v' => '48 h', 'l' => 'De fermentation'], ['v' => '4.9/5', 'l' => '612 avis'], ['v' => '6 h', 'l' => 'Ouverture']],
      'about_p1' => 'Depuis 2014, Camille et Hugo pétrissent à la main des pains au levain qui se gardent une semaine.', 'about_p2' => 'Gâteaux de fête, buffets et commandes spéciales sont préparés à la demande, 48 h à l’avance.',
      'services' => [['name' => 'Pains au levain', 'desc' => 'Campagne, seigle, épeautre et graines, cuits au four à sole.', 'price' => 'dès 3,90 €'], ['name' => 'Viennoiseries pur beurre', 'desc' => 'Croissants feuilletés trois jours, pains au chocolat, kouign-amann.', 'price' => 'dès 1,40 €'], ['name' => 'Gâteaux de fête', 'desc' => 'Entremets et pièces sur commande, de 6 à 40 parts.', 'price' => 'dès 28 €'], ['name' => 'Buffets salés', 'desc' => 'Mini-sandwichs, quiches et feuilletés pour vos événements.', 'price' => 'sur devis']],
      'faq' => [['q' => 'Peut-on commander en ligne ?', 'a' => 'Oui, 48 h à l’avance pour les gâteaux, la veille pour le pain.'], ['q' => 'Faites-vous du sans gluten ?', 'a' => 'Un pain sans gluten est cuit chaque vendredi, sur commande.']], 'cta_text' => 'Commandez la veille, récupérez votre pain encore tiède.'],
    'reviews' => $rev(['Élise M.', 5, 'Le meilleur levain d’Annecy, croûte épaisse et mie parfaite. On fait le détour chaque samedi.'], ['Pierre D.', 5, 'Gâteau d’anniversaire magnifique et délicieux, commandé en deux minutes sur le site.'], ['Nora B.', 5, 'Des croissants comme on n’en trouve plus. Équipe adorable.'])],

  'fleuriste' => ['sector' => 'fleuriste', 'design' => ['theme' => 'light', 'hero' => 'poster', 'font' => 'Cormorant Garamond', 'accent' => '#be185d'],
    'b' => ['name' => 'Atelier Pétale', 'city' => 'Paris 11e', 'phone' => '01 43 57 22 90', 'address' => '48 rue de la Roquette, 75011 Paris', 'rating' => 4.9, 'reviews_count' => 287, 'photos' => array_merge($S('fleuriste'), $S('evenementiel'))],
    'c' => ['tagline' => 'Fleuriste créateur · Paris 11e', 'hero_title' => 'Des fleurs qui racontent quelque chose.', 'hero_subtitle' => 'Bouquets de saison, fleurs françaises et compositions de mariage, livrés le jour même dans Paris.',
      'badges' => ['Fleurs françaises', 'Livraison le jour même', 'Mariages & événements', 'Abonnement floral'], 'stats' => [['v' => '80 %', 'l' => 'Fleurs françaises'], ['v' => '3 h', 'l' => 'Délai de livraison'], ['v' => '4.9/5', 'l' => 'Note Google']],
      'about_p1' => 'Inès a ouvert l’atelier en 2019 avec une idée simple : des fleurs de saison, cultivées le plus près possible.', 'about_p2' => 'Chaque bouquet est unique, composé devant vous ou à distance d’après vos envies.',
      'services' => [['name' => 'Bouquets de saison', 'desc' => 'Composés le matin même, selon les arrivages.', 'price' => 'dès 35 €'], ['name' => 'Mariages', 'desc' => 'Bouquet de mariée, boutonnières, arche et centres de table.', 'price' => 'sur devis'], ['name' => 'Abonnement floral', 'desc' => 'Un bouquet livré chaque semaine, chez vous ou au bureau.', 'price' => 'dès 29 €/sem.'], ['name' => 'Deuil', 'desc' => 'Couronnes et gerbes réalisées avec délicatesse.', 'price' => 'dès 90 €']],
      'faq' => [['q' => 'Livrez-vous le jour même ?', 'a' => 'Oui, pour toute commande avant 14 h dans Paris intra-muros.'], ['q' => 'Puis-je choisir les fleurs ?', 'a' => 'Bien sûr, indiquez vos couleurs et fleurs préférées en commandant.']], 'cta_text' => 'Commandez avant 14 h, livré avant ce soir.'],
    'reviews' => $rev(['Julie R.', 5, 'Un bouquet sublime, encore plus beau qu’en photo. Livré en deux heures.'], ['Marc T.', 5, 'Inès a fleuri notre mariage : tous nos invités en parlent encore.'], ['Sarah L.', 5, 'L’abonnement hebdo a changé mon bureau. Toujours une surprise.'])],

  'barbier' => ['sector' => 'barbier', 'design' => ['theme' => 'dark', 'hero' => 'split', 'font' => 'Bricolage Grotesque', 'accent' => '#c2410c'],
    'b' => ['name' => 'Barbier du Port', 'city' => 'Marseille', 'phone' => '04 91 33 72 18', 'address' => '7 quai de Rive Neuve, 13007 Marseille', 'rating' => 4.8, 'reviews_count' => 945, 'photos' => array_merge($S('barbier'), $S('coiffeur'))],
    'c' => ['tagline' => 'Barbier · Vieux-Port', 'hero_title' => 'Coupe nette. Barbe taillée. Sans attendre.', 'hero_subtitle' => 'Dégradés, rasage à l’ancienne et soins de barbe, réservables en 20 secondes, 7 jours sur 7.',
      'badges' => ['Réservation en ligne', 'Rasage serviette chaude', 'Ouvert le dimanche', 'Produits naturels'], 'stats' => [['v' => '945', 'l' => 'Avis Google'], ['v' => '7/7', 'l' => 'Ouvert'], ['v' => '25 min', 'l' => 'Coupe express']],
      'about_p1' => 'Quatre barbiers passionnés, un fauteuil des années 50 et une playlist soignée.', 'about_p2' => 'On prend le temps de comprendre ce que vous voulez, puis on le fait parfaitement.',
      'services' => [['name' => 'Coupe & dégradé', 'desc' => 'Ciseaux ou tondeuse, finition au rasoir.', 'price' => '28 €'], ['name' => 'Taille de barbe', 'desc' => 'Contours nets, huile et baume.', 'price' => '18 €'], ['name' => 'Rasage traditionnel', 'desc' => 'Serviette chaude, coupe-chou, après-rasage.', 'price' => '32 €'], ['name' => 'Coupe + barbe', 'desc' => 'La totale, en 45 minutes.', 'price' => '42 €']],
      'faq' => [['q' => 'Sans rendez-vous ?', 'a' => 'Oui selon disponibilités, mais la réservation en ligne garantit votre créneau.']], 'cta_text' => 'Réservez votre fauteuil en 20 secondes.'],
    'reviews' => $rev(['Karim B.', 5, 'Le meilleur dégradé de Marseille, et toujours à l’heure.'], ['Thomas G.', 5, 'Rasage à l’ancienne incroyable. Je ne vais plus ailleurs.'], ['Yanis A.', 5, 'Ambiance au top, réservation hyper simple.'])],

  'sport' => ['sector' => 'sport', 'design' => ['theme' => 'dark', 'hero' => 'poster', 'font' => 'Sora', 'accent' => '#ea580c'],
    'b' => ['name' => 'Forge Athletic', 'city' => 'Lille', 'phone' => '03 20 55 41 09', 'address' => '22 rue Gambetta, 59000 Lille', 'rating' => 4.9, 'reviews_count' => 403, 'photos' => array_merge($S('sport'), $S('coach'))],
    'c' => ['tagline' => 'Coaching & cross-training · Lille', 'hero_title' => 'Plus fort chaque semaine.', 'hero_subtitle' => 'Petits groupes de 8, coachs diplômés, programmes suivis. Votre première séance est offerte.',
      'badges' => ['Séance d’essai offerte', 'Groupes de 8 max', 'Coachs diplômés d’État', 'Suivi personnalisé'], 'stats' => [['v' => '8', 'l' => 'Personnes max'], ['v' => '40', 'l' => 'Cours / semaine'], ['v' => '4.9/5', 'l' => 'Note Google']],
      'about_p1' => 'Une salle pensée pour progresser, pas pour se perdre au milieu des machines.', 'about_p2' => 'Chaque membre a un programme, des objectifs et un coach qui le connaît.',
      'services' => [['name' => 'Cross-training', 'desc' => 'Force, cardio et mobilité en petits groupes.', 'price' => 'dès 69 €/mois'], ['name' => 'Coaching individuel', 'desc' => 'Programme sur mesure, bilan tous les mois.', 'price' => '55 €/séance'], ['name' => 'Remise en forme', 'desc' => 'Pour reprendre en douceur, sans pression.', 'price' => 'dès 59 €/mois'], ['name' => 'Entreprises', 'desc' => 'Séances pour vos équipes, sur place ou chez vous.', 'price' => 'sur devis']],
      'faq' => [['q' => 'Je n’ai jamais fait de sport, est-ce pour moi ?', 'a' => 'Oui : chaque exercice est adapté à votre niveau dès la première séance.']], 'cta_text' => 'Réservez votre séance d’essai offerte.'],
    'reviews' => $rev(['Camille P.', 5, 'En 4 mois, j’ai plus progressé qu’en 3 ans de salle classique.'], ['Antoine V.', 5, 'Coachs ultra attentifs, ambiance motivante sans jugement.'], ['Léa F.', 5, 'Les petits groupes changent tout. Je ne rate plus une séance.'])],

  'architecte' => ['sector' => 'architecte', 'design' => ['theme' => 'light', 'hero' => 'bright', 'font' => 'Space Grotesk', 'accent' => '#0f766e'],
    'b' => ['name' => 'Atelier Nord', 'city' => 'Nantes', 'phone' => '02 40 12 76 33', 'address' => '5 quai de la Fosse, 44000 Nantes', 'rating' => 5.0, 'reviews_count' => 64, 'photos' => array_merge($S('architecte'), $S('renovation'))],
    'c' => ['tagline' => 'Architecture & intérieur', 'hero_title' => 'Des lieux pensés pour la vie qu’on y mène.', 'hero_subtitle' => 'Rénovation, extension et architecture d’intérieur, de l’esquisse au chantier, à Nantes et en Loire-Atlantique.',
      'badges' => ['Architectes DPLG', 'Permis de construire', 'Suivi de chantier', 'Rendu 3D'], 'stats' => [['v' => '120+', 'l' => 'Projets livrés'], ['v' => '5.0/5', 'l' => 'Note Google'], ['v' => '15 ans', 'l' => 'D’expérience']],
      'about_p1' => 'Lucie et Thomas dessinent des espaces lumineux, sobres et durables.', 'about_p2' => 'Nous vous accompagnons du premier croquis à la remise des clés.',
      'services' => [['name' => 'Rénovation complète', 'desc' => 'Appartements et maisons, redistribution des espaces.', 'price' => 'sur devis'], ['name' => 'Extension', 'desc' => 'Bois, verre ou béton, permis inclus.', 'price' => 'sur devis'], ['name' => 'Architecture d’intérieur', 'desc' => 'Cuisines, salles de bains, mobilier sur mesure.', 'price' => 'sur devis'], ['name' => 'Étude de faisabilité', 'desc' => 'Visite, contraintes, budget : en une semaine.', 'price' => 'dès 450 €']],
      'faq' => [['q' => 'Intervenez-vous hors de Nantes ?', 'a' => 'Oui, dans toute la Loire-Atlantique et en Vendée.']], 'cta_text' => 'Parlons de votre projet, le premier rendez-vous est offert.'],
    'reviews' => $rev(['Hélène C.', 5, 'Notre maison est méconnaissable. Écoute, créativité et chantier parfaitement suivi.'], ['Julien M.', 5, 'Une extension magnifique, livrée dans les temps et le budget.'], ['Sophie N.', 5, 'Le rendu 3D nous a permis de tout valider avant de commencer.'])],

  'dentiste' => ['sector' => 'dentiste', 'design' => ['theme' => 'light', 'hero' => 'center', 'font' => 'Manrope', 'accent' => '#0284c7'],
    'b' => ['name' => 'Cabinet Lumière', 'city' => 'Lyon 6e', 'phone' => '04 78 24 61 50', 'address' => '31 cours Franklin Roosevelt, 69006 Lyon', 'rating' => 4.9, 'reviews_count' => 521, 'photos' => array_merge($S('dentiste'), $S('sante'))],
    'c' => ['tagline' => 'Cabinet dentaire · Lyon 6e', 'hero_title' => 'Des soins doux, un sourire serein.', 'hero_subtitle' => 'Soins, implants, orthodontie invisible et blanchiment. Rendez-vous en ligne, y compris le samedi matin.',
      'badges' => ['RDV en ligne 24h/24', 'Tiers payant', 'Urgences le jour même', 'Accès PMR'], 'stats' => [['v' => '4', 'l' => 'Praticiens'], ['v' => '24 h', 'l' => 'Délai urgence'], ['v' => '4.9/5', 'l' => '521 avis']],
      'about_p1' => 'Une équipe de quatre praticiens et un plateau technique récent, dans un cadre apaisant.', 'about_p2' => 'Nous prenons le temps d’expliquer chaque soin et chaque devis.',
      'services' => [['name' => 'Soins & détartrage', 'desc' => 'Bilan complet et soins conservateurs.', 'price' => 'conventionné'], ['name' => 'Implants', 'desc' => 'Pose et prothèse, plan de traitement détaillé.', 'price' => 'sur devis'], ['name' => 'Aligneurs invisibles', 'desc' => 'Orthodontie adulte discrète.', 'price' => 'sur devis'], ['name' => 'Blanchiment', 'desc' => 'Au fauteuil ou à domicile.', 'price' => 'dès 390 €']],
      'faq' => [['q' => 'Prenez-vous de nouveaux patients ?', 'a' => 'Oui, réservez directement en ligne votre premier rendez-vous.']], 'cta_text' => 'Votre rendez-vous en ligne, en 30 secondes.'],
    'reviews' => $rev(['Marion G.', 5, 'Moi qui avais peur du dentiste, je suis enfin sereine. Équipe d’une grande douceur.'], ['Paul R.', 5, 'Urgence prise le jour même, soin impeccable.'], ['Inès K.', 5, 'Explications claires, devis transparent, résultat parfait.'])],

  'caviste' => ['sector' => 'caviste', 'design' => ['theme' => 'dark', 'hero' => 'editorial', 'font' => 'Playfair Display', 'accent' => '#9f1239'],
    'b' => ['name' => 'La Cave Sauvage', 'city' => 'Bordeaux', 'phone' => '05 56 44 19 82', 'address' => '18 rue Notre-Dame, 33000 Bordeaux', 'rating' => 4.9, 'reviews_count' => 336, 'photos' => array_merge($S('caviste'), $S('bar'))],
    'c' => ['tagline' => 'Cave & bar à vins nature', 'hero_title' => 'Des vins vivants, choisis un par un.', 'hero_subtitle' => '400 références de vignerons indépendants, dégustations le jeudi et planches à partager aux Chartrons.',
      'badges' => ['400 références', 'Vins nature & bio', 'Dégustations le jeudi', 'Click & collect'], 'stats' => [['v' => '400', 'l' => 'Références'], ['v' => '60', 'l' => 'Vignerons'], ['v' => '4.9/5', 'l' => 'Note Google']],
      'about_p1' => 'Mathieu sillonne les vignobles pour dénicher des cuvées sincères, souvent introuvables ailleurs.', 'about_p2' => 'Au bar, on ouvre une bouteille, on partage une planche et on discute.',
      'services' => [['name' => 'Cave', 'desc' => 'Vins nature, bio et biodynamie, conseils personnalisés.', 'price' => 'dès 9 €'], ['name' => 'Bar à vins', 'desc' => 'Au verre ou à la bouteille, planches de producteurs.', 'price' => 'dès 6 €'], ['name' => 'Dégustations', 'desc' => 'Chaque jeudi soir, un vigneron invité.', 'price' => '25 €'], ['name' => 'Coffrets cadeaux', 'desc' => 'Composés sur mesure, livrés à Bordeaux.', 'price' => 'dès 45 €']],
      'faq' => [['q' => 'Faut-il réserver ?', 'a' => 'Pour le bar le week-end et les dégustations, c’est conseillé.']], 'cta_text' => 'Réservez votre table ou votre dégustation.'],
    'reviews' => $rev(['Clara D.', 5, 'Des conseils d’une justesse rare, et des vins qu’on ne trouve nulle part.'], ['Hugo S.', 5, 'Le jeudi dégustation est devenu notre rituel.'], ['Amandine P.', 5, 'Cadre superbe, planches généreuses, accueil parfait.'])],

  'paysagiste' => ['sector' => 'paysagiste', 'design' => ['theme' => 'light', 'hero' => 'poster', 'font' => 'Fraunces', 'accent' => '#15803d'],
    'b' => ['name' => 'Vert d’Eau Paysage', 'city' => 'Toulouse', 'phone' => '05 61 78 40 25', 'address' => '9 chemin de Lardenne, 31100 Toulouse', 'rating' => 4.9, 'reviews_count' => 158, 'photos' => array_merge($S('paysagiste'), $S('hebergement'))],
    'c' => ['tagline' => 'Paysagiste concepteur · Toulouse', 'hero_title' => 'Votre jardin, en mieux. Toute l’année.', 'hero_subtitle' => 'Création de jardins méditerranéens, terrasses bois et entretien sous contrat, avec 50 % de crédit d’impôt.',
      'badges' => ['Devis gratuit sous 48 h', 'Crédit d’impôt 50 %', 'Jardins économes en eau', 'Garantie décennale'], 'stats' => [['v' => '350+', 'l' => 'Jardins créés'], ['v' => '50 %', 'l' => 'Crédit d’impôt'], ['v' => '48 h', 'l' => 'Devis']],
      'about_p1' => 'Une équipe de six jardiniers passionnés, formés aux jardins secs et aux essences locales.', 'about_p2' => 'Nous concevons des jardins beaux toute l’année, qui demandent peu d’eau et d’entretien.',
      'services' => [['name' => 'Création de jardin', 'desc' => 'Plan, plantations, arrosage goutte-à-goutte.', 'price' => 'sur devis'], ['name' => 'Terrasses & clôtures', 'desc' => 'Bois, pierre, pergolas et brise-vue.', 'price' => 'sur devis'], ['name' => 'Entretien annuel', 'desc' => 'Tonte, taille, désherbage, 50 % déductibles.', 'price' => 'dès 89 €/mois'], ['name' => 'Élagage', 'desc' => 'Arbres de grande hauteur, en sécurité.', 'price' => 'sur devis']],
      'faq' => [['q' => 'Le crédit d’impôt s’applique-t-il ?', 'a' => 'Oui, sur l’entretien : 50 % des sommes versées sont déductibles.']], 'cta_text' => 'Envoyez une photo de votre jardin, devis sous 48 h.'],
    'reviews' => $rev(['Bernard L.', 5, 'Notre jardin est devenu notre pièce préférée. Travail soigné et conseils précieux.'], ['Anaïs T.', 5, 'Terrasse magnifique, chantier propre et rapide.'], ['Olivier M.', 5, 'Contrat d’entretien impeccable, toujours ponctuels.'])],

  'restaurant' => ['sector' => 'restaurant', 'design' => ['theme' => 'dark', 'hero' => 'editorial', 'font' => 'Playfair Display', 'accent' => '#e11d48'],
    'b' => ['name' => 'La Table d’Émile', 'city' => 'Lyon', 'phone' => '04 78 12 34 56', 'address' => '18 rue Mercière, 69002 Lyon', 'rating' => 4.8, 'reviews_count' => 327, 'photos' => array_merge($S('restaurant'), $S('bar'))],
    'c' => ['tagline' => 'Bistronomie de saison', 'hero_title' => 'La cuisine lyonnaise, sublimée', 'hero_subtitle' => 'Produits de saison, feu de bois et grands crus au cœur du Vieux-Lyon.',
      'badges' => ['Produits locaux', 'Fait maison', 'Cave à vins', 'Terrasse'], 'stats' => [['v' => '+15 ans', 'l' => 'D’existence'], ['v' => '4.8/5', 'l' => 'Note Google'], ['v' => '327', 'l' => 'Avis clients']],
      'about_p1' => 'Depuis 2009, La Table d’Émile revisite les grands classiques lyonnais avec des produits de producteurs voisins.', 'about_p2' => 'Une carte courte qui évolue chaque semaine, une cave passionnée et un service qui prend le temps.',
      'services' => [['name' => 'Déjeuner du marché', 'desc' => 'Menu 3 plats renouvelé chaque semaine.', 'price' => 'dès 24 €'], ['name' => 'Menu dégustation', 'desc' => '6 services pour un voyage complet.', 'price' => '68 €'], ['name' => 'Accord mets & vins', 'desc' => 'Un verre choisi par notre sommelier pour chaque plat.', 'price' => '+32 €'], ['name' => 'Privatisation', 'desc' => 'Salle privée jusqu’à 20 convives.', 'price' => 'sur devis']],
      'faq' => [['q' => 'Faut-il réserver ?', 'a' => 'Fortement conseillé le week-end, en ligne en quelques secondes.']], 'cta_text' => 'Réservez votre table en quelques secondes.'],
    'reviews' => $rev(['Camille R.', 5, 'Une cuisine généreuse et raffinée, un service aux petits soins.'], ['Thomas B.', 5, 'Cadre chaleureux, produits frais et de saison.'], ['Nadia L.', 5, 'Rapport qualité-prix imbattable pour ce niveau.'])],

  'hebergement' => ['sector' => 'hebergement', 'design' => ['theme' => 'light', 'hero' => 'bright', 'font' => 'Cormorant Garamond', 'accent' => '#0e7490'],
    'b' => ['name' => 'Villa Belrose', 'city' => 'Saint-Tropez', 'phone' => '04 94 97 22 18', 'address' => 'Route des Plages, 83990 Saint-Tropez', 'rating' => 4.9, 'reviews_count' => 218, 'photos' => array_merge($S('hebergement'), $S('paysagiste'))],
    'c' => ['tagline' => 'Maison d’hôtes · Saint-Tropez', 'hero_title' => 'L’art de recevoir, face à la mer.', 'hero_subtitle' => 'Six chambres, une piscine à débordement et le petit-déjeuner servi sous les pins. Réservez au meilleur prix, en direct.',
      'badges' => ['Meilleur prix garanti', 'Piscine chauffée', 'Petit-déjeuner inclus', 'Plage à 300 m'], 'stats' => [['v' => '6', 'l' => 'Chambres'], ['v' => '300 m', 'l' => 'De la plage'], ['v' => '4.9/5', 'l' => '218 avis']],
      'about_p1' => 'Une villa provençale restaurée avec soin, entre pins parasols et vignes.', 'about_p2' => 'Claire et Antoine vous accueillent comme des amis, avec leurs meilleures adresses.',
      'services' => [['name' => 'Chambre Jardin', 'desc' => 'Terrasse privée, vue sur les pins.', 'price' => 'dès 190 €'], ['name' => 'Suite Mer', 'desc' => 'Balcon face à la mer, baignoire îlot.', 'price' => 'dès 320 €'], ['name' => 'Villa entière', 'desc' => 'Pour vos fêtes de famille, jusqu’à 14 personnes.', 'price' => 'sur demande'], ['name' => 'Table d’hôtes', 'desc' => 'Dîner provençal le vendredi soir.', 'price' => '55 €']],
      'faq' => [['q' => 'Le petit-déjeuner est-il inclus ?', 'a' => 'Oui, servi de 8 h à 11 h en terrasse.']], 'cta_text' => 'Réservez en direct, au meilleur prix.'],
    'reviews' => $rev(['Charlotte V.', 5, 'Un lieu magique, des hôtes adorables. On reviendra chaque été.'], ['James W.', 5, 'Breathtaking view, perfect breakfast, impeccable rooms.'], ['Mathilde F.', 5, 'La piscine au coucher du soleil, inoubliable.'])],
];

foreach ($sites as $key => $s) {
    $cfg = config("sectors.{$s['sector']}");
    $b = $s['b'] + ['reviews' => $s['reviews'], 'opening_hours' => $hours()];
    $b['photos'] = array_values(array_unique($b['photos']));
    $html = View::make('generated.site', [
        'b' => $b, 'c' => $s['c'], 'sector' => $s['sector'],
        'label' => $cfg['label'], 'color' => $s['design']['accent'], 'icon' => $cfg['icon'], 'cta' => $cfg['cta'], 'font' => $s['design']['font'],
        'slug' => $key.'-demo', 'images' => ['hero' => $b['photos'][0], 'about' => $b['photos'][1], 'gallery' => array_slice($b['photos'], 2, 6)],
        'design' => ['font' => $s['design']['font'], 'theme' => $s['design']['theme'], 'hero' => $s['design']['hero'], 'stock' => $cfg['stock'] ?? [], 'order' => $cfg['order'] ?? []],
    ])->render();
    file_put_contents("$out/$key.html", $html);
    echo "rendered $key ({$s['design']['theme']} / {$s['design']['hero']})\n";
}
