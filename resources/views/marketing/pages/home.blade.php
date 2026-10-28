@extends('marketing.layout')
@push('jsonld')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Joow', 'url' => 'https://'.config('marketing.host')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'Joow', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => $seo['description'], 'offers' => [['@type' => 'Offer', 'price' => '39', 'priceCurrency' => 'EUR', 'name' => 'Pro, par mois'], ['@type' => 'Offer', 'price' => '349', 'priceCurrency' => 'EUR', 'name' => 'Liberté, une fois']], 'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => $stats['rating'], 'ratingCount' => max(50, (int) ($stats['sites'] / 10))]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faq)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')

{{-- ═══ Hero ═══ --}}
<section class="relative overflow-hidden pt-28 sm:pt-36">
    <div class="aurora"></div><div class="grain"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-16 lg:grid-cols-[1.05fr_1fr] lg:pb-24">
        <div class="text-center lg:text-left">
            <p class="reveal on inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400 backdrop-blur"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span></span> IA + votre fiche Google · {{ $stats['sectors'] }} métiers</p>
            <h1 class="mt-5 font-display text-[2.6rem] font-bold leading-[1.02] text-white sm:text-6xl xl:text-7xl">Le site pro<br>de votre métier,<br><span class="text-shimmer">en 90 secondes.</span></h1>
            <p class="mx-auto mt-5 max-w-xl text-base text-slate-400 sm:text-lg lg:mx-0">Tapez le nom de votre établissement. Joow rédige, choisit vos photos, intègre vos avis Google et active réservation, devis et assistant IA. Vous ne payez que pour le mettre en ligne.</p>
            <div id="generateur" class="mt-7 scroll-mt-28">@include('marketing.partials.generator')</div>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-slate-400 lg:justify-start">
                <span class="flex items-center gap-2"><span class="flex -space-x-2"><span class="grid h-7 w-7 place-items-center rounded-full border-2 border-ink-950 bg-brand-gradient text-[10px] font-bold text-white">S</span><span class="grid h-7 w-7 place-items-center rounded-full border-2 border-ink-950 bg-emerald-500 text-[10px] font-bold text-white">K</span><span class="grid h-7 w-7 place-items-center rounded-full border-2 border-ink-950 bg-amber-500 text-[10px] font-bold text-white">L</span></span><strong class="text-white"><span data-count="{{ $stats['sites'] }}">{{ number_format($stats['sites'], 0, ',', ' ') }}</span>+</strong> sites créés</span>
                <span class="flex items-center gap-1.5"><span class="text-amber-400">★★★★★</span> <strong class="text-white">{{ $stats['rating'] }}</strong>/5 <span class="text-slate-500">note moyenne des établissements</span></span>
            </div>
        </div>
        <div class="relative mx-auto w-full max-w-[640px] lg:max-w-none">
            <div class="tilt browser"><div class="browser-bar"><i class="bg-rose-400/70"></i><i class="bg-amber-400/70"></i><i class="bg-emerald-400/70"></i><span class="ml-2 rounded-md bg-white/5 px-2 py-0.5 text-[11px] text-slate-400">latabledemile.joow.fr</span><span class="ml-auto rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-300">Généré en 84 s</span></div><img src="/showcase/restaurant.webp" alt="Site de restaurant généré par Joow" class="w-full" width="1280" height="800" loading="eager" fetchpriority="high"></div>
            <div class="tilt phone absolute -bottom-8 -left-4 w-[32%] max-w-[180px] sm:-left-8"><img src="/showcase/beaute-mobile.webp" alt="Version mobile d'un site d'institut de beauté" class="w-full" width="390" height="844" loading="lazy"></div>
            <div class="absolute -right-3 top-6 hidden rounded-2xl border border-white/10 bg-ink-900/90 px-4 py-3 text-xs shadow-xl backdrop-blur sm:block"><p class="font-semibold text-white">✓ Nouvelle réservation</p><p class="text-slate-400">Ce soir · 20h30 · 4 couverts</p></div>
        </div>
    </div>
</section>

