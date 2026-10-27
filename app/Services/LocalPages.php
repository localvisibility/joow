<?php

namespace App\Services;

/**
 * Pages locales « site internet pour <métier> à <ville> » : 15 métiers × 30 villes.
 * Le contenu de chaque page est assemblé à partir de blocs propres au métier, de données propres
 * à la ville (quartiers, communes, traits) et de variantes de formulation choisies de façon
 * déterministe (métier + ville), pour que deux pages ne se ressemblent jamais mot pour mot.
 */
class LocalPages
{
    /** Métiers couverts : clé de config/sectors → libellés et textes locaux. */
    public const SECTORS = ['plombier', 'electricien', 'coiffeur', 'restaurant', 'beaute', 'automobile', 'serrurier', 'paysagiste', 'renovation', 'peintre', 'kine', 'dentiste', 'immobilier', 'boulangerie', 'avocat'];

    public function sectors(): array
    {
        return collect(self::SECTORS)->mapWithKeys(fn ($k) => [$k => config("sectors.$k") + ['key' => $k, 'noun' => self::NOUNS[$k]]])->all();
    }

    public function cities(): array
    {
        return collect(config('cities'))->map(fn ($c, $slug) => self::decorate($c, $slug))->all();
    }

    public function city(string $slug): ?array
    {
        $c = config("cities.$slug");

        return $c ? self::decorate($c, $slug) : null;
    }

    /** « le Rhône », « les Bouches-du-Rhône », « l'Hérault », « Paris ». */
    private static function withArticle(array $city): string
    {
        $art = $city['dept_art'] ?? 'le';

        return $art === '' ? $city['dept_name'] : ($art === "l'" ? "l'".$city['dept_name'] : $art.' '.$city['dept_name']);
    }

    /** « du Rhône », « de la Gironde », « de l'Hérault », « des Bouches-du-Rhône », « de Paris ». */
    private static function ofDept(array $city): string
    {
        return match ($city['dept_art'] ?? 'le') {
            'le' => 'du '.$city['dept_name'],
            'les' => 'des '.$city['dept_name'],
            "l'" => "de l'".$city['dept_name'],
            'la' => 'de la '.$city['dept_name'],
            default => 'de '.$city['dept_name'],
        };
    }

    /** Ajoute le slug et la locution « à Lyon » / « au Havre » (article contracté). */
    private static function decorate(array $c, string $slug): array
    {
        $loc = $c['prep'] === 'au' ? 'au '.preg_replace('/^Le\s+/u', '', $c['name']) : $c['prep'].' '.$c['name'];

        return $c + ['slug' => $slug, 'loc' => $loc];
    }

    /** Contenu complet d'une page locale, ou null si le couple n'existe pas. */
    public function page(string $sector, string $citySlug): ?array
    {
        $city = $this->city($citySlug);
        $cfg = config("sectors.$sector");
        if (! $city || ! $cfg || ! in_array($sector, self::SECTORS, true)) {
            return null;
        }
        $seed = crc32($sector.'|'.$citySlug);
        $pick = fn (array $variants, int $salt = 0) => $variants[($seed + $salt) % count($variants)];
        $noun = self::NOUNS[$sector];
        $v = [
            '{ville}' => $city['name'], '{prep}' => $city['prep'], '{Ville}' => $city['loc'], '{gentile}' => $city['gentile'],
            '{dept}' => self::withArticle($city), '{du_dept}' => self::ofDept($city), '{region}' => $city['region'], '{pop}' => $city['pop'],
            '{q1}' => $city['quartiers'][$seed % count($city['quartiers'])], '{q2}' => $city['quartiers'][($seed + 2) % count($city['quartiers'])], '{q3}' => $city['quartiers'][($seed + 4) % count($city['quartiers'])],
            '{v1}' => $city['voisines'][$seed % count($city['voisines'])], '{v2}' => $city['voisines'][($seed + 3) % count($city['voisines'])],
            '{metier}' => $noun['sing'], '{Metier}' => mb_convert_case(mb_substr($noun['sing'], 0, 1), MB_CASE_UPPER).mb_substr($noun['sing'], 1), '{metiers}' => $noun['plur'], '{label}' => lcfirst($cfg['label']), '{cta}' => strtolower(trim((string) last(explode('·', $cfg['cta'])))),
        ];
        // Contractions après substitution des quartiers (« de le Vieux-Lyon » → « du Vieux-Lyon »)
        $t = fn (string $s) => preg_replace(['/\bde le /u', '/\bDe le /u', '/\bde les /u', '/\bDe les /u', '/\bà le /u', '/\bÀ le /u', '/\bà les /u', '/\bÀ les /u'], ['du ', 'Du ', 'des ', 'Des ', 'au ', 'Au ', 'aux ', 'Aux '], strtr($s, $v));
        $S = self::TEXTS[$sector];

        // Arguments liés aux traits de la ville (au plus trois), puis argument générique du métier
        $args = [];
        foreach ($city['traits'] as $trait) {
            if (isset($S['traits'][$trait]) && count($args) < 3) {
                $args[] = ['title' => $t($S['traits'][$trait][0]), 'text' => $t($S['traits'][$trait][1])];
            }
        }
        foreach ($S['always'] as $a) {
            if (count($args) < 4) {
                $args[] = ['title' => $t($a[0]), 'text' => $t($a[1])];
            }
        }

        // Requêtes : sans article devant le quartier (« plombier Vieux-Lyon », pas « plombier le Vieux-Lyon »)
        $searches = array_map(fn ($q) => preg_replace(["/ (le|la|les) /u", "/ l'/u"], [' ', ' '], $t($q)), array_merge(['{metier} {ville}', '{metier} {q1}', '{metier} {v1}', '{metier} {v2}'], $S['searches']));

        return [
            'sector' => $sector, 'cfg' => $cfg, 'city' => $city, 'noun' => $noun,
            'title' => $t($pick(['Site internet pour {metier} {Ville} : créé par IA en 90 secondes', 'Créer le site de votre activité de {metier} {Ville}', 'Site internet {metier} {ville} : prêt en 90 secondes, à partir de votre fiche Google'])).' — Joow',
            'h1' => $t($pick(['Site internet pour {metier} {Ville}', 'Votre site de {metier} {Ville}, prêt en 90 secondes', 'Le site internet des {metiers} {Ville}'])),
            'description' => $t($pick(['Créez le site de votre activité de {metier} {Ville} à partir de votre fiche Google : textes, photos, avis et {cta} en ligne. Gratuit jusqu\'à la mise en ligne.', '{Metier} {Ville} ? Générez votre site professionnel en 90 secondes, avec vos avis Google et votre zone ({q1}, {v1}, {v2}). Aperçu gratuit.', 'Un site de {metier} pensé pour {ville} et ses environs : prestations, avis, {cta}, référencement local. Essai gratuit, sans carte bancaire.'])),
            'intro' => $t($pick($S['intro'])),
            'args' => $args,
            'zone' => $t($pick(['{Ville} et dans l\'agglomération ({pop} habitants), de {q1} à {q2} en passant par {q3}, ainsi que dans les communes voisines : {v1}, {v2} et les autres villes {du_dept}.', 'Votre zone couvre {ville} ({pop} habitants) : {q1}, {q2}, {q3}, et s\'étend aux communes proches comme {v1} ou {v2}, dans {dept}.', 'De {q1} à {q3}, et jusqu\'à {v1} et {v2} : votre site nomme les quartiers de {ville} et les communes {du_dept} où vous intervenez réellement.'])),
            'searches' => $searches,
            'faq' => array_map(fn ($qa) => ['q' => $t($qa[0]), 'a' => $t($qa[1])], array_merge($S['faq'], self::COMMON_FAQ)),
            'closing' => $t($pick(['Les {gentile} vous cherchent sur Google. Votre site peut exister avant ce soir.', 'Tapez votre nom, regardez le résultat : votre site de {metier} {Ville} en 90 secondes.', 'Gratuit jusqu\'à la mise en ligne, sans carte bancaire. Commencez par votre fiche Google.'])),
        ];
    }

