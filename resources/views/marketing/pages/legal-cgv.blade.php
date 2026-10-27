@extends('marketing.layout')
@php $mail = config('mail.from.address', 'contact@joow.fr'); $pro = config('marketing.pricing.pro.price', 39); $lib = config('marketing.pricing.liberte.price', 349); @endphp
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Légal', 'heading' => 'Conditions générales d\'utilisation et de vente', 'lead' => 'Ce que vous obtenez, ce que vous payez, ce que chacun s\'engage à faire. Version applicable à compter du 27 octobre 2026.'])
<section class="mx-auto max-w-3xl px-5 prose-joow">
    <h2>1. Objet et acceptation</h2>
    <p>Les présentes conditions régissent l'accès et l'utilisation du service Joow (ci-après « le Service »), édité par <strong>GMD COM</strong>, SASU au capital de 1 800 €, 70 rue Damrémont, 75018 Paris, RCS Paris 882 334 733, TVA FR20 882 334 733 (ci-après « Joow »). Le Service permet à un professionnel de générer un site internet par intelligence artificielle à partir de sa fiche Google Business Profile, de son site existant ou d'un questionnaire, de le modifier, de l'héberger, de le publier et d'y activer des modules (réservation, devis, rendez-vous, assistant, etc.).</p>
    <p>Toute utilisation du Service, gratuite ou payante, emporte acceptation sans réserve des présentes conditions et de la <a href="{{ route('marketing.legal', 'confidentialite') }}">politique de confidentialité</a>. Joow s'adresse aux professionnels (commerçants, artisans, indépendants, professions libérales, sociétés, associations) agissant dans le cadre de leur activité.</p>

    <h2>2. Définitions</h2>
    <ul>
        <li><strong>Client</strong> : le professionnel titulaire d'un compte, ou la personne agissant pour son compte avec les pouvoirs nécessaires.</li>
        <li><strong>Aperçu</strong> : site généré et consultable à une adresse privée, avant toute publication.</li>
        <li><strong>Site</strong> : site internet créé via le Service, accessible en <em>nom.joow.fr</em> ou sur le nom de domaine du Client.</li>
        <li><strong>Studio</strong> : l'éditeur en ligne permettant de modifier le Site.</li>
        <li><strong>Modules</strong> : fonctionnalités activables sur le Site (réservation, devis, rendez-vous, menu, assistant IA, WhatsApp, mentions légales, etc.).</li>
        <li><strong>Crédits IA</strong> : unités consommées par les actions de l'assistant IA du Studio.</li>
        <li><strong>Contenus Client</strong> : l'ensemble des informations, textes, photos, logos, avis et données relatifs à l'établissement du Client, quelle qu'en soit l'origine (fiche Google, site existant, questionnaire, téléversement).</li>
    </ul>

    <h2>3. Compte et accès</h2>
    <p>La génération d'un Aperçu ne nécessite pas de compte. L'accès au Studio, la sauvegarde au-delà de 7 jours et la publication nécessitent un compte, créé avec une adresse email valide ou via « Se connecter avec Google ». Le Client garantit l'exactitude des informations fournies, assure la confidentialité de ses identifiants et répond de toute utilisation de son compte. Joow peut suspendre un compte en cas d'utilisation frauduleuse, de violation des présentes ou de non-paiement, après notification sauf urgence.</p>

    <h2>4. Offre gratuite</h2>
    <p>Sont gratuits et sans engagement : la génération d'Aperçus, la consultation de l'Aperçu, l'accès au Studio avec un nombre limité de Crédits IA de bienvenue, et la conservation du Site non publié tant que le compte est actif. Un Aperçu créé sans adresse email est supprimé automatiquement 7 jours après sa création. Joow peut limiter le nombre d'Aperçus par personne pour prévenir les abus.</p>

    <h2>5. Formules payantes et prix</h2>
    <p>La mise en ligne d'un Site suppose la souscription d'une des formules suivantes. Les prix s'entendent hors taxes ; la TVA au taux en vigueur s'ajoute. Ils sont indiqués sur la page <a href="{{ route('marketing.pricing') }}">Tarifs</a> au moment de la commande, qui seule fait foi.</p>
    <ul>
        <li><strong>Pro</strong> : abonnement mensuel de {{ $pro }} € HT, sans engagement de durée, reconduit tacitement chaque mois. Comprend l'hébergement, la publication, tous les Modules, 100 Crédits IA par mois (non reportables), un nom de domaine inclus (ou la connexion d'un domaine existant) et l'assistance par email.</li>
        <li><strong>Liberté</strong> : paiement unique de {{ $lib }} € HT, donnant droit à l'hébergement et à la publication du Site sans mensualité tant que le Service est exploité (voir article 14), tous les Modules, 100 Crédits IA offerts, un nom de domaine inclus la première année (ou la connexion d'un domaine existant) et l'assistance par email.</li>
        <li><strong>Crédits IA supplémentaires</strong> : vendus par packs, prix affichés dans l'espace Client, valables 12 mois, non remboursables une fois consommés.</li>
        <li><strong>Nom de domaine</strong> : un premier nom de domaine en .fr ou .com est inclus, pour la durée de l'abonnement Pro ou pour la première année en formule Liberté (renouvellement ensuite au prix indiqué dans l'espace Client). Tout domaine supplémentaire est facturé au prix indiqué avant commande. Le domaine est enregistré au nom du Client.</li>
    </ul>
    <p>Joow peut modifier ses prix. Pour les abonnements en cours, toute hausse est notifiée au moins 30 jours avant son application ; le Client peut résilier sans frais avant cette date. La formule Liberté n'est pas affectée par les hausses ultérieures.</p>

    <h2>6. Commande, paiement, facturation</h2>
    <p>Le paiement s'effectue en ligne par carte bancaire via Stripe, prestataire agréé ; Joow n'a jamais accès aux numéros de carte. La commande est ferme à la validation du paiement ; une facture est émise et disponible dans l'espace Client. Pour l'abonnement Pro, le prélèvement intervient à la souscription puis à chaque échéance mensuelle. En cas d'échec de paiement, Joow notifie le Client et retente le prélèvement ; le Site reste en ligne 14 jours, puis est suspendu (page d'attente) jusqu'à régularisation. Sans régularisation sous 60 jours, le contrat est résilié et le Site mis hors ligne. Tout retard de paiement entre professionnels entraîne de plein droit l'indemnité forfaitaire de recouvrement de 40 € et des pénalités au taux de trois fois l'intérêt légal.</p>

    <h2>7. Contenus Client : déclarations et garanties</h2>
    <p>Le Service permet de reprendre des informations publiques relatives à un établissement (fiche Google, site existant). <strong>En créant un compte et, en tout état de cause, en publiant un Site, le Client déclare et garantit :</strong></p>
    <ul>
        <li>qu'il est le propriétaire, l'exploitant ou le représentant dûment habilité de l'établissement concerné, ou qu'il agit avec l'autorisation écrite de celui-ci ;</li>
        <li>qu'il détient les droits nécessaires sur l'ensemble des Contenus Client (textes, photographies, logo, vidéos, marques), y compris ceux repris de sa fiche Google ou de son site existant, et qu'il a vérifié qu'ils ne portent atteinte à aucun droit de tiers (droit d'auteur, droit à l'image, marques, données personnelles) ;</li>
        <li>que les informations publiées sont exactes, à jour et conformes à la réglementation applicable à son activité (mentions obligatoires, prix, allergènes, diplômes, assurances, etc.) ;</li>
        <li>qu'il a relu les contenus produits par l'intelligence artificielle avant publication et en assume seul la responsabilité éditoriale.</li>
    </ul>
    <p>Les avis clients issus de Google sont reproduits tels quels, avec mention de leur origine ; le Client s'interdit de les modifier, d'en inventer ou d'en supprimer de manière sélective en vue de tromper le public (articles L.121-1 et suivants du Code de la consommation). Joow ne garantit pas la disponibilité des données Google et peut être amené à les actualiser ou à les retirer pour se conformer aux conditions de Google.</p>
    <p>Le Client s'interdit tout contenu illicite, trompeur, contrefaisant, diffamatoire, discriminatoire, contraire à l'ordre public ou aux bonnes mœurs, ainsi que tout usage du Service pour usurper l'identité d'un tiers ou d'un établissement qu'il ne représente pas. Joow, en qualité d'hébergeur, peut retirer tout contenu manifestement illicite ou signalé comme tel, suspendre le Site concerné et, en cas de manquement grave ou répété, résilier le compte sans indemnité. Le Client garantit Joow contre toute réclamation de tiers liée aux Contenus Client et l'indemnise des frais et condamnations en résultant.</p>
    <p>Toute personne estimant qu'un Site reprend sans droit les informations de son établissement peut le signaler à <a href="mailto:{{ $mail }}">{{ $mail }}</a>. Joow suspend l'Aperçu ou le Site litigieux dans l'attente des justificatifs du Client.</p>

    <h2>8. Propriété intellectuelle</h2>
    <p><strong>Contenus Client</strong> : le Client en reste propriétaire. Il concède à Joow, pour la durée du contrat, une licence non exclusive et gratuite d'hébergement, de reproduction, d'adaptation technique et de représentation nécessaire à la fourniture du Service, ainsi que le droit de mentionner son Site comme référence (nom et capture d'écran), auquel il peut s'opposer à tout moment par simple email.</p>
    <p><strong>Textes générés</strong> : les textes rédigés par l'IA pour le Site du Client lui sont cédés à titre non exclusif, sans restriction d'usage, dès leur génération.</p>
    <p><strong>Modèles, code et Modules</strong> : ils restent la propriété exclusive de Joow. Le Client bénéficie d'un droit d'utilisation non exclusif et non cessible, limité au Site, pour la durée du contrat. Il s'interdit de copier, décompiler ou réutiliser les modèles, le code ou les Modules en dehors du Service.</p>
    <p><strong>Photographies de banques d'images</strong> : fournies sous les licences Unsplash, Pexels, Pixabay ou Creative Commons indiquées ; le Client s'engage à les respecter (notamment l'interdiction de les revendre ou de les utiliser comme marque).</p>

    <h2>9. Hébergement, disponibilité, sauvegardes</h2>
    <p>Joow héberge le Site sur des serveurs situés dans l'Union européenne, avec certificat HTTPS et sauvegardes quotidiennes conservées 30 jours. Joow s'engage sur une obligation de moyens avec un objectif de disponibilité mensuelle de 99,5 %, hors maintenance programmée (notifiée 48 heures à l'avance et réalisée autant que possible la nuit) et hors cas de force majeure, défaillance des réseaux ou des prestataires tiers, attaque informatique ou fait du Client. Joow peut faire évoluer l'infrastructure et les fonctionnalités sans dégrader substantiellement le Service.</p>

    <h2>10. Nom de domaine</h2>
    <p>Le Client peut connecter un domaine qu'il possède déjà, ou en commander un via Joow. Dans ce cas, le domaine est enregistré au nom et pour le compte du Client, qui en est titulaire ; Joow assure la gestion technique. Le Client peut à tout moment demander le code de transfert. Hors domaine inclus, le renouvellement est facturé annuellement et notifié 30 jours avant l'échéance ; à défaut de paiement, le domaine n'est pas renouvelé et peut être perdu. Joow n'est pas responsable de l'indisponibilité d'un nom souhaité ni des litiges relatifs aux droits des tiers sur un nom.</p>

    <h2>11. Modules et paiements des clients finaux</h2>
    <p>Les Modules sont fournis en l'état et peuvent évoluer. Le module de réservation permet au Client d'exiger une empreinte bancaire ou un acompte via son propre compte Stripe : le contrat de paiement est conclu entre le Client et Stripe, Joow n'étant pas partie aux transactions entre le Client et ses propres clients et ne percevant aucune commission sur celles-ci. Le Client est seul responsable de ses conditions de réservation, d'annulation et de remboursement, qu'il porte à la connaissance de ses clients. L'assistant IA répond à partir des informations du Site ; le Client en vérifie les réglages et reste responsable des informations qu'il fait communiquer.</p>

    <h2>12. Données personnelles et sous-traitance</h2>
    <p>Les traitements dont Joow est responsable sont décrits dans la <a href="{{ route('marketing.legal', 'confidentialite') }}">politique de confidentialité</a>. Pour les données des visiteurs et clients finaux collectées via le Site (réservations, devis, rendez-vous, messages, assistant), le Client est responsable du traitement et Joow sous-traitant. À ce titre, Joow s'engage à : ne traiter ces données que sur instruction documentée du Client et pour les seules finalités du Service ; garantir la confidentialité des personnes habilitées ; mettre en œuvre les mesures de sécurité décrites dans la politique de confidentialité ; recourir aux seuls sous-traitants ultérieurs listés dans cette politique, le Client étant informé par email de tout changement avec faculté d'objection sous 30 jours ; assister le Client dans la réponse aux demandes d'exercice de droits et, le cas échéant, dans la notification des violations de données, qu'il lui signale sans délai indu ; supprimer ou restituer les données en fin de contrat selon l'article 14 ; tenir à disposition les informations nécessaires à la démonstration de la conformité. Le Client s'engage de son côté à disposer d'une base légale pour les traitements qu'il effectue, à informer ses visiteurs (le Module « Mentions légales » fournit un modèle qu'il doit compléter et adapter) et à ne pas collecter via le Site de données sensibles sans fondement légal.</p>

    <h2>13. Durée, résiliation</h2>
    <p>Le compte est ouvert pour une durée indéterminée. L'abonnement Pro est résiliable à tout moment depuis l'espace Client ; la résiliation prend effet à la fin de la période mensuelle en cours, sans remboursement au prorata. La formule Liberté est conclue pour la durée d'exploitation du Service. Le Client peut supprimer son Site et son compte à tout moment. Joow peut résilier le contrat de plein droit, par email, en cas de manquement grave non réparé sous 15 jours après mise en demeure, ou immédiatement en cas de contenu illicite, de fraude ou d'atteinte à la sécurité du Service.</p>

    <h2>14. Fin de contrat et réversibilité</h2>
    <p>À la fin de l'abonnement Pro ou à la suppression du compte, le Site est mis hors ligne. Pendant 30 jours, le Client peut exporter depuis son espace ses contenus (textes, images, données de réservation et de contacts au format CSV) et récupérer son nom de domaine. Les données sont ensuite supprimées, sauf obligation légale de conservation. En formule Liberté, si Joow venait à cesser l'exploitation du Service, le Client en serait informé au moins 3 mois à l'avance et disposerait des mêmes facultés d'export ; aucune autre indemnité ne serait due au-delà de cette période de préavis, le prix payé couvrant l'hébergement pour la durée d'exploitation du Service.</p>

    <h2>15. Droit de rétractation</h2>
    <p>Le Client professionnel ne bénéficie pas du droit de rétractation, sauf s'il emploie cinq salariés au plus et que l'objet du contrat n'entre pas dans le champ de son activité principale (article L.221-3 du Code de la consommation) ; il dispose alors de 14 jours à compter de la souscription pour se rétracter par email, sans motif. En demandant la publication immédiate de son Site, le Client demande expressément l'exécution du service avant la fin du délai et reconnaît qu'il devra, en cas de rétractation, un montant proportionnel au service déjà fourni ; les frais d'enregistrement de nom de domaine, exécutés immédiatement auprès du registre, ne sont pas remboursables. L'Aperçu gratuit permet en toute hypothèse de juger du résultat avant tout paiement.</p>

    <h2>16. Responsabilité</h2>
    <p>Joow fournit un outil de création et d'hébergement ; il ne garantit pas de résultat commercial (visites, contacts, classement dans les moteurs de recherche) et n'est pas responsable des Contenus Client ni de l'usage que le Client fait du Site. Les textes et analyses produits par l'IA peuvent comporter des inexactitudes : le Client les relit avant publication. La responsabilité de Joow, toutes causes confondues, est limitée aux dommages directs et prouvés et ne peut excéder les sommes effectivement payées par le Client au cours des 12 mois précédant le fait générateur. Joow n'est pas responsable des dommages indirects (perte de chiffre d'affaires, de clientèle, de données imputable au Client, atteinte à l'image). Ces limitations ne s'appliquent pas en cas de faute lourde ou dolosive, ni aux dommages corporels, ni lorsque la loi l'interdit.</p>

    <h2>17. Assistance</h2>
    <p>L'assistance est assurée par email à <a href="mailto:{{ $mail }}">{{ $mail }}</a>, du lundi au vendredi, avec un objectif de première réponse sous 24 heures ouvrées. L'assistant IA du Studio est disponible en permanence.</p>

    <h2>18. Force majeure</h2>
    <p>Aucune partie n'est responsable d'un manquement dû à un événement de force majeure au sens de l'article 1218 du Code civil, y compris la défaillance généralisée des réseaux, des hébergeurs ou des fournisseurs d'API tiers, les attaques informatiques d'ampleur et les décisions des autorités.</p>

    <h2>19. Modification des conditions</h2>
    <p>Joow peut modifier les présentes conditions. Les titulaires de compte sont informés par email au moins 30 jours avant l'entrée en vigueur de modifications substantielles ; la poursuite de l'utilisation du Service vaut acceptation. Le Client qui refuse peut résilier avant cette date, sans frais.</p>

    <h2>20. Dispositions diverses</h2>
    <p>Si une clause était déclarée nulle, les autres resteraient applicables. Le fait pour Joow de ne pas se prévaloir d'un manquement ne vaut pas renonciation. Le Client accepte que les notifications lui soient adressées à l'email de son compte. Le contrat est rédigé en français ; les registres informatiques de Joow font foi des opérations effectuées, sauf preuve contraire.</p>

    <h2>21. Droit applicable et litiges</h2>
    <p>Les présentes conditions sont soumises au droit français. En cas de différend, les parties recherchent d'abord une solution amiable par écrit à <a href="mailto:{{ $mail }}">{{ $mail }}</a>. À défaut d'accord sous 30 jours, le litige relève des tribunaux compétents du ressort de la Cour d'appel de Paris pour les Clients professionnels, nonobstant pluralité de défendeurs ou appel en garantie. Le Client ayant la qualité de consommateur conserve la faculté de saisir le médiateur de la consommation mentionné dans les <a href="{{ route('marketing.legal', 'mentions-legales') }}">mentions légales</a> et les juridictions territorialement compétentes selon le Code de procédure civile.</p>
    <p><em>Version du 27 octobre 2026.</em></p>
</section>
@endsection
