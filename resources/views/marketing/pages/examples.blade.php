@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Exemples', 'heading' => 'Des sites générés, pas des maquettes', 'lead' => 'Chaque exemple a été produit par Joow à partir d\'une fiche Google : textes, photos, couleurs et modules sont ceux du métier. Les noms ont été modifiés.'])
<section class="mx-auto max-w-7xl px-5 pb-10">
    <div class="space-y-16">
        @foreach($examples as $i => $e)@php $c = $sectors[$e['sector']]; @endphp
        <article class="reveal grid items-center gap-8 lg:grid-cols-[1.4fr_1fr] {{ $i % 2 ? 'lg:[&>*:first-child]:order-2' : '' }}">
            <div class="relative"><div class="tilt browser"><div class="browser-bar"><i class="bg-rose-400/70"></i><i class="bg-amber-400/70"></i><i class="bg-emerald-400/70"></i><span class="ml-2 rounded-md bg-white/5 px-2 py-0.5 text-[11px] text-slate-400">{{ \Illuminate\Support\Str::slug($e['name']) }}.joow.fr</span></div><img src="{{ $e['full'] }}" alt="Site {{ $e['name'] }}" class="w-full" width="1280" height="1600" loading="lazy"></div><div class="tilt phone absolute -bottom-6 -right-2 w-[26%] max-w-[150px]"><img src="{{ $e['mobile'] }}" alt="Version mobile" class="w-full" width="390" height="844" loading="lazy"></div></div>
            <div><p class="text-xs font-bold uppercase tracking-[0.3em]" style="color: {{ $c['color'] }}">{{ $c['label'] }} · {{ $e['city'] }}</p><h2 class="mt-3 font-display text-3xl font-bold text-white">{{ $e['name'] }}</h2><p class="mt-3 text-slate-400">Modèle {{ $c['label'] }} : typographie {{ $c['font'] }}, thème {{ $c['theme'] === 'dark' ? 'sombre' : 'clair' }}, bouton « {{ $c['cta'] }} », avis Google en haut de page, formulaire en trois étapes adapté au métier.</p><a href="{{ route('marketing.sector', $e['sector']) }}" class="mt-5 inline-flex font-semibold text-brand-400 hover:text-white">Un site pour {{ lcfirst($c['label']) }} →</a></div>
        </article>
        @endforeach
    </div>
</section>
@include('marketing.partials.cta', ['title' => 'Et le vôtre ?', 'text' => 'Le meilleur exemple, c\'est votre propre site. Tapez votre nom, il est prêt dans 90 secondes.'])
@endsection