    /** Autres métiers disponibles pour la même ville. */
    public function otherSectors(string $sector): array
    {
        return collect($this->sectors())->except($sector)->values()->all();
    }

    /** Villes proches : même région d'abord, puis les suivantes de la liste. */
    public function nearbyCities(string $citySlug, int $limit = 8): array
    {
        $city = $this->city($citySlug);
        $all = collect($this->cities())->except($citySlug);
        $same = $all->where('region', $city['region'] ?? '');

        return $same->merge($all->diffKeys($same))->take($limit)->values()->all();
    }

    public const NOUNS = [
        'plombier' => ['sing' => 'plombier', 'plur' => 'plombiers'],
        'electricien' => ['sing' => 'électricien', 'plur' => 'électriciens'],
        'coiffeur' => ['sing' => 'coiffeur', 'plur' => 'coiffeurs'],
        'restaurant' => ['sing' => 'restaurant', 'plur' => 'restaurants'],
        'beaute' => ['sing' => 'institut de beauté', 'plur' => 'instituts de beauté'],
        'automobile' => ['sing' => 'garage automobile', 'plur' => 'garages'],
        'serrurier' => ['sing' => 'serrurier', 'plur' => 'serruriers'],
        'paysagiste' => ['sing' => 'paysagiste', 'plur' => 'paysagistes'],
        'renovation' => ['sing' => 'entreprise de rénovation', 'plur' => 'entreprises de rénovation'],
        'peintre' => ['sing' => 'peintre en bâtiment', 'plur' => 'peintres en bâtiment'],
        'kine' => ['sing' => 'kinésithérapeute', 'plur' => 'kinésithérapeutes'],
        'dentiste' => ['sing' => 'dentiste', 'plur' => 'dentistes'],
        'immobilier' => ['sing' => 'agence immobilière', 'plur' => 'agences immobilières'],
        'boulangerie' => ['sing' => 'boulangerie', 'plur' => 'boulangeries'],
        'avocat' => ['sing' => 'avocat', 'plur' => 'avocats'],
    ];

    public const COMMON_FAQ = [
        ['Combien coûte le site ?', 'La génération et l\'aperçu sont gratuits, sans carte bancaire. Pour le mettre en ligne : 39 € HT par mois sans engagement, ou 349 € HT une fois pour toujours. Tous les modules sont inclus dans les deux formules.'],
        ['Faut-il une fiche Google pour commencer ?', 'C\'est le plus rapide : nous en tirons vos informations, photos et avis. Sinon, collez l\'adresse de votre site actuel pour une refonte, ou répondez à cinq questions.'],
        ['Puis-je utiliser mon propre nom de domaine ?', 'Oui. Vous connectez un domaine que vous possédez ou en commandez un à votre nom depuis votre espace. Le certificat HTTPS et la redirection sont automatiques.'],
    ];

