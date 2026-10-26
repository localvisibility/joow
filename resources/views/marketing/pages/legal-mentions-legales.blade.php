@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Légal', 'heading' => 'Mentions légales'])
<section class="mx-auto max-w-3xl px-5 prose-joow">
    <h2>Éditeur du site</h2>
    <p>Le site joow.fr et le service Joow sont édités par <strong>[Raison sociale à compléter]</strong>, [forme juridique] au capital de [montant] €, immatriculée au RCS de [ville] sous le numéro [SIREN], dont le siège social est situé [adresse complète]. Numéro de TVA intracommunautaire : [FR…]. Directeur de la publication : [nom]. Contact : <a href="mailto:{{ config('mail.from.address', 'contact@joow.fr') }}">{{ config('mail.from.address', 'contact@joow.fr') }}</a>.</p>
    <h2>Hébergement</h2>
    <p>Le service est hébergé par Hostinger International Ltd (61 Lordou Vironos Street, 6023 Larnaca, Chypre) sur des serveurs situés dans l'Union européenne. Les emails transactionnels sont acheminés par Resend, Inc. Les paiements sont traités par Stripe Payments Europe, Ltd.</p>
    <h2>Propriété intellectuelle</h2>
    <p>La marque Joow, le logo, l'interface et le code du service sont protégés. Les contenus des sites générés (textes, photos, informations) appartiennent aux établissements concernés. Les photos issues de banques d'images sont utilisées conformément à leurs licences (Unsplash, Pexels, Pixabay, Openverse).</p>
    <h2>Données personnelles</h2>
    <p>Le traitement des données est décrit dans notre <a href="{{ route('marketing.legal', 'confidentialite') }}">politique de confidentialité</a>.</p>
</section>
@endsection
