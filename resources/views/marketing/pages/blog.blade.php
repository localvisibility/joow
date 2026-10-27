@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Blog', 'heading' => 'Être trouvé, inspirer confiance, être choisi', 'lead' => 'Des conseils concrets pour les commerces, artisans et indépendants : site internet, fiche Google, avis, réservation en ligne, nom de domaine.'])
<section class="mx-auto max-w-7xl px-5 pb-10">
    <nav class="mb-8 flex flex-wrap justify-center gap-2" aria-label="Catégories"><a href="{{ route('marketing.blog') }}" class="rounded-full border px-4 py-1.5 text-sm {{ $category === '' ? 'border-brand-400 text-white' : 'border-white/10 text-slate-300 hover:border-brand-400/60 hover:text-white' }}">Tous</a>@foreach($categories as $c => $n)<a href="{{ route('marketing.blog', ['categorie' => $c]) }}" class="rounded-full border px-4 py-1.5 text-sm {{ $category === $c ? 'border-brand-400 text-white' : 'border-white/10 text-slate-300 hover:border-brand-400/60 hover:text-white' }}">{{ $c }} <span class="text-slate-500">{{ $n }}</span></a>@endforeach</nav>
    <div class="stagger grid gap-5 md:grid-cols-2 lg:grid-cols-3">@forelse($posts as $p)@include('marketing.partials.post-card', ['p' => $p])@empty<p class="text-slate-400">Premiers articles en préparation.</p>@endforelse</div></section>
@include('marketing.partials.cta')
@endsection
