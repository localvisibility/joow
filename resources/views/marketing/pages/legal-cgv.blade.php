@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Légal', 'heading' => 'Conditions générales de vente'])
<section class="mx-auto max-w-3xl px-5 prose-joow">
    <h2>1. Objet</h2>
    <p>Les présentes conditions régissent l'utilisation du service Joow, édité par <strong>[Raison sociale à compléter]</strong> : génération, édition, hébergement et mise en ligne de sites internet pour professionnels.</p>
    <h2>2. Offre gratuite</h2>
    <p>La génération d'un site et son aperçu sont gratuits et sans engagement. L'aperçu créé sans adresse email est supprimé automatiquement après 7 jours. Le Studio d'édition est accessible gratuitement, avec un nombre limité de crédits d'assistant IA.</p>
    <h2>3. Formules payantes</h2>
    <p><strong>Pro</strong> : abonnement mensuel à 39 € HT, sans engagement de durée, résiliable à tout moment depuis l'espace client, effet à la fin de la période en cours. Comprend l'hébergement, tous les modules, 100 crédits IA par mois et le nom de domaine personnalisé. <strong>Liberté</strong> : paiement unique de 349 € HT, comprenant l'hébergement sans mensualité, tous les modules et 100 crédits IA offerts. Les crédits IA supplémentaires sont vendus par packs. Prix en euros hors taxes, TVA applicable en sus.</p>
    <h2>4. Paiement</h2>
    <p>Par carte bancaire via Stripe. En cas d'échec de prélèvement de l'abonnement Pro, le site reste accessible 14 jours, puis est suspendu jusqu'à régularisation.</p>
    <h2>5. Droit de rétractation</h2>
    <p>Le service s'adresse aux professionnels. L'aperçu gratuit permet de juger du résultat avant tout paiement. Pour les professionnels de cinq salariés au plus, hors objet de leur activité principale, le droit de rétractation de 14 jours s'applique ; la mise en ligne immédiate vaut demande d'exécution anticipée.</p>
    <h2>6. Contenus et responsabilités</h2>
    <p>Le client garantit disposer des droits sur les contenus de son site (textes, photos, informations) et répond de leur exactitude et de leur légalité. Joow fournit les moyens techniques, assure l'hébergement et la disponibilité du service avec un objectif de 99,5 % mensuels, et ne saurait être tenu responsable des résultats commerciaux.</p>
    <h2>7. Nom de domaine</h2>
    <p>Les domaines commandés via Joow sont enregistrés au nom du client, qui en reste titulaire. Les frais d'enregistrement et de renouvellement sont indiqués avant commande.</p>
    <h2>8. Résiliation et réversibilité</h2>
    <p>Le client peut résilier à tout moment. À la fin de l'abonnement Pro, le site est mis hors ligne ; le client peut exporter ses contenus depuis son espace pendant 30 jours. En formule Liberté, le site reste en ligne tant que le service existe ; en cas d'arrêt du service, un préavis de 3 mois et un export sont fournis.</p>
    <h2>9. Droit applicable</h2>
    <p>Droit français. En cas de litige, les parties recherchent une solution amiable avant toute action devant les tribunaux compétents de [ville du siège].</p>
</section>
@endsection
