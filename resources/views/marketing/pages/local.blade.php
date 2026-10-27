@extends('marketing.layout')
@php $label = lcfirst($cfg['label']); $hero = $cfg['stock'][0] ?? '/showcase/restaurant.webp'; $base = 'https://'.config('marketing.host', 'joow.fr'); @endphp
@push('jsonld')<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $base.'/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Métiers', 'item' => $base.route('marketing.sectors', [], false)],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $cfg['label'], 'item' => $base.route('marketing.sector', $sector, false)],
        ['@type' => 'ListItem', 'position' => 4, 'name' => $city['name'], 'item' => $base.route('marketing.local', [$sector, $city['slug']], false)],
    ]],
    ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faq)],
    ['@type' => 'Service', 'name' => 'Création de site internet pour '.$noun['sing'].' '.$city['loc'], 'provider' => ['@type' => 'Organization', 'name' => 'Joow', 'url' => $base.'/'], 'areaServed' => ['@type' => 'City', 'name' => $city['name']], 'serviceType' => 'Création de site internet', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR', 'description' => 'Génération et aperçu gratuits']],
]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>@endpush
@section('content')
<section class="relative overflow-hidden pt-28 sm:pt-36">
    <div class="aurora"></div><div class="grain"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-16 lg:grid-cols-[1.05fr_1fr]">
        <div>
            <nav class="mb-4 text-xs text-slate-500" aria-label="Fil d'Ariane"><a href="{{ route('marketing.home') }}" class="hover:text-white">Accueil</a><span class="mx-1.5">/</span><a href="{{ route('marketing.sectors') }}" class="hover:text-white">Métiers</a><span class="mx-1.5">/</span><a href="{{ route('marketing.sector', $sector) }}" class="hover:text-white">{{ $cfg['label'] }}</a><span class="mx-1.5">/</span><a href="{{ route('marketing.city', $city['slug']) }}" class="text-slate-300 hover:text-white">{{ $city['name'] }}</a></nav>
            <p class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400"><i class="fa-solid {{ $cfg['icon'] }}"></i> {{ $cfg['label'] }} · {{ $city['name'] }} ({{ $city['dept'] }})</p>
            <h1 class="mt-5 font-display text-[2.4rem] font-bold leading-[1.05] text-white sm:text-5xl lg:text-6xl">{{ $h1 }}<span class="block text-shimmer">en 90 secondes, par IA</span></h1>
            <p class="mt-5 max-w-xl text-lg text-slate-400">{{ $intro }}</p>
            <div id="generateur" class="mt-7 scroll-mt-28">@include('marketing.partials.generator')</div>
        </div>
        <div class="relative mx-auto w-full max-w-[560px] lg:max-w-none">
            <div class="tilt overflow-hidden rounded-3xl border border-white/10 shadow-2xl" style="--c: {{ $cfg['color'] }}">
                <div class="relative aspect-[4/3]"><img src="{{ $hero }}" alt="Site internet pour {{ $noun['sing'] }} {{ $city['loc'] }}" class="h-full w-full object-cover" loading="eager" width="1200" height="900"><div class="absolute inset-0 bg-gradient-to-t from-ink-950 via-ink-950/20 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6"><p class="text-[11px] font-bold uppercase tracking-[0.25em]" style="color: {{ $cfg['color'] }}">{{ $cfg['label'] }} · {{ $city['name'] }}</p><p class="mt-1 font-display text-2xl font-bold text-white">Votre nom ici, {{ $label }} {{ $city['loc'] }}</p><span class="mt-3 inline-block rounded-xl px-4 py-2 text-sm font-semibold text-white" style="background: {{ $cfg['color'] }}">{{ $cfg['cta'] }} →</span></div>
                </div>
            </div>
            <div class="absolute -right-3 -top-4 hidden rounded-2xl border border-white/10 bg-ink-900/90 px-4 py-3 text-xs shadow-xl backdrop-blur sm:block"><p class="text-amber-400">★★★★★ <span class="font-semibold text-white">4,8/5</span></p><p class="text-slate-400">Vos avis Google, intégrés</p></div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-16">
    <div class="mx-auto max-w-2xl text-center"><p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Pourquoi {{ $city['loc'] }}</p><h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Un site de {{ $label }} pensé pour les {{ $city['gentile'] }}</h2></div>
    <div class="stagger mt-12 grid gap-5 md:grid-cols-2">
        @foreach($args as $a)<div class="reveal glass rounded-3xl p-7"><h3 class="font-display text-lg font-bold text-white">{{ $a['title'] }}</h3><p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $a['text'] }}</p></div>@endforeach
    </div>
</section>

