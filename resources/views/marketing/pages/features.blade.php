@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Fonctionnalités', 'heading' => 'Tout ce qu\'un site doit faire.<br>Rien que vous ayez à configurer.', 'lead' => 'De la génération par IA à la réservation en ligne, chaque fonctionnalité est pensée pour un commerce, un artisan ou un indépendant qui n\'a pas de temps à perdre.'])
<section class="mx-auto max-w-7xl px-5 pb-10">
    <div class="stagger grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach($features as $k => $f)
        <a href="{{ route('marketing.feature', $k) }}" class="reveal glass glass-hover group rounded-3xl p-7"><span class="text-3xl">{{ $f['icon'] }}</span><h2 class="mt-4 font-display text-xl font-bold text-white group-hover:text-brand-400">{{ $f['title'] }}</h2><p class="mt-2 text-sm font-semibold text-slate-300">{{ $f['tagline'] }}</p><p class="mt-2 text-sm text-slate-400">{{ \Illuminate\Support\Str::limit($f['summary'], 140) }}</p><span class="mt-4 inline-block text-xs font-semibold text-slate-500 group-hover:text-white">Voir le détail →</span></a>
        @endforeach
    </div>
</section>
@include('marketing.partials.cta')
@endsection