{{-- ═══ Bandeau métiers ═══ --}}
<section class="relative border-y border-white/[0.06] bg-ink-900/40 py-5">
    <p class="mb-3 text-center text-[11px] font-bold uppercase tracking-[0.3em] text-slate-500">{{ $stats['sectors'] }} métiers, chacun son modèle</p>
    <div class="overflow-hidden"><div class="marquee">@foreach(array_merge($sectors, $sectors) as $s)<a href="{{ route('marketing.sector', $s['key']) }}" class="flex shrink-0 items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-4 py-2 text-sm text-slate-300 transition hover:border-brand-400/60 hover:text-white">{{ $s['label'] }}</a>@endforeach</div></div>
</section>

{{-- ═══ Comment ça marche ═══ --}}
<section class="mx-auto max-w-7xl px-5 py-24">
    <div class="mx-auto max-w-2xl text-center">
        <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Comment ça marche</p>
        <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Pas de brief. Pas de graphiste. Pas d'attente.</h2>
        <p class="reveal mt-4 text-lg text-slate-400">Trois points de départ, un même résultat : un site complet, qui vous ressemble, prêt à encaisser des demandes.</p>
    </div>
    <div class="stagger mt-14 grid gap-5 md:grid-cols-3">
        @foreach([['1', 'Vous le trouvez', 'Votre fiche Google, l\'adresse de votre site actuel, ou cinq questions si vous n\'avez ni l\'un ni l\'autre.', '🔎'], ['2', 'L\'IA construit', 'Métier détecté, textes rédigés à partir de vos avis, photos analysées une par une, couleurs de votre marque, modules activés.', '✨'], ['3', 'Vous ajustez, vous publiez', 'Un clic sur un texte ou une photo pour le changer. Un mot à l\'assistant pour le reste. En ligne quand c\'est parfait.', '🚀']] as [$n, $t, $d, $i])
        <div class="reveal glass glass-hover rounded-3xl p-7"><div class="flex items-center justify-between"><span class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-gradient text-xl">{{ $i }}</span><span class="font-display text-4xl font-bold text-white/10">{{ $n }}</span></div><h3 class="mt-5 font-display text-xl font-bold text-white">{{ $t }}</h3><p class="mt-2 text-slate-400">{{ $d }}</p></div>
        @endforeach
    </div>
</section>

{{-- ═══ Modules (bento) ═══ --}}
<section class="relative overflow-hidden py-24">
    <div class="aurora opacity-50"></div>
    <div class="relative mx-auto max-w-7xl px-5">
        <div class="mx-auto max-w-2xl text-center">
            <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Modules inclus</p>
            <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Un site qui travaille pour vous, même quand vous dormez</h2>
            <p class="reveal mt-4 text-lg text-slate-400">Réservation, devis, assistant IA, chatbot, avis Google, nom de domaine. Tout est inclus, tout s'active en un clic.</p>
        </div>
        <div class="stagger mt-14 grid gap-4 md:grid-cols-6">
            @php $bento = ['reservation' => 'md:col-span-3', 'devis' => 'md:col-span-3', 'assistant-ia' => 'md:col-span-2', 'chatbot' => 'md:col-span-2', 'avis-google' => 'md:col-span-2', 'domaine' => 'md:col-span-2', 'seo-local' => 'md:col-span-2', 'mobile' => 'md:col-span-2']; @endphp
            @foreach($bento as $k => $span)@php $f = $features[$k]; @endphp
            <a href="{{ route('marketing.feature', $k) }}" class="reveal glass glass-hover group rounded-3xl p-6 {{ $span }}"><span class="text-2xl">{{ $f['icon'] }}</span><h3 class="mt-4 font-display text-lg font-bold text-white group-hover:text-brand-400">{{ $f['title'] }}</h3><p class="mt-2 text-sm text-slate-400">{{ $f['tagline'] }}</p><span class="mt-4 inline-block text-xs font-semibold text-slate-500 group-hover:text-white">En savoir plus →</span></a>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ Exemples ═══ --}}
