@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'À propos', 'heading' => 'Un site professionnel<br>pour ceux qui n\'ont pas le temps', 'lead' => 'Joow est né d\'un constat simple : la plupart des commerces, artisans et indépendants ont une fiche Google soignée et pas de site, ou un site qu\'ils n\'osent plus montrer. Pas par manque d\'envie : par manque de temps, de budget et de patience.'])
<section class="mx-auto max-w-3xl px-5 prose-joow">
    <h2>Ce que nous croyons</h2>
    <p>Un site n'a pas besoin d'un brief de dix pages, d'un graphiste et de six semaines. Il a besoin de vos informations réelles, de vos photos, de vos avis, et d'un soin de présentation que vous n'avez pas à fournir vous-même. C'est exactement ce que fait l'IA de Joow, en 90 secondes, pour {{ $stats['sectors'] }} métiers différents.</p>
    <h2>Ce que nous refusons</h2>
    <p>Les textes génériques réutilisables pour n'importe quel concurrent. Les photos de banque sans rapport avec votre activité. Les abonnements qui vous enferment. Les « extensions » payantes pour chaque fonction de base. Avec Joow, les textes parlent de vous, les modules sont inclus, et la formule Liberté vous laisse partir avec votre site.</p>
    <h2>Où nous en sommes</h2>
    <p>Plus de {{ number_format($stats['sites'], 0, ',', ' ') }} sites générés, une note moyenne des établissements de {{ $stats['rating'] }}/5 reprise depuis Google, et un produit qui s'améliore chaque semaine : détection des métiers, choix des photos, assistant IA, refonte de sites existants.</p>
    <h2>Nous écrire</h2>
    <p>Une question, une idée, un métier mal servi ? <a href="{{ route('marketing.contact') }}">Écrivez-nous</a>, un humain vous répond.</p>
</section>
@include('marketing.partials.cta')
@endsection
