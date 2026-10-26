@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Blog', 'heading' => 'Être trouvé, inspirer confiance, être choisi', 'lead' => 'Des conseils concrets pour les commerces, artisans et indépendants : site internet, fiche Google, avis, réservation en ligne, nom de domaine.'])
<section class="mx-auto max-w-7xl px-5 pb-10"><div class="stagger grid gap-5 md:grid-cols-2 lg:grid-cols-3">@forelse($posts as $p)@include('marketing.partials.post-card', ['p' => $p])@empty<p class="text-slate-400">Premiers articles en préparation.</p>@endforelse</div></section>
@include('marketing.partials.cta')
@endsection
