@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => count($cities).' villes · '.count($sectors).' métiers', 'heading' => 'Un site pensé pour <span class="text-shimmer">votre ville</span>', 'lead' => 'La recherche locale décide : « plombier Lyon », « coiffeur Nantes », « restaurant Bordeaux ». Joow rédige votre site autour de votre métier, de votre ville et de vos quartiers.', 'crumbs' => [['Accueil', route('marketing.home')], ['Villes', null]]])
<section class="mx-auto max-w-7xl px-5 pb-10">
    <div class="stagger grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach($cities as $c)<a href="{{ route('marketing.city', $c['slug']) }}" class="reveal glass glass-hover group flex items-center justify-between gap-4 rounded-2xl p-4"><span class="min-w-0"><span class="block truncate font-semibold text-white group-hover:text-brand-400">{{ $c['name'] }}</span><span class="block text-xs text-slate-500">{{ $c['dept_name'] }} ({{ $c['dept'] }})</span></span><span class="text-slate-500 group-hover:text-brand-400">→</span></a>@endforeach
    </div>
    <div class="mt-12 reveal glass rounded-3xl p-7"><p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Votre ville n'y est pas ?</p><h2 class="mt-3 font-display text-2xl font-bold text-white">Ça ne change rien pour votre site</h2><p class="mt-3 text-slate-400">Ces pages présentent notre approche pour les grandes villes. Le générateur fonctionne partout en France : il reprend la ville et la zone de votre fiche Google, où que vous soyez. <a href="{{ route('marketing.sectors') }}" class="text-brand-400 hover:text-white">Choisissez votre métier</a> ou tapez votre nom en haut de page.</p></div>
</section>
@include('marketing.partials.cta')
@endsection
