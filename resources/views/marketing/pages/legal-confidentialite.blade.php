@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Légal', 'heading' => 'Politique de confidentialité', 'lead' => 'Ce que nous collectons, pourquoi, combien de temps, et comment exercer vos droits. En français clair.'])
<section class="mx-auto max-w-3xl px-5 prose-joow">
    <h2>Qui est responsable ?</h2>
    <p><strong>[Raison sociale à compléter]</strong>, éditeur de Joow, est responsable du traitement des données décrites ici. Contact : <a href="mailto:{{ config('mail.from.address', 'contact@joow.fr') }}">{{ config('mail.from.address', 'contact@joow.fr') }}</a>.</p>
    <h2>Les données que nous traitons</h2>
    <ul>
        <li><strong>Compte et site</strong> : adresse email, nom, informations de votre établissement (issues de votre fiche Google publique, de votre site existant ou de vos réponses), photos que vous téléversez. Base : exécution du service.</li>
        <li><strong>Paiement</strong> : traité par Stripe ; nous ne conservons pas vos numéros de carte. Base : exécution du contrat.</li>
        <li><strong>Emails</strong> : emails indispensables (lien de connexion, site prêt, facture) et emails de conseils, dont vous pouvez vous désinscrire en un clic. Base : intérêt légitime, opposition possible à tout moment.</li>
        <li><strong>Mesure publicitaire</strong> : avec votre accord uniquement (bannière), un pixel Meta et l'API de conversions Meta nous indiquent quelles campagnes vous ont amené ici. Sans accord, rien n'est déposé ni envoyé. Base : consentement, retirable à tout moment en supprimant le cookie <code>joow_consent</code>.</li>
        <li><strong>Journaux techniques</strong> : adresse IP, navigateur, erreurs, conservés 12 mois pour la sécurité. Base : intérêt légitime.</li>
        <li><strong>Demandes reçues par les sites générés</strong> (réservations, devis, messages) : traitées pour le compte de l'établissement, qui en est responsable ; Joow agit comme sous-traitant.</li>
    </ul>
    <h2>Combien de temps ?</h2>
    <p>Aperçus créés sans email : supprimés automatiquement après 7 jours. Compte et site : pendant la durée de votre utilisation, puis 3 ans après le dernier contact. Factures : 10 ans (obligation légale).</p>
    <h2>Avec qui ?</h2>
    <p>Nos sous-traitants, chacun limité à sa mission : Hostinger (hébergement, UE), Resend (emails), Stripe (paiement), Google (données de fiche d'établissement, cartes), Google Gemini (génération des textes et analyse des photos, sans conservation à des fins d'entraînement selon les conditions API), Unsplash, Pexels, Pixabay et Openverse (banques d'images), Meta (mesure publicitaire, avec votre accord).</p>
    <h2>Vos droits</h2>
    <p>Accès, rectification, suppression, opposition, portabilité, limitation : écrivez à <a href="mailto:{{ config('mail.from.address', 'contact@joow.fr') }}">{{ config('mail.from.address', 'contact@joow.fr') }}</a>. Nous répondons sous un mois. Vous pouvez aussi saisir la CNIL.</p>
    <h2>Cookies</h2>
    <p>Cookies indispensables : session et sécurité (pas de choix possible, ils ne tracent pas). Cookie de consentement : mémorise votre choix 6 mois. Cookies de mesure publicitaire Meta (<code>_fbp</code>, <code>_fbc</code>) : uniquement après « Accepter ».</p>
    <p><em>Dernière mise à jour : {{ date('d/m/Y') }}.</em></p>
</section>
@endsection