<section class="mx-auto max-w-7xl px-5 py-24">
    <div class="mx-auto max-w-2xl text-center">
        <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Exemples</p>
        <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Des sites qui donnent envie de cliquer</h2>
        <p class="reveal mt-4 text-lg text-slate-400">Chaque métier a son modèle : typographie, couleurs, sections et modules pensés pour ce que cherchent ses clients.</p>
    </div>
    <div class="stagger mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach(array_slice($examples, 0, 6) as $e)
        <a href="{{ route('marketing.sector', $e['sector']) }}" class="reveal group block"><div class="tilt browser"><div class="browser-bar"><i class="bg-rose-400/70"></i><i class="bg-amber-400/70"></i><i class="bg-emerald-400/70"></i><span class="ml-2 truncate rounded-md bg-white/5 px-2 py-0.5 text-[11px] text-slate-400">{{ \Illuminate\Support\Str::slug($e['name']) }}.joow.fr</span></div><div class="relative aspect-[16/10] overflow-hidden"><img src="{{ $e['img'] }}" alt="Site {{ $e['name'] }}" class="h-full w-full object-cover object-top transition duration-700 group-hover:scale-[1.03]" width="1280" height="800" loading="lazy"></div></div><p class="mt-3 font-semibold text-white">{{ $e['name'] }} <span class="font-normal text-slate-500">· {{ config("sectors.{$e['sector']}.label") }}, {{ $e['city'] }}</span></p></a>
        @endforeach
    </div>
    <p class="mt-8 text-center"><a href="{{ route('marketing.examples') }}" class="font-semibold text-brand-400 hover:text-white">Voir tous les exemples →</a></p>
</section>

{{-- ═══ Studio ═══ --}}
<section class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-24 lg:grid-cols-2">
    <div>
        <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Le Studio</p>
        <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Modifiez tout en 2 clics.<br>Ou demandez à l'IA.</h2>
        <p class="reveal mt-4 text-lg text-slate-400">Cliquez un texte, il s'édite. Cliquez une photo, vous la remplacez. Et pour le reste : « ajoute une page Tarifs », « rends le ton plus chaleureux », l'assistant le fait, et vous pouvez annuler.</p>
        <ul class="stagger mt-6 space-y-3">
            @foreach(['Édition directe sur la page, sur mobile comme sur ordinateur', 'Pages supplémentaires en une phrase : tarifs, réalisations, équipe', 'Modules activés en un clic : réservation, devis, chatbot, avis', 'Chaque modification annulable, aperçu identique au site publié'] as $b)<li class="reveal flex items-start gap-3 text-slate-300"><span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-xs text-emerald-300">✓</span>{{ $b }}</li>@endforeach
        </ul>
        <a href="{{ route('marketing.feature', 'studio') }}" class="reveal mt-7 inline-flex font-semibold text-brand-400 hover:text-white">Découvrir le Studio →</a>
    </div>
    <div class="reveal tilt browser"><div class="browser-bar"><i class="bg-rose-400/70"></i><i class="bg-amber-400/70"></i><i class="bg-emerald-400/70"></i><span class="ml-2 rounded-md bg-white/5 px-2 py-0.5 text-[11px] text-slate-400">app.joow.fr · Studio</span></div><img src="/showcase/studio.webp" alt="Le Studio Joow : édition directe du site" class="w-full" width="1280" height="800" loading="lazy"></div>
</section>

{{-- ═══ Refonte ═══ --}}
<section class="relative overflow-hidden py-24">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 lg:grid-cols-2">
        <div class="reveal compare aspect-[16/11] border border-white/10">
            <img src="/showcase/renovation-full.webp" alt="Avant : ancien site" class="grayscale contrast-75 brightness-75 saturate-50" width="1280" height="900" loading="lazy">
            <div class="compare-after"><img src="/showcase/renovation.webp" alt="Après : nouveau site Joow" width="1280" height="900" loading="lazy"></div>
            <div class="compare-line"></div>
            <span class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">Avant</span><span class="pointer-events-none absolute right-4 top-4 rounded-full bg-brand-gradient px-3 py-1 text-xs font-semibold text-white">Après</span>
            <input type="range" min="5" max="95" value="50" aria-label="Comparer avant et après">
        </div>
        <div>
            <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Vous avez déjà un site ?</p>
            <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Collez son adresse. On garde le fond, on refait la forme.</h2>
            <p class="reveal mt-4 text-lg text-slate-400">L'IA lit vos pages, conserve vos textes, vos prix, vos photos et votre logo, retrouve votre fiche Google pour y ajouter vos avis, et reconstruit un site moderne avec ses sous-pages. Vous comparez avant / après, puis vous décidez.</p>
            <a href="{{ route('marketing.feature', 'refonte') }}" class="reveal mt-7 inline-flex font-semibold text-brand-400 hover:text-white">Comment fonctionne la refonte →</a>
        </div>
    </div>
</section>