<section class="mx-auto grid max-w-7xl items-start gap-10 px-5 py-12 lg:grid-cols-3">
    <div class="reveal glass rounded-3xl p-7 lg:col-span-2"><p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Votre zone d'intervention</p><h2 class="mt-3 font-display text-2xl font-bold text-white">{{ $city['name'] }}, ses quartiers et ses environs</h2><p class="mt-3 leading-relaxed text-slate-400">{{ $zone }}</p>
        <div class="mt-5 flex flex-wrap gap-2">@foreach($city['quartiers'] as $q)<span class="rounded-full border border-white/10 px-3 py-1 text-xs text-slate-300">{{ $q }}</span>@endforeach @foreach($city['voisines'] as $q)<span class="rounded-full border border-brand-400/30 px-3 py-1 text-xs text-slate-300">{{ $q }}</span>@endforeach</div>
        <p class="mt-4 text-xs text-slate-500">La liste exacte de vos communes se modifie en quelques clics dans le Studio.</p></div>
    <div class="reveal glass rounded-3xl p-7"><p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Ce que l'on cherche</p><h2 class="mt-3 font-display text-2xl font-bold text-white">Requêtes locales visées</h2><ul class="mt-4 space-y-2 text-sm text-slate-300">@foreach($searches as $s)<li class="flex gap-2"><span class="text-brand-400">🔍</span><span>{{ $s }}</span></li>@endforeach</ul><p class="mt-4 text-xs text-slate-500">Titres, premières phrases et section zone sont rédigés autour de votre métier et de {{ $city['name'] }}.</p></div>
</section>

<section class="mx-auto max-w-7xl px-5 py-16">
    <div class="mx-auto max-w-2xl text-center"><p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Ce que votre site contient</p><h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-4xl">Le modèle {{ $cfg['label'] }}, appliqué {{ $city['loc'] }}</h2><p class="reveal mt-4 text-slate-400">{{ $family['pitch'] ?? '' }}</p></div>
    <div class="stagger mt-12 grid gap-5 md:grid-cols-3">
        <div class="reveal glass rounded-3xl p-7"><span class="text-2xl">🧾</span><h3 class="mt-4 font-display text-lg font-bold text-white">Vos prestations, rédigées</h3>@if(count($services))<ul class="mt-2 space-y-1 text-sm text-slate-400">@foreach($services as $s)<li>· {{ ucfirst($s) }}</li>@endforeach</ul>@else<p class="mt-2 text-sm text-slate-400">Reprises de votre fiche Google ou de votre site, complétées par l'IA selon votre métier.</p>@endif</div>
        <div class="reveal glass rounded-3xl p-7"><span class="text-2xl">⚡</span><h3 class="mt-4 font-display text-lg font-bold text-white">« {{ $cfg['cta'] }} », activé d'office</h3><p class="mt-2 text-sm text-slate-400">Le module qui correspond à votre métier est en place dès la génération, avec confirmations, rappels et tableau de suivi dans votre espace.</p></div>
        <div class="reveal glass rounded-3xl p-7"><span class="text-2xl">⭐</span><h3 class="mt-4 font-display text-lg font-bold text-white">Avis Google et informations à jour</h3><p class="mt-2 text-sm text-slate-400">Note, derniers avis, horaires, adresse et téléphone : identiques à votre fiche Google, mis à jour automatiquement.</p></div>
    </div>
</section>

<section class="mx-auto max-w-3xl px-5 py-16">
    <h2 class="reveal text-center font-display text-3xl font-bold text-white">Questions des {{ $noun['plur'] }} {{ $city['loc'] }}</h2>
    <div class="mt-8 space-y-3">@foreach($faq as $f)<details class="reveal glass group rounded-2xl p-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-white"><span>{{ $f['q'] }}</span><span class="text-brand-400 transition group-open:rotate-45">+</span></summary><p class="mt-3 text-sm leading-relaxed text-slate-400">{{ $f['a'] }}</p></details>@endforeach</div>
</section>

@if(count($posts))
<section class="mx-auto max-w-7xl px-5 py-12"><div class="flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Conseils</p><h2 class="mt-2 font-display text-3xl font-bold text-white">Articles pour les {{ $noun['plur'] }}</h2></div><a href="{{ route('marketing.blog') }}" class="hidden text-sm font-semibold text-brand-400 hover:text-white sm:block">Tout le blog →</a></div><div class="stagger mt-8 grid gap-5 md:grid-cols-3">@foreach($posts as $p)@include('marketing.partials.post-card', ['p' => $p])@endforeach</div></section>
@endif

<section class="mx-auto max-w-7xl px-5 py-10">
    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Autres métiers {{ $city['loc'] }}</p>
    <div class="mt-4 flex flex-wrap gap-2">@foreach($others as $o)<a href="{{ route('marketing.local', [$o['key'], $city['slug']]) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 hover:border-brand-400/60 hover:text-white">{{ $o['label'] }}</a>@endforeach</div>
    <p class="mt-8 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">{{ $cfg['label'] }} dans d'autres villes</p>
    <div class="mt-4 flex flex-wrap gap-2">@foreach($nearby as $n)<a href="{{ route('marketing.local', [$sector, $n['slug']]) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 hover:border-brand-400/60 hover:text-white">{{ $n['name'] }}</a>@endforeach <a href="{{ route('marketing.sector', $sector) }}" class="rounded-full border border-brand-400/40 px-4 py-2 text-sm font-semibold text-brand-400 hover:text-white">Toutes les villes →</a></div>
</section>
@include('marketing.partials.cta', ['title' => 'Votre site de '.$label.' '.$city['loc'].', maintenant.', 'text' => $closing])
@endsection