    /**
     * Textes par métier : intro (variantes), arguments par trait de ville, arguments toujours
     * présents, requêtes locales et questions fréquentes. Placeholders : voir page().
     */
    public const TEXTS = [
        'plombier' => [
            'intro' => [
                'Une fuite à {q1}, un chauffe-eau en panne à {v1}, une salle de bains à refaire {Ville} : le client appelle le premier plombier qui inspire confiance sur Google. Votre site doit être celui-là.',
                '{Ville}, un plombier se choisit en trente secondes sur un téléphone : zone d\'intervention, délai, avis, devis. Votre site répond à ces quatre questions avant même le premier appel.',
                'Les {gentile} cherchent « plombier {ville} » plusieurs centaines de fois par mois. Un site clair, avec vos avis et votre zone réelle, transforme ces recherches en interventions.',
            ],
            'traits' => [
                'littoral' => ['Air marin et canalisations', 'Corrosion, calcaire et humidité font vieillir plus vite les installations près de la mer. Un site qui parle de remplacement de canalisations, de traitement de l\'eau et d\'entretien répond à des recherches fréquentes {Ville}.'],
                'climat_froid' => ['Le chauffage, sujet numéro un', 'Chaudières, pompes à chaleur, entretien annuel, dépannage en plein hiver : {Ville}, la saison de chauffe concentre les demandes. Votre site met ces prestations en avant au bon moment.'],
                'patrimoine' => ['Des immeubles anciens', 'Colonnes montantes vétustes, plomb, copropriétés : le bâti ancien de {ville} ({q1}, {q2}) génère des interventions spécifiques. Les nommer sur votre site attire les syndics et les propriétaires concernés.'],
                'metropole' => ['Une concurrence dense', 'Des dizaines de plombiers couvrent {ville}. Ce qui vous distingue : une zone précise par quartier, un délai annoncé, des avis récents et un devis clair. Le site les affiche là où la fiche Google ne le peut pas.'],
                'etudiante' => ['Locations et petits dépannages', 'Une ville étudiante multiplie les locations et les petites interventions (robinetterie, chasse d\'eau, ballon d\'eau chaude). Un formulaire de demande rapide, utilisable depuis un téléphone, capte ces demandes.'],
                'climat_chaud' => ['Climatisation et eau chaude solaire', 'Sous le climat de {ville}, la climatisation et les chauffe-eau solaires font partie des demandes régulières. Les afficher clairement sur votre site vous positionne sur ces recherches saisonnières.'],
            ],
            'always' => [
                ['L\'urgence, en premier', 'Téléphone cliquable fixé en bas d\'écran, délai d\'intervention et mention « devis annoncé avant intervention » : le site est conçu pour l\'appel en urgence, qui représente la majorité des contacts.'],
                ['Le devis avec photo', 'Pour les projets (salle de bains, chaudière), un formulaire court avec envoi de photos permet une première estimation sérieuse et une réponse rapide.'],
            ],
            'searches' => ['fuite d\'eau {ville}', 'dépannage plomberie {ville}', 'chauffe-eau {ville}', 'plombier urgence {ville}'],
            'faq' => [
                ['Le site met-il en avant mes urgences ?', 'Oui : appel direct, délai d\'intervention et zone sont affichés avant tout, et le formulaire de devis avec photos prend le relais pour les projets.'],
                ['Puis-je indiquer mes communes précises ?', 'Oui. Une section zone d\'intervention liste vos communes autour de {ville}, modifiable dans le Studio, pour être trouvé sur « plombier {v1} » comme sur « plombier {q1} ».'],
            ],
        ],
        'electricien' => [
            'intro' => [
                'Mise aux normes d\'un appartement à {q1}, tableau à remplacer à {v1}, borne de recharge à installer {Ville} : les {gentile} cherchent un électricien sur Google, et choisissent sur ce qu\'ils voient.',
                '{Ville}, un électricien se choisit sur ses réalisations, ses certifications et ses avis. Votre site les montre, là où une fiche Google reste limitée.',
                'Entre dépannage et rénovation, les recherches « électricien {ville} » sont constantes. Un site clair, mobile et à jour vous place dans la sélection finale.',
            ],
            'traits' => [
                'patrimoine' => ['Le bâti ancien de {ville}', 'Installations hors normes, tableaux vétustes, mises en conformité avant vente ou location : les immeubles anciens de {q1} et {q2} génèrent une demande régulière que votre site peut nommer.'],
                'metropole' => ['Se distinguer dans une grande ville', 'Face à de nombreux concurrents, votre site précise vos spécialités (domotique, bornes IRVE, rénovation complète), votre zone et vos avis récents.'],
                'etudiante' => ['Petits travaux locatifs', 'Les logements étudiants et les locations appellent des interventions courtes : prises, éclairage, diagnostics. Un formulaire rapide depuis un téléphone capte ces demandes.'],
                'industrielle' => ['Professionnels et locaux d\'activité', 'Ateliers, commerces et bureaux de {ville} ont des besoins tertiaires et industriels. Une section dédiée aux professionnels attire ces contrats récurrents.'],
                'climat_chaud' => ['Climatisation et solaire', 'Sous le climat de {ville}, climatisation et panneaux photovoltaïques font partie des demandes fréquentes. Les afficher vous positionne sur ces recherches.'],
                'climat_froid' => ['Chauffage électrique et pompes à chaleur', 'Radiateurs performants, pompes à chaleur, programmation : {Ville}, les demandes liées au chauffage se concentrent à l\'automne. Votre site les met en avant.'],
            ],
            'always' => [
                ['Réalisations et certifications', 'Tableau avant/après, cuisine câblée, terrasse éclairée, logos Qualifelec, RGE et IRVE : les preuves qui font choisir, en page d\'accueil.'],
                ['Devis court avec photos', 'Type de travaux, surface, ville, téléphone, photos : le client envoie en une minute, vous répondez sous 48 h, souvent en premier.'],
            ],
            'searches' => ['dépannage électrique {ville}', 'mise aux normes électrique {ville}', 'borne de recharge {ville}', 'électricien urgence {ville}'],
            'faq' => [
                ['Puis-je mettre en avant mes certifications ?', 'Oui : RGE, Qualifelec, IRVE et votre assurance décennale s\'affichent en page d\'accueil et dans les mentions légales générées.'],
                ['Le site convient-il aux chantiers professionnels ?', 'Oui. Vous pouvez ajouter une page dédiée aux commerces et bureaux de {ville} depuis le Studio, avec un formulaire adapté.'],
            ],
        ],
        'coiffeur' => [
            'intro' => [
                'Une cliente de {q1} cherche un salon sur son téléphone : photos, avis, et surtout un rendez-vous maintenant. {Ville}, votre site doit répondre en trois secondes.',
                'Les salons de {ville} se comptent par dizaines. Celui qu\'on choisit est celui dont on voit les réalisations, les prix, et où l\'on réserve sans appeler.',
                '« Coiffeur {ville} », « balayage {q1} », « barbier {v1} » : les recherches locales remplissent les agendas des salons qui ont un site clair et une réservation en ligne.',
            ],
            'traits' => [
                'etudiante' => ['Une clientèle jeune et connectée', 'Les étudiants de {ville} réservent depuis leur téléphone, le soir, et comparent les prix. Une carte des prestations lisible et un rendez-vous en ligne 24 h/24 font la différence.'],
                'touristique' => ['Visiteurs de passage', 'Touristes et visiteurs de {ville} cherchent un salon près de leur hébergement : une page claire avec adresse, horaires et réservation immédiate capte cette clientèle sans effort.'],
                'metropole' => ['Se distinguer par quartier', 'Dans une grande ville, on cherche un coiffeur près de chez soi : « coiffeur {q1} », « coiffeur {q2} ». Votre site nomme votre quartier et vos accès.'],
                'patrimoine' => ['Un salon dans le centre', 'Rues piétonnes, stationnement difficile : précisez accès, parkings et transports. Les clientes de {ville} apprécient de savoir comment venir avant de réserver.'],
                'climat_chaud' => ['Saison et terrasses', 'Été, mariages, événements : {Ville}, la saison rythme les demandes de coiffure événementielle. Une page dédiée et une réservation anticipée répondent à ce calendrier.'],
            ],
            'always' => [
                ['Rendez-vous en ligne, sans commission', 'Prestations, durées, équipe, créneaux réels, rappel la veille, empreinte bancaire si vous le souhaitez. Votre fichier clientes reste à vous.'],
                ['Vos réalisations, pas des photos de banque', 'L\'IA trie les photos de votre fiche Google ; vous ajoutez vos coupes et couleurs depuis le Studio. Les clientes veulent voir votre travail.'],
            ],
            'searches' => ['coiffeur femme {ville}', 'coiffeur homme {ville}', 'balayage {ville}', 'coiffeur sans rendez-vous {ville}'],
            'faq' => [
                ['La prise de rendez-vous est-elle incluse ?', 'Oui, sans commission : prestations et durées, planning par coiffeur, confirmations et rappels, annulation en ligne.'],
                ['Puis-je afficher mes prix ?', 'Oui, par prestation, avec « à partir de ». Les salons qui affichent leurs prix reçoivent moins d\'appels et plus de réservations.'],
            ],
        ],
        'restaurant' => [
            'intro' => [
                'Où dîner ce soir {Ville} ? La décision se prend sur un téléphone, en trente secondes, entre la carte, les photos, les avis et la possibilité de réserver. Votre site doit gagner ces trente secondes.',
                'Les {gentile} et les visiteurs cherchent « restaurant {q1} », « où manger {ville} », « brunch {q2} ». Un site avec votre carte lisible, vos photos et une réservation directe capte ces recherches sans commission.',
                '{Ville}, la concurrence se joue sur Google Maps. Votre site complète la fiche : carte à jour, ambiance, avis, réservation en un geste.',
            ],
            'traits' => [
                'touristique' => ['Une clientèle de passage', 'Les visiteurs de {ville} ne vous connaissent pas : photos de la salle et des plats, carte avec prix, avis récents et réservation immédiate font la décision. Une version anglaise de la carte aide.'],
                'etudiante' => ['Étudiants et jeunes actifs', 'Formules du midi, happy hours, groupes : la clientèle jeune de {ville} réserve tard et sur mobile. Un site rapide avec réservation 24 h/24 capte ces tables.'],
                'littoral' => ['Terrasse et saison', 'Terrasse, vue, saison haute : {Ville}, la saisonnalité est forte. Horaires d\'été et d\'hiver, réservation anticipée et photos de la terrasse structurent votre site.'],
                'gastronomie' => ['Une ville qui aime la table', 'À {ville}, la clientèle est exigeante et informée. Votre site met en avant vos produits, vos fournisseurs et votre chef, pas seulement vos horaires.'],
                'metropole' => ['Se distinguer par quartier', 'On cherche un restaurant près de chez soi ou du bureau : « restaurant {q1} », « déjeuner {q2} ». Votre site nomme votre quartier, vos accès et vos formules.'],
                'climat_chaud' => ['La terrasse, argument numéro un', 'Sous le climat de {ville}, la terrasse décide de la réservation une grande partie de l\'année. Photos, nombre de couverts dehors et ombrage méritent une place en page d\'accueil.'],
            ],
            'always' => [
                ['La carte en page, pas en PDF', 'Catégories, plats, prix, pictogrammes, modifiable en deux minutes depuis votre téléphone. Lisible sur mobile et indexée par Google.'],
                ['Réservation directe, sans commission', 'Services, créneaux, capacité, confirmation, rappel, empreinte bancaire pour les grandes tables si vous le souhaitez. Les coordonnées de vos clients restent chez vous.'],
            ],
            'searches' => ['où manger {ville}', 'restaurant {q2}', 'brunch {ville}', 'restaurant terrasse {ville}'],
            'faq' => [
                ['La réservation remplace-t-elle les plateformes ?', 'Pour vos habitués et ceux qui vous cherchent par votre nom, oui, sans commission. Vous pouvez garder une plateforme en parallèle pour les nouveaux clients.'],
                ['Puis-je modifier la carte depuis mon téléphone ?', 'Oui, en quelques secondes depuis le Studio : plat du jour, prix, saison. Les allergènes se renseignent au même endroit.'],
            ],
        ],
        'beaute' => [
            'intro' => [
                'Soin du visage à {q1}, épilation à {v1}, manucure avant une soirée {Ville} : la cliente réserve là où elle peut le faire tout de suite, en voyant les prix et les avis.',
                'Les instituts de {ville} rivalisent sur Google Maps. Le vôtre gagne avec une carte des soins claire, des photos de l\'institut et un rendez-vous en ligne sans plateforme.',
                '« Institut de beauté {ville} », « épilation {q2} », « massage {v2} » : des recherches régulières, captées par les instituts dont le site permet de réserver en trois écrans.',
            ],
            'traits' => [
                'etudiante' => ['Une clientèle jeune', 'Les étudiantes de {ville} réservent le soir, depuis leur téléphone, et comparent les prix. Carte des soins tarifée et rendez-vous 24 h/24 font la différence.'],
                'touristique' => ['Visiteuses et événements', 'Mariages, séjours, événements : {Ville}, une partie de la clientèle vient de passage et cherche une disponibilité immédiate. Votre site la donne.'],
                'metropole' => ['Le quartier d\'abord', 'Dans une grande ville, on choisit un institut près de chez soi : « institut {q1} », « onglerie {q2} ». Votre site nomme votre quartier et vos accès.'],
                'climat_chaud' => ['Saison et soins du corps', 'Sous le climat de {ville}, épilation, soins du corps et préparation estivale concentrent les demandes au printemps. Votre site les met en avant au bon moment.'],
                'thermale' => ['Bien-être et tradition', 'La tradition thermale de {ville} entretient une demande de soins du corps et de relaxation. Votre site peut s\'adresser à cette clientèle en nommant ces prestations.'],
            ],
            'always' => [
                ['Rendez-vous en ligne avec rappels', 'Soins, durées, cabines, praticiennes, créneaux réels, confirmation, rappel la veille et empreinte bancaire facultative pour les soins longs.'],
                ['Ambiance et confiance', 'Photos de l\'institut, présentation des praticiennes et de leurs formations, avis Google en haut de page : la confiance se construit avant la première visite.'],
            ],
            'searches' => ['épilation {ville}', 'soin du visage {ville}', 'onglerie {ville}', 'massage {ville}'],
            'faq' => [
                ['Puis-je demander une empreinte bancaire ?', 'Oui, via votre compte Stripe, pour les soins longs ou les nouvelles clientes. Les absences baissent nettement.'],
                ['Les marques que j\'utilise peuvent-elles apparaître ?', 'Oui, dans la carte des soins et la présentation. Certaines clientes cherchent précisément une marque.'],
            ],
        ],
        'automobile' => [
            'intro' => [
                'Révision, pneus, voyant allumé : l\'automobiliste de {ville} cherche un garage de confiance près de chez lui, à {q1} ou à {v1}, et compare avant d\'appeler.',
                'Face aux centres auto et aux réseaux, un garage indépendant {Ville} se fait choisir par sa transparence : prestations tarifées, avis détaillés, rendez-vous en ligne.',
                '« Garage {ville} », « vidange {q2} », « contrôle technique {v2} » : des recherches quotidiennes, captées par les garages dont le site rassure et permet de réserver.',
            ],
            'traits' => [
                'metropole' => ['Proximité et stationnement', 'Dans une grande ville, on choisit un garage accessible : quartier, transports pour repartir, véhicule de courtoisie. Votre site répond à ces questions pratiques.'],
                'industrielle' => ['Flottes et utilitaires', 'Artisans et entreprises de {ville} ont des utilitaires à entretenir vite. Une section professionnels avec prise en charge rapide attire ces contrats récurrents.'],
                'montagne' => ['Pneus et saison', 'Pneus hiver, chaînes, préparation avant la neige : {Ville}, la saison déclenche des pics de demandes. Votre site les anticipe avec une section dédiée.'],
                'littoral' => ['Air salin et carrosserie', 'Corrosion, carrosserie, climatisation : près de la mer, certaines interventions reviennent souvent. Les nommer vous positionne sur ces recherches.'],
                'etudiante' => ['Petits budgets, grandes attentes', 'Les étudiants de {ville} roulent dans des véhicules d\'occasion et cherchent des forfaits clairs. Des prix « à partir de » et un diagnostic honnête fidélisent.'],
                'climat_chaud' => ['Climatisation en tête', 'Sous le climat de {ville}, la recharge et la réparation de climatisation sont des demandes récurrentes dès le printemps. Votre site les affiche au bon moment.'],
            ],
            'always' => [
                ['Rendez-vous atelier et devis avec photo', 'Type d\'intervention, véhicule, créneaux, confirmation ; pour la carrosserie et les pannes, photos du problème pour une première estimation.'],
                ['Prestations tarifées, avis détaillés', 'Forfaits « à partir de », marques et spécialités, services annexes (courtoisie, dépannage), avis Google avec vos réponses : ce qui fait confiance.'],
            ],
            'searches' => ['vidange {ville}', 'garage pas cher {ville}', 'pneus {ville}', 'carrosserie {ville}'],
            'faq' => [
                ['Puis-je afficher des forfaits ?', 'Oui : révision, vidange, pneus, freins avec des prix « à partir de », modifiables dans le Studio.'],
                ['Le devis avec photo fonctionne-t-il pour la carrosserie ?', 'Oui. Le client envoie des photos de la rayure ou du choc, vous recevez la demande immédiatement avec ses coordonnées.'],
            ],
        ],
        'serrurier' => [
            'intro' => [
                'Porte claquée à {q1} un dimanche soir, serrure forcée à {v1} : le client appelle le premier serrurier qui paraît sérieux. {Ville}, votre site doit rassurer en trois secondes et donner le prix.',
                'La serrurerie souffre d\'une mauvaise réputation à cause des arnaques. {Ville}, un site transparent, avec tarifs de déplacement et avis, vous distingue immédiatement.',
                '« Serrurier {ville} », « ouverture de porte {q2} », « serrurier urgence {v2} » : des recherches à toute heure, captées par un site mobile qui affiche prix et délai.',
            ],
            'traits' => [
                'metropole' => ['Rassurer dans une grande ville', 'Les {gentile} se méfient des serruriers trouvés en ligne. Entreprise identifiée, adresse réelle, tarifs affichés, avis récents : votre site inverse la méfiance.'],
                'patrimoine' => ['Portes anciennes et copropriétés', 'Immeubles anciens, portes cochères, serrures de sécurité à remplacer : le bâti de {q1} et {q2} appelle des compétences spécifiques à nommer.'],
                'etudiante' => ['Locations et clés perdues', 'Une ville étudiante multiplie les ouvertures de porte et les changements de serrure entre locataires. Un formulaire rapide et un prix clair captent ces demandes.'],
                'touristique' => ['Locations saisonnières', 'Boîtes à clés, serrures connectées, interventions pour locations de courte durée : {Ville}, les propriétaires cherchent un serrurier réactif. Votre site s\'adresse à eux.'],
                'littoral' => ['Humidité et sécurité', 'Corrosion des serrures et des portails près de la mer, sécurisation des résidences secondaires : des demandes régulières {Ville}.'],
            ],
            'always' => [
                ['Prix et délai avant l\'appel', 'Tarif de déplacement, fourchette pour une ouverture simple, délai moyen : affichés en haut de page. C\'est ce qui fait appeler vous plutôt qu\'un autre.'],
                ['Entreprise identifiée', 'Nom, SIREN, adresse, photo du véhicule et de l\'équipe, avis Google : les preuves de sérieux que vos concurrents douteux ne peuvent pas montrer.'],
            ],
            'searches' => ['ouverture de porte {ville}', 'serrurier urgence {ville}', 'changement de serrure {ville}', 'serrurier pas cher {ville}'],
            'faq' => [
                ['Puis-je afficher mes tarifs d\'urgence ?', 'Oui, et c\'est recommandé : déplacement, ouverture simple, majoration nuit et week-end. La transparence est votre meilleur argument.'],
                ['Le site fonctionne-t-il bien la nuit sur mobile ?', 'Il est conçu pour cela : appel en un geste, chargement rapide, prix visibles sans faire défiler.'],
            ],
        ],
        'paysagiste' => [
            'intro' => [
                'Un jardin à créer à {v1}, une terrasse à {q1}, un entretien régulier {Ville} : le client choisit un paysagiste sur ses réalisations. Votre site doit les montrer.',
                'Le paysagiste a un avantage : son travail est beau en photo. {Ville}, un site qui ouvre sur vos jardins avant/après décroche les projets de création, les plus rentables.',
                '« Paysagiste {ville} », « entretien jardin {v2} », « terrasse bois {q2} » : des recherches saisonnières, captées par les entreprises dont le site est à jour.',
            ],
            'traits' => [
                'climat_chaud' => ['Jardins secs et arrosage', 'Sous le climat de {ville}, jardins méditerranéens, arrosage automatique et plantations résistantes à la sécheresse sont des demandes fortes. Votre site les nomme.'],
                'climat_froid' => ['Saison courte, demandes concentrées', '{Ville}, la saison de création est courte et les demandes se concentrent au printemps. Un site qui annonce vos délais et permet de réserver tôt structure votre planning.'],
                'montagne' => ['Terrains en pente et hivernage', 'Murets, soutènements, plantations de montagne, préparation à l\'hiver : les terrains de {ville} appellent un savoir-faire particulier à mettre en avant.'],
                'littoral' => ['Vent, sel et végétaux adaptés', 'Près de la mer, le choix des végétaux et la protection contre le vent font la réussite d\'un jardin. Votre site montre vos réalisations dans ces conditions.'],
                'metropole' => ['Petits espaces et copropriétés', 'Balcons, cours, jardins de ville, espaces verts de copropriété : {Ville}, les projets sont souvent compacts et exigeants. Une page dédiée attire syndics et particuliers.'],
                'patrimoine' => ['Jardins de maisons anciennes', 'Murs de pierre, puits, vieux arbres : les jardins des maisons anciennes de {ville} demandent du respect et du savoir-faire. Vos réalisations le prouvent.'],
            ],
            'always' => [
                ['Réalisations avant/après', 'Dix réalisations en page d\'accueil avec surface, commune et durée ; le reste en galerie. L\'IA trie vos photos, vous ajoutez vos chantiers.'],
                ['Création et entretien, deux offres', 'Devis avec photos et surface pour les projets ; forfaits indicatifs et crédit d\'impôt de 50 % pour l\'entretien si vous êtes agréé.'],
            ],
            'searches' => ['entretien jardin {ville}', 'création jardin {ville}', 'élagage {ville}', 'terrasse bois {ville}'],
            'faq' => [
                ['Puis-je montrer des avant/après ?', 'Oui, c\'est l\'argument principal du modèle Paysagiste. Vous les ajoutez depuis le Studio, avec surface, commune et durée.'],
                ['Le crédit d\'impôt est-il mentionné ?', 'Si vous êtes agréé services à la personne, oui : le site rappelle le crédit d\'impôt de 50 % sur l\'entretien, un argument décisif.'],
            ],
        ],
        'renovation' => [
            'intro' => [
                'Rénover un appartement à {q1}, isoler une maison à {v1}, changer un chauffage {Ville} : le particulier compare trois entreprises sur Google. Votre site décide si vous en faites partie.',
                '{Ville}, la rénovation énergétique et les travaux d\'intérieur sont des projets à plusieurs milliers d\'euros. Le client veut des preuves : réalisations, certifications RGE, avis, devis clair.',
                '« Rénovation {ville} », « isolation {v2} », « rénovation appartement {q2} » : des recherches à forte intention, captées par les entreprises dont le site inspire confiance.',
            ],
            'traits' => [
                'patrimoine' => ['Le bâti ancien de {ville}', 'Appartements haussmanniens, maisons de ville, pierre, parquets : rénover l\'ancien à {q1} ou {q2} exige des compétences spécifiques. Les montrer attire les projets de qualité.'],
                'climat_froid' => ['Isolation et chauffage', '{Ville}, l\'isolation et le remplacement de chauffage sont les premières demandes, portées par les aides. Vos certifications RGE en page d\'accueil sont indispensables.'],
                'climat_chaud' => ['Confort d\'été', 'Isolation contre la chaleur, protections solaires, climatisation : sous le climat de {ville}, le confort d\'été entre dans tous les projets de rénovation.'],
                'metropole' => ['Appartements et copropriétés', 'Dans une grande ville, les projets concernent surtout des appartements et des parties communes. Une page dédiée aux copropriétés et aux syndics ouvre des chantiers récurrents.'],
                'etudiante' => ['Investisseurs et locations', 'Les investisseurs rénovent des studios et des petites surfaces pour la location étudiante à {ville}. Des offres clés en main avec délais les attirent.'],
                'littoral' => ['Humidité et résidences secondaires', 'Près de la mer, humidité, menuiseries et façades vieillissent vite, et les propriétaires de résidences secondaires cherchent une entreprise de confiance à distance.'],
            ],
            'always' => [
                ['Réalisations et RGE', 'Avant/après par type de projet, logos RGE et Qualibat, assurance décennale : les preuves qui déclenchent la demande de devis.'],
                ['Devis structuré', 'Type de travaux, surface, état, ville, photos : un formulaire qui qualifie le projet et vous permet de répondre vite et juste.'],
            ],
            'searches' => ['rénovation appartement {ville}', 'isolation {ville}', 'rénovation énergétique {ville}', 'entreprise de rénovation {v1}'],
            'faq' => [
                ['Les aides (MaPrimeRénov\', CEE) peuvent-elles être expliquées ?', 'Oui, une section dédiée est prévue : vous y décrivez les aides que vous accompagnez. Les textes sont modifiables à mesure que les dispositifs évoluent.'],
                ['Puis-je présenter plusieurs corps de métier ?', 'Oui. Le modèle Rénovation accepte plusieurs pages de prestations (plomberie, électricité, isolation, peinture) avec un devis commun.'],
            ],
        ],
        'peintre' => [
            'intro' => [
                'Repeindre un appartement à {q1}, ravaler une façade à {v1}, poser un papier peint {Ville} : le client veut voir des chantiers finis et un prix. Votre site les montre.',
                '{Ville}, un peintre se choisit sur la propreté de son travail et la clarté de son devis. Vos réalisations, vos avis et un formulaire simple font la différence.',
                '« Peintre en bâtiment {ville} », « ravalement {v2} », « peinture appartement {q2} » : des recherches régulières, captées par les entreprises dont le site est net et à jour.',
            ],
            'traits' => [
                'patrimoine' => ['Façades et intérieurs anciens', 'Moulures, enduits à la chaux, ravalements encadrés : le bâti ancien de {ville} demande des techniques particulières que vos réalisations peuvent illustrer.'],
                'metropole' => ['Appartements, vite et proprement', 'Dans une grande ville, la demande porte sur des appartements occupés : rapidité, protection, propreté. Votre site rassure sur l\'organisation du chantier.'],
                'littoral' => ['Façades face à la mer', 'Sel, vent, humidité : les façades de {ville} se dégradent vite. Les peintures et traitements adaptés sont un argument à mettre en avant.'],
                'etudiante' => ['Remises en état entre locataires', 'Les propriétaires de logements étudiants cherchent des remises en peinture rapides et au forfait. Une offre claire les attire.'],
                'climat_chaud' => ['Protection solaire des façades', 'Sous le soleil de {ville}, les peintures extérieures doivent résister aux UV. Les produits adaptés et vos garanties méritent une place sur votre site.'],
                'climat_froid' => ['Intérieurs en hiver, extérieurs en saison', '{Ville}, les ravalements se font à la belle saison et les intérieurs en hiver. Votre site annonce vos disponibilités selon la période.'],
            ],
            'always' => [
                ['Chantiers avant/après', 'Pièce par pièce, façade par façade, avec le type de finition. Les photos propres et bien éclairées sont votre meilleur commercial.'],
                ['Devis simple avec photos', 'Nombre de pièces ou surface, état des supports, ville, photos : le client envoie en une minute, vous chiffrez vite.'],
            ],
            'searches' => ['peinture appartement {ville}', 'ravalement façade {ville}', 'peintre pas cher {ville}', 'papier peint {ville}'],
            'faq' => [
                ['Puis-je afficher des prix au m² ?', 'Oui, à titre indicatif, par type de prestation. C\'est un filtre utile qui évite les demandes hors budget.'],
                ['Le site présente-t-il aussi la décoration ?', 'Oui : conseil couleur, papiers peints, enduits décoratifs ont leur section si vous les proposez.'],
            ],
        ],
        'kine' => [
            'intro' => [
                'Un patient de {q1} cherche un kiné disponible rapidement, proche et bien noté. {Ville}, votre site répond : spécialités, accès, prise de rendez-vous, sans démarchage.',
                'Le site d\'un cabinet de kinésithérapie ou d\'ostéopathie {Ville} informe sans faire de publicité : praticiens, spécialités, accès, modalités de rendez-vous. Et il allège le secrétariat.',
                '« Kiné {ville} », « ostéopathe {q2} », « kiné du sport {v1} » : des recherches constantes, captées par les cabinets dont le site est clair et respectueux des règles.',
            ],
            'traits' => [
                'etudiante' => ['Sport et jeunes patients', 'Clubs, universités, sportifs amateurs : {Ville}, la demande en kiné du sport et en prévention est forte. Une page spécialité attire ces patients.'],
                'montagne' => ['Traumatologie de montagne', 'Ski, randonnée, trail : les blessures de montagne font une part de la patientèle de {ville}. Nommer cette expertise vous rend visible auprès des sportifs.'],
                'metropole' => ['Proximité et accès', 'Dans une grande ville, le patient choisit un cabinet accessible : quartier, transports, stationnement, étage, accessibilité PMR. Votre site le précise.'],
                'thermale' => ['Rééducation et cures', 'La tradition thermale de {ville} entretient une demande de rééducation et de soins post-cure. Votre site peut s\'adresser à cette patientèle.'],
                'littoral' => ['Patientèle senior et saisonnière', 'Résidents retraités et visiteurs d\'été : {Ville}, la patientèle varie. Horaires, domicile et disponibilités sont à préciser.'],
                'climat_froid' => ['Hiver et pathologies saisonnières', 'Chutes, douleurs articulaires, reprise sportive au printemps : votre site peut informer sur ces motifs fréquents sans promettre de résultat.'],
            ],
            'always' => [
                ['Prise de rendez-vous conforme', 'Motifs généraux, praticien, créneaux, rappel la veille. Sans détail médical dans les formulaires, conformément au RGPD.'],
                ['Informer sans démarcher', 'Ton informatif, titres et diplômes, actes pratiqués, conventionnement, accès. Avis Google affichés sans sollicitation automatique.'],
            ],
            'searches' => ['kiné {q2}', 'ostéopathe {ville}', 'kiné du sport {ville}', 'kiné à domicile {ville}'],
            'faq' => [
                ['Le site respecte-t-il les règles de l\'Ordre ?', 'Il adopte un ton informatif sans comparaison ni promesse de résultat, et n\'automatise pas la sollicitation d\'avis. La relecture finale vous appartient.'],
                ['Puis-je indiquer les soins à domicile ?', 'Oui, avec votre zone autour de {ville} et vos conditions, dans une section dédiée.'],
            ],
        ],
        'dentiste' => [
            'intro' => [
                'Trouver un dentiste disponible {Ville} n\'est pas simple. Un site clair, avec les soins pratiqués, les praticiens et la prise de rendez-vous, aide les patients et désengorge le secrétariat.',
                'Le site d\'un cabinet dentaire {Ville} informe sans publicité : équipe, soins, honoraires et conventionnement, accès, urgences. C\'est ce que cherchent les patients de {q1} comme de {v1}.',
                '« Dentiste {ville} », « urgence dentaire {q2} », « orthodontiste {v2} » : des recherches quotidiennes, souvent anxieuses. Un site rassurant et précis fait la différence.',
            ],
            'traits' => [
                'etudiante' => ['Étudiants et premiers soins', 'Les étudiants de {ville} cherchent un dentiste conventionné, accessible, et des informations claires sur les tarifs et le tiers payant. Votre site les donne.'],
                'metropole' => ['Accès et horaires étendus', 'Dans une grande ville, horaires élargis, accès en transports et prise de rendez-vous en ligne sont décisifs. Votre site les met en avant.'],
                'frontaliere' => ['Patients transfrontaliers', '{Ville}, une partie de la patientèle compare avec les pays voisins. Honoraires transparents et qualité de l\'équipement sont des arguments à présenter sobrement.'],
                'touristique' => ['Urgences de passage', 'Visiteurs et vacanciers cherchent une urgence dentaire : une section urgences avec modalités claires répond à ce besoin fréquent {Ville}.'],
                'patrimoine' => ['Cabinet en centre ancien', 'Stationnement, étage, ascenseur, accessibilité : dans le centre de {ville}, préciser l\'accès évite des appels et des retards.'],
            ],
            'always' => [
                ['Prise de rendez-vous et urgences', 'Motifs généraux, praticien, créneaux, rappel la veille ; une section urgences avec la conduite à tenir hors horaires.'],
                ['Information loyale', 'Soins pratiqués, honoraires et conventionnement, équipement, hygiène, mentions légales générées. Ton informatif, sans comparaison ni promesse.'],
            ],
            'searches' => ['urgence dentaire {ville}', 'dentiste {q2}', 'orthodontiste {ville}', 'implant dentaire {ville}'],
            'faq' => [
                ['Les honoraires peuvent-ils être affichés ?', 'Oui, conformément à l\'obligation d\'information : honoraires des actes courants, conventionnement, tiers payant.'],
                ['Le site respecte-t-il la déontologie ?', 'Il adopte un ton informatif, sans publicité comparative ni promesse de résultat, et n\'automatise pas la sollicitation d\'avis.'],
            ],
        ],
        'immobilier' => [
            'intro' => [
                'Les acheteurs sont sur les portails. Les vendeurs de {q1} ou de {v1}, eux, cherchent une agence {Ville}. Votre site s\'adresse à eux : estimation, ventes récentes, méthode, honoraires.',
                '{Ville}, une agence indépendante se distingue par sa connaissance des quartiers. Une page par secteur, vos ventes et vos avis font ce qu\'aucun portail ne peut faire.',
                '« Agence immobilière {ville} », « estimation appartement {q2} », « vendre maison {v2} » : des recherches de vendeurs, les plus précieuses, captées par les agences dont le site est local et vivant.',
            ],
            'traits' => [
                'metropole' => ['Un marché par quartier', 'De {q1} à {q3}, les prix et les attentes changent. Une page par quartier, avec vos ventes et vos chiffres, positionne votre agence sur chaque micro-marché.'],
                'etudiante' => ['Investissement locatif', 'Studios et petites surfaces pour étudiants : {Ville}, les investisseurs cherchent une agence qui connaît ce marché et gère les locations. Une page dédiée les attire.'],
                'touristique' => ['Résidences secondaires', 'Acheteurs lointains, locations saisonnières, gestion à distance : {Ville}, une partie du marché passe par des clients qui vous découvrent en ligne. Votre site doit tout expliquer.'],
                'littoral' => ['Vue mer et saisonnalité', 'Le marché de {ville} a ses codes : vue, proximité des plages, saison des transactions. Vos ventes récentes et votre expertise locale rassurent les vendeurs.'],
                'frontaliere' => ['Acheteurs frontaliers', '{Ville}, les travailleurs frontaliers forment une clientèle spécifique, avec ses contraintes de financement. Montrer que vous la connaissez attire vendeurs et acheteurs.'],
                'patrimoine' => ['Biens de caractère', 'Appartements anciens, maisons de ville, biens à rénover : le patrimoine de {ville} exige une présentation soignée et une connaissance des règles. Vos ventes le montrent.'],
            ],
            'always' => [
                ['L\'estimation en premier', 'Formulaire court (type, surface, ville, téléphone) en haut de page : c\'est l\'entrée des vendeurs, votre vrai client.'],
                ['Ventes récentes et équipe', 'Biens vendus avec délais, négociateurs identifiés par secteur, avis de vendeurs : les preuves qui font signer un mandat.'],
            ],
            'searches' => ['estimation appartement {ville}', 'vendre maison {ville}', 'agence immobilière {q2}', 'gestion locative {ville}'],
            'faq' => [
                ['Puis-je afficher mes biens à la vente ?', 'Oui, une sélection mise à jour depuis le Studio. Le site est pensé d\'abord pour les vendeurs, les acheteurs restent sur les portails.'],
                ['Puis-je créer une page par quartier ?', 'Oui, autant que nécessaire : {q1}, {q2}, {v1}… avec vos ventes et vos chiffres propres à chaque secteur.'],
            ],
        ],
        'boulangerie' => [
            'intro' => [
                'La baguette du matin ne se cherche pas sur Google. Le gâteau d\'anniversaire, la galette, le buffet pour trente personnes, si. {Ville}, votre site prend ces commandes pendant que vous êtes au fournil.',
                '« Boulangerie ouverte dimanche {ville} », « pâtisserie {q1} », « pain au levain {v1} » : des recherches réelles, captées par les boulangeries dont le site donne horaires, spécialités et commande en ligne.',
                '{Ville}, votre boulangerie a des habitués. Votre site s\'adresse aux autres : ceux qui cherchent une spécialité, une commande spéciale, ou simplement si vous êtes ouvert.',
            ],
            'traits' => [
                'touristique' => ['Visiteurs et spécialités locales', 'Les visiteurs de {ville} cherchent les spécialités du coin et une boulangerie ouverte près de leur hébergement. Horaires exacts et photos des produits les font venir.'],
                'etudiante' => ['Formules et horaires élargis', 'Sandwichs, formules du midi, horaires tardifs : la clientèle étudiante de {ville} compte sur vous. Votre site affiche ces offres et l\'état ouvert/fermé en direct.'],
                'metropole' => ['Le quartier, votre territoire', 'On cherche une boulangerie à moins de cinq minutes : « boulangerie {q1} », « pâtisserie {q2} ». Votre site nomme votre quartier et vos accès.'],
                'gastronomie' => ['Une clientèle exigeante', '{Ville}, on parle farines, levain et fournisseurs. Votre site raconte ce que vous faites et comment, en trois phrases vraies et une photo du fournil.'],
                'patrimoine' => ['Centre ancien et commande', 'Dans le centre de {ville}, les commandes spéciales (événements, entreprises, mariages) sont fréquentes. Un formulaire de commande avec date de retrait les organise.'],
            ],
            'always' => [
                ['Commandes spéciales organisées', 'Gâteaux, pièces montées, plateaux, bûches : formulaire avec date de retrait, nombre de parts, téléphone, et vos délais annoncés.'],
                ['Horaires exacts et spécialités', 'Horaires avec exceptions, état ouvert/fermé en direct, spécialités en photos. Les mêmes informations que votre fiche Google, à jour partout.'],
            ],
            'searches' => ['boulangerie ouverte dimanche {ville}', 'pâtisserie {q2}', 'gâteau d\'anniversaire {ville}', 'boulangerie {v1}'],
            'faq' => [
                ['Peut-on commander un gâteau depuis le site ?', 'Oui, via le formulaire de commande : date de retrait, parts, message. Vous recevez la demande et confirmez.'],
                ['Le site gère-t-il mes jours de fermeture ?', 'Oui : horaires avec exceptions, congés annoncés en bandeau, état ouvert/fermé calculé en direct.'],
            ],
        ],
        'avocat' => [
            'intro' => [
                'Un justiciable de {q1} ou de {v1} cherche un avocat {Ville} pour un divorce, un licenciement, un litige. Il choisit sur le domaine, les honoraires et la personne. Votre site doit dire les trois, sobrement.',
                'Le site d\'un cabinet d\'avocats {Ville} respecte les règles de la profession : information sincère, non comparative, sans démarchage. Dans ce cadre, il est devenu le premier contact de la plupart des clients.',
                '« Avocat droit du travail {ville} », « avocat divorce {q2} », « avocat {v2} » : des recherches précises, captées par les cabinets dont le site présente clairement domaines et honoraires.',
            ],
            'traits' => [
                'metropole' => ['Se distinguer par le domaine', 'Dans une grande ville, le client cherche un spécialiste : une page par domaine, avec les situations concrètes traitées, vous positionne face à des centaines de confrères.'],
                'etudiante' => ['Jeunes actifs et premiers litiges', 'Baux, emploi, consommation : les jeunes actifs de {ville} rencontrent leurs premiers litiges et cherchent des honoraires clairs et une première consultation accessible.'],
                'frontaliere' => ['Dossiers transfrontaliers', '{Ville}, les questions de droit international privé, de travail frontalier et de fiscalité sont fréquentes. Nommer ces compétences attire une clientèle spécifique.'],
                'industrielle' => ['Entreprises et droit social', 'Les entreprises de {ville} ont besoin de conseil en droit du travail et des affaires. Une section dédiée aux professionnels ouvre des relations durables.'],
                'touristique' => ['Immobilier et locations', 'Locations saisonnières, copropriétés, résidences secondaires : {Ville}, le droit immobilier génère de nombreux dossiers. Une page dédiée y répond.'],
                'patrimoine' => ['Cabinet en centre historique', 'Accès, stationnement, étage, horaires du secrétariat : dans le centre de {ville}, ces précisions évitent des appels et rassurent.'],
            ],
            'always' => [
                ['Domaines et honoraires', 'Une page par domaine avec les situations traitées, les modes de facturation (forfait, horaire, convention, aide juridictionnelle) et la première consultation.'],
                ['Rendez-vous sans détails sensibles', 'Domaine, mode (cabinet, visio, téléphone), créneau souhaité, téléphone. Le secret professionnel commence avant le premier rendez-vous.'],
            ],
            'searches' => ['avocat droit du travail {ville}', 'avocat divorce {ville}', 'avocat droit immobilier {ville}', 'consultation avocat {ville}'],
            'faq' => [
                ['Le site respecte-t-il les règles de la profession ?', 'Il adopte un ton sobre et informatif, sans comparaison, superlatif ni promesse de résultat. La déclaration éventuelle à votre Ordre vous appartient.'],
                ['Puis-je publier des articles juridiques ?', 'Oui, depuis le Studio : des réponses claires aux questions fréquentes attirent des clients concernés et prouvent la compétence, sans conseil personnalisé.'],
            ],
        ],
    ];
}