{{-- ═══ Comparatif ═══ --}}
<section class="mx-auto max-w-5xl px-5 py-24">
    <div class="mx-auto max-w-2xl text-center"><p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Comparez</p><h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Le résultat d'une agence. Sans le prix, sans l'attente.</h2></div>
    <div class="reveal mt-12 overflow-hidden rounded-3xl border border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-left text-xs uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-4"></th><th class="px-5 py-4 text-white">Joow</th><th class="px-5 py-4">Agence</th><th class="px-5 py-4">Éditeur en ligne</th></tr></thead>
            <tbody class="divide-y divide-white/[0.06]">
                @foreach([['Délai', '90 secondes', '4 à 8 semaines', 'Plusieurs soirées'], ['Prix', '39 € HT/mois ou 349 € une fois', '1 500 à 5 000 €', '20 à 40 €/mois + votre temps'], ['Textes et photos', 'Rédigés et choisis par l\'IA', 'À fournir', 'À fournir'], ['Avis Google', 'Intégrés automatiquement', 'Sur devis', 'Extension payante'], ['Réservation, devis, chatbot', 'Inclus', 'Sur devis', 'Extensions payantes'], ['Modifications', 'Vous, en 2 clics', 'Par ticket', 'Vous, avec de la patience']] as [$k, $j, $a, $w])
                <tr><td class="px-5 py-4 font-semibold text-white">{{ $k }}</td><td class="px-5 py-4 font-semibold text-emerald-300">{{ $j }}</td><td class="px-5 py-4 text-slate-400">{{ $a }}</td><td class="px-5 py-4 text-slate-400">{{ $w }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

{{-- ═══ Témoignages ═══ --}}
<section class="mx-auto max-w-6xl px-5 py-20">
    <h2 class="reveal text-center font-display text-3xl font-bold text-white sm:text-5xl">Ils ont créé leur site en moins de 2 minutes</h2>
    <div class="stagger mt-12 grid gap-5 md:grid-cols-3">
        @foreach($testimonials as $t)<figure class="reveal glass rounded-3xl p-6"><p class="text-amber-400">★★★★★</p><blockquote class="mt-3 text-slate-200">« {{ $t['q'] }} »</blockquote><figcaption class="mt-5 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-brand-gradient font-bold text-white">{{ mb_substr($t['a'], 0, 1) }}</span><span><span class="block font-semibold text-white">{{ $t['a'] }}</span><span class="block text-xs text-slate-500">{{ $t['r'] }}</span></span></figcaption></figure>@endforeach
    </div>
</section>

{{-- ═══ Tarifs ═══ --}}
<section class="mx-auto max-w-5xl px-5 py-20">
    <div class="mx-auto max-w-2xl text-center"><p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Tarifs</p><h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Deux formules, tous les modules inclus</h2><p class="reveal mt-4 text-lg text-slate-400">Création et aperçu gratuits, sans carte bancaire. Vous ne payez que pour mettre en ligne.</p></div>
    @include('marketing.partials.pricing', ['pricing' => $pricing])
</section>

{{-- ═══ FAQ ═══ --}}
<section class="mx-auto max-w-3xl px-5 py-20">
    <h2 class="reveal text-center font-display text-3xl font-bold text-white sm:text-5xl">Questions fréquentes</h2>
    <div class="mt-10 space-y-3">@foreach($faq as $f)<details class="reveal glass group rounded-2xl p-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-white"><span>{{ $f['q'] }}</span><span class="text-slate-500 transition group-open:rotate-45">+</span></summary><p class="mt-3 text-slate-400">{{ $f['a'] }}</p></details>@endforeach</div>
    <p class="mt-6 text-center"><a href="{{ route('marketing.faq') }}" class="font-semibold text-brand-400 hover:text-white">Toutes les questions →</a></p>
</section>

{{-- ═══ Blog ═══ --}}
@if(count($posts))
<section class="mx-auto max-w-7xl px-5 py-20">
    <div class="flex items-end justify-between gap-4"><div><p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Conseils</p><h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-4xl">Être trouvé, inspirer confiance, être choisi</h2></div><a href="{{ route('marketing.blog') }}" class="hidden font-semibold text-brand-400 hover:text-white sm:inline">Tout le blog →</a></div>
    <div class="stagger mt-10 grid gap-5 md:grid-cols-3">@foreach($posts as $p)@include('marketing.partials.post-card', ['p' => $p])@endforeach</div>
</section>
@endif

@include('marketing.partials.cta')
@endsection
