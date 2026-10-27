@extends('marketing.layout')
@push('jsonld')<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => 'https://'.config('marketing.host').'/'], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Métiers', 'item' => 'https://'.config('marketing.host').route('marketing.sectors', [], false)], ['@type' => 'ListItem', 'position' => 3, 'name' => $cfg['label']]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>@endpush
@section('content')
@php $label = lcfirst($cfg['label']); $hero = $cfg['stock'][0] ?? '/showcase/restaurant.webp'; @endphp
<section class="relative overflow-hidden pt-28 sm:pt-36">
    <div class="aurora"></div><div class="grain"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-16 lg:grid-cols-[1.05fr_1fr]">
        <div>
            <nav class="mb-4 text-xs text-slate-500" aria-label="Fil d'Ariane"><a href="{{ route('marketing.home') }}" class="hover:text-white">Accueil</a><span class="mx-1.5">/</span><a href="{{ route('marketing.sectors') }}" class="hover:text-white">Métiers</a><span class="mx-1.5">/</span><span class="text-slate-300">{{ $cfg['label'] }}</span></nav>
            <p class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400"><i class="fa-solid {{ $cfg['icon'] }}"></i> Modèle {{ $cfg['label'] }}</p>
            <h1 class="mt-5 font-display text-[2.5rem] font-bold leading-[1.03] text-white sm:text-6xl">Site internet pour {{ $label }} : <span class="text-shimmer">prêt en 90 secondes</span></h1>
            <p class="mt-5 max-w-xl text-lg text-slate-400">{{ $family['pitch'] }}</p>
            <div id="generateur" class="mt-7 scroll-mt-28">@include('marketing.partials.generator')</div>
        </div>
        <div class="relative mx-auto w-full max-w-[560px] lg:max-w-none">
            <div class="tilt overflow-hidden rounded-3xl border border-white/10 shadow-2xl" style="--c: {{ $cfg['color'] }}">
                <div class="relative aspect-[4/3]"><img src="{{ $hero }}" alt="{{ $cfg['label'] }}" class="h-full w-full object-cover" loading="eager" width="1200" height="900"><div class="absolute inset-0 bg-gradient-to-t from-ink-950 via-ink-950/30 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6"><p class="text-[11px] font-bold uppercase tracking-[0.25em]" style="color: {{ $cfg['color'] }}">{{ $cfg['label'] }}</p><p class="mt-1 font-display text-2xl font-bold text-white" style="font-family: '{{ $cfg['font'] }}', Space Grotesk, sans-serif">Votre nom ici, {{ $label }} à votre ville</p><span class="mt-3 inline-flex rounded-xl px-4 py-2 text-sm font-bold text-white" style="background: {{ $cfg['color'] }}">{{ $cfg['cta'] }} →</span></div>
                </div>
            </div>
            <div class="absolute -right-3 -top-4 hidden rounded-2xl border border-white/10 bg-ink-900/90 px-4 py-3 text-xs shadow-xl backdrop-blur sm:block"><p class="text-amber-400">★★★★★ <span class="font-semibold text-white">4,8/5</span></p><p class="text-slate-400">Vos avis Google, intégrés</p></div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-20">
    <div class="mx-auto max-w-2xl text-center"><p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Ce que votre site contient</p><h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Pensé pour {{ $label }}, pas pour « une entreprise »</h2><p class="reveal mt-4 text-lg text-slate-400">Le modèle {{ $cfg['label'] }} privilégie {{ $family['proof'] }}. Palette « {{ $cfg['label'] }} », typographie {{ $cfg['font'] }}, thème {{ $cfg['theme'] === 'dark' ? 'sombre' : 'clair' }}.</p></div>
    <div class="stagger mt-12 grid gap-5 md:grid-cols-3">
        <div class="reveal glass rounded-3xl p-7"><span class="text-2xl">🧭</span><h3 class="mt-4 font-display text-lg font-bold text-white">Les bonnes sections, dans le bon ordre</h3><p class="mt-2 text-sm text-slate-400">@foreach(array_slice($cfg['order'], 0, 6) as $i => $sec){{ $i ? ' → ' : '' }}{{ ['services' => 'Prestations', 'process' => 'Parcours', 'about' => 'À propos', 'gallery' => 'Réalisations / galerie', 'reviews' => 'Avis Google', 'faq' => 'FAQ', 'booking' => $cfg['cta'], 'cta' => 'Appel à l\'action', 'contact' => 'Contact & horaires', 'menu' => 'Carte', 'rooms' => 'Chambres'][$sec] ?? $sec }}@endforeach</p></div>
        <div class="reveal glass rounded-3xl p-7"><span class="text-2xl">🧾</span><h3 class="mt-4 font-display text-lg font-bold text-white">Des prestations qui parlent à vos clients</h3>@if(count($services))<ul class="mt-2 space-y-1 text-sm text-slate-400">@foreach($services as $s)<li>• {{ ucfirst($s) }}</li>@endforeach</ul>@else<p class="mt-2 text-sm text-slate-400">Rédigées à partir de vos avis et de votre fiche Google, avec des prix réalistes pour votre métier et votre ville.</p>@endif</div>
        <div class="reveal glass rounded-3xl p-7"><span class="text-2xl">⚡</span><h3 class="mt-4 font-display text-lg font-bold text-white">Le bon module activé d'office</h3><p class="mt-2 text-sm text-slate-400">« {{ $cfg['cta'] }} » en un tap sur mobile, formulaire en trois étapes pensé pour {{ $label }}, assistant IA qui répond à vos clients, avis Google en vitrine.</p></div>
    </div>
