@extends('marketing.layout')
@push('jsonld')<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => 'https://'.config('marketing.host').'/'], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fonctionnalités', 'item' => 'https://'.config('marketing.host').route('marketing.features', [], false)], ['@type' => 'ListItem', 'position' => 3, 'name' => $f['title']]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>@endpush
@section('content')
@include('marketing.partials.page-head', ['crumbs' => [['Accueil', route('marketing.home')], ['Fonctionnalités', route('marketing.features')], [$f['title'], null]], 'kicker' => $f['icon'].' Fonctionnalité', 'heading' => e($f['title']), 'lead' => $f['tagline']])
<section class="mx-auto grid max-w-6xl items-start gap-12 px-5 py-10 lg:grid-cols-[1fr_1.1fr]">
    <div>
        <p class="reveal text-lg leading-relaxed text-slate-300">{{ $f['summary'] }}</p>
        <ul class="stagger mt-8 space-y-3">@foreach($f['bullets'] as $b)<li class="reveal flex items-start gap-3 text-slate-200"><span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-xs text-emerald-300">✓</span>{{ $b }}</li>@endforeach</ul>
        <div class="reveal mt-8 flex flex-wrap gap-3"><a href="#generateur" class="btn-brand">Essayer gratuitement</a><a href="{{ route('marketing.pricing') }}" class="inline-flex items-center rounded-xl border border-white/15 px-5 py-3 font-semibold text-white hover:bg-white/5">Voir les tarifs</a></div>
    </div>
    <div class="reveal tilt browser"><div class="browser-bar"><i class="bg-rose-400/70"></i><i class="bg-amber-400/70"></i><i class="bg-emerald-400/70"></i></div><img src="{{ $f['image'] }}" alt="{{ $f['title'] }}" class="w-full" width="1280" height="800" loading="lazy"></div>
</section>
<section class="mx-auto max-w-6xl px-5 py-12">
    <h2 class="font-display text-2xl font-bold text-white">Voir aussi</h2>
    <div class="stagger mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($others as $k => $o)<a href="{{ route('marketing.feature', $k) }}" class="reveal glass glass-hover rounded-2xl p-5"><span class="text-2xl">{{ $o['icon'] }}</span><p class="mt-3 font-semibold text-white">{{ $o['title'] }}</p></a>@endforeach</div>
</section>
@include('marketing.partials.cta')
@endsection
