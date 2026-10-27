@extends('marketing.layout')
@php $mail = config('mail.from.address', 'contact@joow.fr'); @endphp
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Légal', 'heading' => 'Mentions légales', 'lead' => 'Informations légales relatives au site joow.fr et au service Joow, conformément à la loi pour la confiance dans l\'économie numérique.'])
<section class="mx-auto max-w-3xl px-5 prose-joow">
    <h2>Éditeur</h2>
    <p>Le site joow.fr, le sous-domaine app.joow.fr et le service Joow sont édités par :</p>
    <p><strong>GMD COM</strong>, société par actions simplifiée à associé unique au capital de 1 800 €<br>
    Siège social : 70 rue Damrémont, 75018 Paris, France<br>
    Immatriculée au RCS de Paris sous le numéro 882 334 733 (SIRET du siège : 882 334 733 00038)<br>
    N° de TVA intracommunautaire : FR20 882 334 733<br>
    Code APE : 73.11Z (activités des agences de publicité)<br>
    Président et directeur de la publication : Yaniv Benaim<br>
    Email : <a href="mailto:{{ $mail }}">{{ $mail }}</a></p>
    <p>Joow est une marque exploitée par GMD COM. Dans la suite des documents légaux, « Joow », « nous » et « GMD COM » désignent la même entité.</p>

    <h2>Hébergement</h2>
    <p>Le service est hébergé sur des serveurs loués auprès de <strong>Hostinger International Ltd.</strong>, 61 Lordou Vironos Street, 6023 Larnaca, Chypre (<a href="https://www.hostinger.fr" rel="nofollow noopener" target="_blank">hostinger.fr</a>), situés dans l'Union européenne. Les sauvegardes sont conservées dans l'Union européenne.</p>
    <p>Les sites créés avec Joow (adresses en <em>nom.joow.fr</em> ou sous le nom de domaine du client) sont hébergés dans les mêmes conditions. GMD COM en est l'hébergeur technique ; l'éditeur de chaque site est le professionnel qui l'a publié, dont les coordonnées figurent dans les mentions légales du site concerné.</p>

    <h2>Prestataires techniques</h2>
    <ul>
        <li><strong>Stripe Payments Europe, Ltd.</strong> (Irlande) : paiement sécurisé des abonnements et des achats.</li>
        <li><strong>Resend, Inc.</strong> (États-Unis) : acheminement des emails transactionnels.</li>
        <li><strong>Google Ireland Ltd.</strong> : données publiques des fiches d'établissement (Google Maps Platform), cartes, connexion « Se connecter avec Google », modèles d'intelligence artificielle Gemini.</li>
        <li><strong>Unsplash, Pexels, Pixabay, Openverse</strong> : banques d'images libres de droits utilisées conformément à leurs licences respectives.</li>
        <li><strong>Meta Platforms Ireland Ltd.</strong> : mesure de l'efficacité publicitaire, uniquement avec votre consentement.</li>
    </ul>

    <h2>Propriété intellectuelle</h2>
    <p>La marque Joow, le logo, la charte graphique, l'interface, les modèles de sites, les textes et le code du service sont la propriété de GMD COM ou de ses concédants et sont protégés par le Code de la propriété intellectuelle. Toute reproduction, représentation ou extraction, totale ou partielle, sans autorisation écrite est interdite.</p>
    <p>Les contenus propres à chaque site créé avec Joow (nom, textes relatifs à l'établissement, photographies, logo, avis) appartiennent au professionnel concerné ou à leurs auteurs respectifs. Les conditions d'utilisation de ces contenus sont décrites dans nos <a href="{{ route('marketing.legal', 'cgv') }}">conditions générales</a>.</p>

    <h2>Données personnelles et cookies</h2>
    <p>Le traitement des données personnelles et l'usage des cookies sont décrits dans notre <a href="{{ route('marketing.legal', 'confidentialite') }}">politique de confidentialité</a>. Vous pouvez modifier votre choix en matière de cookies à tout moment en supprimant le cookie « joow_consent » de votre navigateur.</p>

    <h2>Signalement d'un contenu</h2>
    <p>Si vous estimez qu'un site hébergé par Joow porte atteinte à vos droits (par exemple l'utilisation sans autorisation du nom, des photos ou des informations de votre établissement), écrivez à <a href="mailto:{{ $mail }}">{{ $mail }}</a> en indiquant l'adresse du site concerné, les contenus visés, vos coordonnées et les éléments justifiant votre demande. Conformément à l'article 6-I-5 de la loi n° 2004-575, nous traitons les notifications dans les meilleurs délais, et les aperçus non revendiqués sont supprimés automatiquement sous 7 jours.</p>

    <h2>Médiation</h2>
    <p>Joow s'adresse aux professionnels. Pour les clients qui auraient la qualité de consommateur, et après réclamation écrite restée sans réponse satisfaisante sous 30 jours, un médiateur de la consommation peut être saisi gratuitement. Les coordonnées du médiateur désigné sont communiquées sur demande à <a href="mailto:{{ $mail }}">{{ $mail }}</a>. La plateforme européenne de règlement en ligne des litiges est accessible sur <a href="https://ec.europa.eu/consumers/odr" rel="nofollow noopener" target="_blank">ec.europa.eu/consumers/odr</a>.</p>

    <h2>Crédits</h2>
    <p>Conception et réalisation : GMD COM. Icônes : Font Awesome (licence CC BY 4.0). Polices : Google Fonts. Photographies d'illustration : Unsplash, Pexels, Pixabay.</p>
    <p><em>Dernière mise à jour : 27 octobre 2026.</em></p>
</section>
@endsection