</section>

<section class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 lg:grid-cols-2">
    <div class="reveal tilt browser order-2 lg:order-1"><div class="browser-bar"><i class="bg-rose-400/70"></i><i class="bg-amber-400/70"></i><i class="bg-emerald-400/70"></i><span class="ml-2 rounded-md bg-white/5 px-2 py-0.5 text-[11px] text-slate-400">app.joow.fr · Studio</span></div><img src="/showcase/studio.webp" alt="Le Studio Joow" class="w-full" width="1280" height="800" loading="lazy"></div>
    <div class="order-1 lg:order-2"><p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Comment ça se passe</p><h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-4xl">De votre fiche Google à votre site, en trois étapes</h2>
        <ol class="stagger mt-6 space-y-4">@foreach([['Tapez votre nom', 'Votre fiche Google apparaît. Pas de fiche ? Cinq questions. Déjà un site ? Collez son adresse.'], ['L\'IA construit', 'Métier détecté, textes rédigés à partir de vos avis, photos analysées, couleurs de votre marque.'], ['Vous ajustez et publiez', 'Deux clics par retouche dans le Studio. En ligne quand c\'est parfait, à partir de 39 € HT par mois.']] as $i => [$t, $d])<li class="reveal flex gap-4"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-gradient font-bold text-white">{{ $i + 1 }}</span><span><span class="block font-semibold text-white">{{ $t }}</span><span class="text-sm text-slate-400">{{ $d }}</span></span></li>@endforeach</ol>
    </div>
</section>

<section class="mx-auto max-w-3xl px-5 py-16">
    <h2 class="reveal text-center font-display text-3xl font-bold text-white">Questions fréquentes des {{ $label }}s</h2>
    <div class="mt-8 space-y-3">
        @foreach([['Combien coûte un site pour '.$label.' ?', 'La création et l\'aperçu sont gratuits. Pour le mettre en ligne : 39 € HT par mois sans engagement, ou 349 € HT une fois. Tous les modules sont inclus.'], ['Je n\'ai pas de belles photos, est-ce un problème ?', 'Non. L\'IA analyse celles de votre fiche Google et de votre site, et complète avec des visuels professionnels choisis pour chaque sujet. Vous remplacez ce que vous voulez depuis votre téléphone.'], ['Puis-je modifier les textes moi-même ?', 'Oui, directement sur la page, en cliquant dessus. Ou en demandant à l\'assistant IA : « rends le ton plus chaleureux », « ajoute une page Tarifs ».'], ['Mes clients pourront-ils '.strtolower($cfg['cta']).' en ligne ?', 'Oui, le module est activé d\'office pour votre métier : vous recevez chaque demande par email et dans votre espace, votre client une confirmation.']] as [$q, $a])
        <details class="reveal glass group rounded-2xl p-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-white"><span>{{ $q }}</span><span class="text-slate-500 transition group-open:rotate-45">+</span></summary><p class="mt-3 text-slate-400">{{ $a }}</p></details>
        @endforeach
    </div>
</section>

@if(count($posts))
<section class="mx-auto max-w-7xl px-5 py-16"><div class="flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Conseils</p><h2 class="mt-2 font-display text-3xl font-bold text-white">Articles pour votre métier</h2></div><a href="{{ route('marketing.blog') }}" class="hidden text-sm font-semibold text-brand-400 hover:text-white sm:block">Tout le blog →</a></div><div class="stagger mt-8 grid gap-5 md:grid-cols-3">@foreach($posts as $p)@include('marketing.partials.post-card', ['p' => $p])@endforeach</div></section>
@endif
@if(count($related))
<section class="mx-auto max-w-7xl px-5 py-10"><p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Métiers proches</p><div class="mt-4 flex flex-wrap gap-2">@foreach($related as $r)<a href="{{ route('marketing.sector', $r['key']) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 hover:border-brand-400/60 hover:text-white">{{ $r['label'] }}</a>@endforeach</div></section>
@endif
@include('marketing.partials.cta', ['title' => 'Votre site de '.$label.', maintenant.', 'text' => 'Tapez votre nom, regardez le résultat dans 90 secondes. Gratuit jusqu\'à la mise en ligne.'])
@endsection
