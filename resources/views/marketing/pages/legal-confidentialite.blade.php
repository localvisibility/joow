@extends('marketing.layout')
@php $mail = config('mail.from.address', 'contact@joow.fr'); @endphp
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Légal', 'heading' => 'Politique de confidentialité', 'lead' => 'Quelles données nous traitons, pourquoi, combien de temps, avec qui, et comment exercer vos droits. Rédigée pour être lue, pas seulement affichée.'])
<section class="mx-auto max-w-3xl px-5 prose-joow">
    <h2>1. Responsable du traitement</h2>
    <p><strong>GMD COM</strong>, SASU au capital de 1 800 €, 70 rue Damrémont, 75018 Paris, RCS Paris 882 334 733, éditrice du service Joow, est responsable des traitements décrits dans cette politique. Contact pour toute question relative à vos données : <a href="mailto:{{ $mail }}">{{ $mail }}</a>.</p>
    <p>Cette politique s'applique au site joow.fr, à l'application app.joow.fr, aux emails que nous envoyons et aux données traitées lors de la création d'un site. Pour les données collectées <em>par les sites de nos clients</em> (formulaires de réservation, de devis, de contact), voir la section 8 : le professionnel est alors responsable du traitement et Joow son sous-traitant.</p>

    <h2>2. Les données que nous traitons, et pourquoi</h2>
    <table>
        <thead><tr><th>Données</th><th>Finalité</th><th>Base légale</th><th>Durée</th></tr></thead>
        <tbody>
            <tr><td>Email, nom, mot de passe (haché), identifiant Google si connexion Google</td><td>Créer et sécuriser votre compte, vous donner accès à vos sites et au Studio</td><td>Exécution du contrat</td><td>Durée du compte, puis 3 ans après le dernier contact</td></tr>
            <tr><td>Informations de votre établissement : nom, adresse, téléphone, horaires, prestations, photos, logo, avis publics</td><td>Générer, afficher, modifier et héberger votre site</td><td>Exécution du contrat ; intérêt légitime pour l'aperçu gratuit (section 3)</td><td>Durée du site ; aperçus sans email supprimés sous 7 jours</td></tr>
            <tr><td>Réponses au questionnaire de création, adresse de votre site existant et contenus qui en sont extraits</td><td>Alimenter la génération et la refonte</td><td>Exécution du contrat</td><td>Durée du site</td></tr>
            <tr><td>Données de facturation et historique des paiements (les numéros de carte sont traités par Stripe, jamais stockés par Joow)</td><td>Encaisser, facturer, prévenir la fraude</td><td>Exécution du contrat, obligation légale</td><td>10 ans (pièces comptables)</td></tr>
            <tr><td>Emails envoyés et ouverts, statut des relances</td><td>Emails indispensables (lien de connexion, site prêt, facture) et emails de conseils</td><td>Exécution du contrat ; intérêt légitime pour les conseils, avec désinscription en un clic</td><td>Durée du compte</td></tr>
            <tr><td>Paramètres de campagne (utm, fbclid), identifiants publicitaires Meta (_fbp, _fbc), événements de conversion</td><td>Mesurer l'efficacité de nos publicités</td><td><strong>Consentement</strong> (bannière), retirable à tout moment</td><td>13 mois maximum</td></tr>
            <tr><td>Adresse IP, navigateur, pages consultées, erreurs techniques</td><td>Sécurité, prévention des abus, diagnostic</td><td>Intérêt légitime</td><td>12 mois</td></tr>
            <tr><td>Messages envoyés via le formulaire de contact</td><td>Vous répondre</td><td>Intérêt légitime (mesures précontractuelles)</td><td>3 ans</td></tr>
            <tr><td>Questions posées à l'assistant IA du Studio</td><td>Vous aider à modifier votre site</td><td>Exécution du contrat</td><td>12 mois</td></tr>
        </tbody>
    </table>
    <p>Nous ne vendons aucune donnée et ne faisons aucune décision automatisée produisant des effets juridiques à votre égard.</p>

    <h2>3. Données issues de votre fiche Google et de votre site existant</h2>
    <p>Pour générer un site, Joow s'appuie sur des informations <strong>déjà rendues publiques par le professionnel lui-même</strong> : sa fiche Google Business Profile (nom, adresse, téléphone, horaires, catégorie, photos, note et avis) et, le cas échéant, les pages de son site internet actuel. Ces informations sont obtenues via l'API officielle Google Maps Platform et par la lecture des pages publiques du site indiqué.</p>
    <ul>
        <li><strong>Avant toute publication</strong>, le site généré n'est qu'un aperçu privé : adresse non devinable, non indexée par les moteurs de recherche, supprimée automatiquement après 7 jours si aucun compte ne le revendique.</li>
        <li><strong>La publication</strong> n'est possible qu'après création d'un compte par le professionnel, qui déclare être le représentant légitime de l'établissement et détenir les droits sur les contenus repris (voir les <a href="{{ route('marketing.legal', 'cgv') }}">conditions générales</a>, article 7).</li>
        <li><strong>Les avis Google</strong> affichés comportent le prénom ou le pseudonyme public de leur auteur et le texte qu'il a publié. Ils sont reproduits à l'identique, avec mention de leur source. Un auteur d'avis peut demander le retrait de son avis d'un site Joow en écrivant à <a href="mailto:{{ $mail }}">{{ $mail }}</a> ; nous le masquons sous 72 heures.</li>
        <li><strong>Si vous n'êtes pas à l'origine d'un aperçu</strong> qui concerne votre établissement (par exemple créé par un tiers), vous pouvez en demander la suppression immédiate à la même adresse. Base légale de l'aperçu : notre intérêt légitime à permettre au professionnel de visualiser son futur site avant de s'engager, avec des garanties fortes (durée limitée, non-indexation, retrait sur simple demande).</li>
    </ul>
    <p>L'utilisation des données Google est soumise aux <a href="https://cloud.google.com/maps-platform/terms" rel="nofollow noopener" target="_blank">conditions de Google Maps Platform</a> et à la <a href="https://policies.google.com/privacy" rel="nofollow noopener" target="_blank">politique de confidentialité de Google</a>.</p>

    <h2>4. Intelligence artificielle</h2>
    <p>Les textes du site, l'analyse des photos et les réponses de l'assistant sont produits par les modèles Gemini de Google, via leur API professionnelle. Les données envoyées ne sont pas utilisées par Google pour entraîner ses modèles, conformément aux conditions de l'API. Les contenus générés sont des propositions : le professionnel les relit et en reste l'éditeur responsable.</p>

    <h2>5. Destinataires et sous-traitants</h2>
    <p>Vos données sont accessibles aux seules personnes habilitées de GMD COM et aux prestataires suivants, chacun limité à sa mission et lié par contrat :</p>
    <ul>
        <li>Hostinger International Ltd. (hébergement, Union européenne)</li>
        <li>Stripe Payments Europe, Ltd. (paiement, Irlande ; données de carte traitées par Stripe selon la norme PCI-DSS)</li>
        <li>Resend, Inc. (envoi d'emails, États-Unis)</li>
        <li>Google Ireland Ltd. (données d'établissement, cartes, connexion Google, modèles Gemini)</li>
        <li>Unsplash, Pexels, Pixabay, Openverse (banques d'images ; seuls des mots-clés anonymes leur sont transmis)</li>
        <li>Meta Platforms Ireland Ltd. (mesure publicitaire, uniquement avec votre consentement)</li>
    </ul>
    <p><strong>Transferts hors Union européenne</strong> : certains prestataires (Resend, Google, Meta, Stripe pour une partie de ses traitements) peuvent traiter des données aux États-Unis. Ces transferts sont encadrés par le cadre de protection des données UE-États-Unis (Data Privacy Framework) lorsque le prestataire y est certifié, et par les clauses contractuelles types de la Commission européenne.</p>

    <h2>6. Cookies et traceurs</h2>
    <table>
        <thead><tr><th>Cookie</th><th>Rôle</th><th>Durée</th><th>Choix</th></tr></thead>
        <tbody>
            <tr><td>joow-session, XSRF-TOKEN</td><td>Session et sécurité des formulaires</td><td>2 heures</td><td>Indispensable, pas de consentement requis</td></tr>
            <tr><td>remember_web</td><td>Rester connecté si vous l'avez demandé</td><td>1 an</td><td>Indispensable</td></tr>
            <tr><td>joow_consent</td><td>Mémoriser votre choix de consentement</td><td>6 mois</td><td>Indispensable</td></tr>
            <tr><td>_fbp, _fbc (Meta)</td><td>Mesure de l'efficacité de nos campagnes publicitaires</td><td>90 jours</td><td><strong>Uniquement après « Accepter »</strong> dans la bannière</td></tr>
        </tbody>
    </table>
    <p>Tant que vous n'avez pas accepté, aucun traceur publicitaire n'est déposé et aucune donnée n'est envoyée à Meta. Pour retirer votre consentement, supprimez le cookie « joow_consent » dans votre navigateur : la bannière réapparaît. Nous n'utilisons pas de cookies de mesure d'audience tiers sur joow.fr.</p>

    <h2>7. Vos droits</h2>
    <p>Vous disposez des droits d'accès, de rectification, d'effacement, de limitation, d'opposition et de portabilité, ainsi que du droit de retirer votre consentement à tout moment et de définir des directives sur le sort de vos données après votre décès. Pour les exercer : <a href="mailto:{{ $mail }}">{{ $mail }}</a>, ou par courrier à GMD COM, 70 rue Damrémont, 75018 Paris. Nous répondons sous un mois ; une pièce d'identité peut être demandée en cas de doute raisonnable sur votre identité.</p>
    <p>Depuis votre espace, vous pouvez à tout moment modifier vos informations, vous désinscrire des emails de conseils (lien présent dans chaque email) et supprimer vos sites. Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir la CNIL (<a href="https://www.cnil.fr" rel="nofollow noopener" target="_blank">cnil.fr</a>, 3 place de Fontenoy, 75007 Paris).</p>

    <h2>8. Données collectées par les sites de nos clients</h2>
    <p>Lorsqu'un visiteur réserve une table, demande un devis, prend rendez-vous ou écrit via un site créé avec Joow, les données saisies sont traitées <strong>pour le compte du professionnel</strong>, qui en est le responsable du traitement. GMD COM agit comme sous-traitant au sens de l'article 28 du RGPD : nous les hébergeons, les affichons dans l'espace du professionnel, envoyons les confirmations et rappels, et ne les utilisons pour aucune autre finalité. Les engagements correspondants (sécurité, confidentialité, assistance, suppression en fin de contrat, sous-traitants ultérieurs) figurent à l'article 12 des <a href="{{ route('marketing.legal', 'cgv') }}">conditions générales</a>. Les visiteurs exercent leurs droits auprès du professionnel, dont les coordonnées figurent dans les mentions légales de son site ; nous l'assistons dans sa réponse.</p>
    <p>Les sites générés ne déposent que des cookies techniques et une mesure d'audience sans cookie ni identifiant individuel (comptage de pages vues côté serveur).</p>

    <h2>9. Sécurité</h2>
    <p>Chiffrement des échanges (HTTPS), mots de passe hachés, accès restreints et journalisés, sauvegardes quotidiennes chiffrées, séparation des environnements, mises à jour de sécurité régulières. En cas de violation de données susceptible d'engendrer un risque pour vos droits, nous notifions la CNIL sous 72 heures et vous informons lorsque la loi l'exige.</p>

    <h2>10. Mineurs</h2>
    <p>Le service s'adresse aux professionnels et n'est pas destiné aux personnes de moins de 15 ans.</p>

    <h2>11. Modifications</h2>
    <p>Nous pouvons faire évoluer cette politique pour refléter des changements du service ou de la réglementation. La date ci-dessous fait foi ; en cas de changement substantiel, les titulaires de compte sont prévenus par email.</p>
    <p><em>Dernière mise à jour : 27 octobre 2026.</em></p>
</section>
@endsection
