{{-- ═══════════ PAGE ADDITIONNELLE : hero compact + blocs ═══════════
     Variables héritées du template principal ($b, $c, $t, $editMode, $pool, $hero, $lqip, $svcIcon, $ctaHref, $ctaLabel, $phoneHref, $hours, $mapsKey, $stars…)
     $page   : page normalisée (PageSchema)  ·  $pi : index de la page dans site_data.pages
--}}
@php
  $pp = "pages.$pi";
  $pHero = $page['hero'] ?? [];
  $pImg = $pHero['image'] ?? ($pool[1] ?? $hero);
  $pLq  = $lqip($pImg);
  $paras = fn(string $txt) => array_values(array_filter(array_map('trim', preg_split('/\n\s*\n|\r\n\s*\r\n/', $txt))));
  $ytId = function (?string $u): ?string { if (!$u) return null; if (preg_match('~(?:youtu\.be/|v=|/embed/|/shorts/)([A-Za-z0-9_-]{6,})~', $u, $m)) return 'https://www.youtube.com/embed/'.$m[1]; if (preg_match('~vimeo\.com/(\d+)~', $u, $m)) return 'https://player.vimeo.com/video/'.$m[1]; return null; };
@endphp

<header class="relative flex min-h-[62svh] items-center overflow-hidden" data-section="phero" data-label="{{ $page['title'] }}">
  <div class="absolute inset-0 overflow-hidden">
    @if($pLq)<div class="lqip absolute inset-0" style="background-image:url('{{ $pLq }}')"></div>@endif
    <div class="kb absolute inset-0"><img src="{{ $pImg }}" id="heroimg" class="hero-img absolute inset-0 h-[115%] w-full object-cover" alt="{{ $page['title'] }}" fetchpriority="high" decoding="async" onload="this.classList.add('ready')"></div>
  </div>
  <div class="hero-ov absolute inset-0"></div>
  <div class="grain absolute inset-0"></div>
  @if($editMode)<button type="button" class="joow-img-btn" style="right:1.25rem;bottom:1.25rem" data-edit-img="{{ $pp }}.hero.image"><i class="fa-solid fa-image"></i> Changer la photo</button>@endif
  <div class="relative mx-auto w-full max-w-6xl px-5 pb-20 pt-36 text-white">
    <nav class="mb-6 flex items-center gap-2 text-sm text-white/70"><a href="/" class="hover:text-white">Accueil</a><span>/</span><span class="text-white">{{ $page['title'] }}</span></nav>
    <div class="stagger">
      @if(!empty($pHero['tag']) || $editMode)<p class="mb-3 text-sm font-bold uppercase tracking-[0.3em] accent" data-edit="{{ $pp }}.hero.tag">{{ $pHero['tag'] ?? '' }}</p>@endif
      <h1 class="font-display font-bold {{ $serif ? 'text-[2.6rem] leading-[1.02] sm:text-6xl' : 'text-[2.3rem] leading-[1.05] sm:text-5xl' }} max-w-4xl" data-edit="{{ $pp }}.hero.title">{{ $pHero['title'] ?: $page['title'] }}</h1>
      @if(!empty($pHero['subtitle']) || $editMode)<p class="mt-5 max-w-2xl text-lg text-white/80" data-edit="{{ $pp }}.hero.subtitle">{{ $pHero['subtitle'] ?? '' }}</p>@endif
    </div>
  </div>
</header>

<main>
@foreach($page['blocks'] as $bi => $blk)
@php $bp = "$pp.blocks.$bi"; $type = $blk['type']; $items = $blk['items'] ?? []; @endphp

{{-- ── TEXTE & IMAGE ── --}}
@if($type === 'text')
<section class="py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Texte' }}">
  <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 {{ !empty($blk['image']) || $editMode ? 'lg:grid-cols-2' : 'max-w-3xl' }}">
    <div class="reveal {{ ($blk['image_side'] ?? 'right') === 'left' ? 'lg:order-2' : '' }}">
      <h2 class="font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
      <div class="prose-joow mt-6 space-y-4 text-lg leading-relaxed text-slate-600" data-edit="{{ $bp }}.body" data-multiline>@foreach($paras($blk['body'] ?? '') as $para)<p>{{ $para }}</p>@endforeach</div>
    </div>
    @if(!empty($blk['image']) || $editMode)
    <div class="reveal relative {{ ($blk['image_side'] ?? 'right') === 'left' ? 'lg:order-1' : '' }}">
      @if(!empty($blk['image']))
      <img src="{{ $blk['image'] }}" class="aspect-[4/3] w-full rounded-[2rem] object-cover shadow-2xl" alt="{{ $blk['title'] }}" loading="lazy" @if($editMode) data-edit-img="{{ $bp }}.image" @endif>
      @else
      <button type="button" class="joow-img-btn" style="position:static;aspect-ratio:4/3;width:100%;border-radius:2rem;justify-content:center;border:2px dashed #c7d2fe;background:#f5f3ff" data-edit-img="{{ $bp }}.image"><i class="fa-solid fa-image"></i> Ajouter une image</button>
      @endif
    </div>
    @endif
  </div>
</section>

{{-- ── POINTS FORTS ── --}}
@elseif($type === 'features')
<section class="bg-slate-50 py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Points forts' }}">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal max-w-2xl">
      <h2 class="font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
      @if(!empty($blk['intro']) || $editMode)<p class="mt-4 text-lg text-slate-600" data-edit="{{ $bp }}.intro">{{ $blk['intro'] ?? '' }}</p>@endif
    </div>
    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      @foreach($items as $ii => $it)
      <div class="svc tilt reveal">
        <span class="svc-ic"><i class="fa-solid {{ $it['icon'] ?: $svcIcon($it['title']) }}"></i></span>
        <h3 class="mt-5 font-display text-xl font-bold text-slate-900" data-edit="{{ $bp }}.items.{{ $ii }}.title">{{ $it['title'] }}</h3>
        <p class="mt-2 text-slate-600" data-edit="{{ $bp }}.items.{{ $ii }}.desc">{{ $it['desc'] }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ── ÉTAPES ── --}}
@elseif($type === 'steps')
<section class="py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Étapes' }}">
  <div class="mx-auto max-w-6xl px-5 text-center">
    <h2 class="reveal font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
    <div class="steps mt-12" style="grid-template-columns:repeat({{ max(1, min(5, count($items))) }},minmax(0,1fr))">
      @foreach($items as $ii => $it)
      <div class="reveal step"><div class="step-n">{{ $ii + 1 }}</div><h3 class="mt-5 font-display text-lg font-bold text-slate-900" data-edit="{{ $bp }}.items.{{ $ii }}.title">{{ $it['title'] }}</h3><p class="mt-2 text-slate-600" data-edit="{{ $bp }}.items.{{ $ii }}.desc">{{ $it['desc'] }}</p></div>
      @endforeach
    </div>
  </div>
</section>

{{-- ── GALERIE ── --}}
@elseif($type === 'gallery')
@php $gimgs = $blk['images'] ?: array_slice($pool, 2, 6); @endphp
<section class="bg-slate-50 py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Galerie' }}">
  <div class="mx-auto max-w-6xl px-5">
    <h2 class="reveal font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
    <div class="mt-10 grid grid-cols-2 gap-4 md:grid-cols-3">
      @foreach($gimgs as $gi => $g)
      <div class="group aspect-[4/3] overflow-hidden rounded-3xl {{ $gi === 0 ? 'col-span-2 row-span-2 md:aspect-auto' : '' }}"><img src="{{ $g }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-105" alt="{{ $blk['title'] }}" loading="lazy" @if($editMode) data-edit-img="{{ $bp }}.images.{{ $gi }}" @else data-lb="{{ $g }}" @endif></div>
      @endforeach
      @if($editMode)<button type="button" class="joow-img-btn" style="position:static;aspect-ratio:4/3;width:100%;border-radius:1.5rem;justify-content:center;border:2px dashed #c7d2fe;background:#f5f3ff" data-edit-img="{{ $bp }}.images.{{ count($blk['images']) }}"><i class="fa-solid fa-plus"></i> Ajouter</button>@endif
    </div>
  </div>
</section>

{{-- ── FAQ ── --}}
@elseif($type === 'faq')
<section class="py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'FAQ' }}">
  <div class="mx-auto max-w-3xl px-5">
    <h2 class="reveal text-center font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
    <div class="mt-10 space-y-3">
      @foreach($items as $ii => $it)
      <details class="reveal group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm" @if($editMode) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900"><span data-edit="{{ $bp }}.items.{{ $ii }}.q">{{ $it['q'] }}</span><i class="fa-solid fa-chevron-down text-slate-400 transition group-open:rotate-180"></i></summary>
        <p class="mt-3 text-slate-600" data-edit="{{ $bp }}.items.{{ $ii }}.a">{{ $it['a'] }}</p>
      </details>
      @endforeach
    </div>
  </div>
</section>

{{-- ── TARIFS ── --}}
@elseif($type === 'pricing')
<section class="bg-slate-50 py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Tarifs' }}">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mx-auto max-w-2xl text-center">
      <h2 class="font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
      @if(!empty($blk['intro']) || $editMode)<p class="mt-4 text-lg text-slate-600" data-edit="{{ $bp }}.intro">{{ $blk['intro'] ?? '' }}</p>@endif
    </div>
    <div class="mt-12 grid gap-5" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
      @foreach($items as $ii => $it)
      <div class="svc reveal flex flex-col {{ $ii === 1 && count($items) >= 3 ? 'ring-2 ring-[var(--c)]' : '' }}">
        <p class="text-xs font-bold uppercase tracking-[0.2em] accent" data-edit="{{ $bp }}.items.{{ $ii }}.name">{{ $it['name'] }}</p>
        <p class="mt-3 font-display text-4xl font-bold text-slate-900" data-edit="{{ $bp }}.items.{{ $ii }}.price">{{ $it['price'] }}</p>
        <p class="mt-2 text-slate-600" data-edit="{{ $bp }}.items.{{ $ii }}.desc">{{ $it['desc'] }}</p>
        @if(count($it['features']))<ul class="mt-5 space-y-2 text-sm text-slate-700">@foreach($it['features'] as $fi => $f)<li class="flex gap-2"><i class="fa-solid fa-check mt-1 accent"></i><span data-edit="{{ $bp }}.items.{{ $ii }}.features.{{ $fi }}">{{ $f }}</span></li>@endforeach</ul>@endif
        <a href="{{ $ctaHref }}" class="mt-auto pt-6"><span class="block rounded-xl bg-grad px-5 py-3 text-center font-bold text-white shadow-c">{{ $ctaLabel }}</span></a>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ── ÉQUIPE ── --}}
@elseif($type === 'team')
<section class="py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Équipe' }}">
  <div class="mx-auto max-w-6xl px-5">
    <h2 class="reveal text-center font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-{{ max(2, min(4, count($items))) }}">
      @foreach($items as $ii => $it)
      <div class="reveal text-center">
        <div class="mx-auto aspect-square w-40 overflow-hidden rounded-[2rem] bg-slate-100 shadow-lg">
          @if(!empty($it['image']))<img src="{{ $it['image'] }}" class="h-full w-full object-cover" alt="{{ $it['name'] }}" loading="lazy" @if($editMode) data-edit-img="{{ $bp }}.items.{{ $ii }}.image" @endif>
          @else<div class="grid h-full w-full place-items-center bg-grad font-display text-4xl font-bold text-white" @if($editMode) data-edit-img="{{ $bp }}.items.{{ $ii }}.image" style="cursor:pointer" @endif>{{ $initial($it['name']) }}</div>@endif
        </div>
        <h3 class="mt-5 font-display text-xl font-bold text-slate-900" data-edit="{{ $bp }}.items.{{ $ii }}.name">{{ $it['name'] }}</h3>
        <p class="text-sm font-semibold accent" data-edit="{{ $bp }}.items.{{ $ii }}.role">{{ $it['role'] }}</p>
        <p class="mt-2 text-sm text-slate-600" data-edit="{{ $bp }}.items.{{ $ii }}.bio">{{ $it['bio'] }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ── TÉMOIGNAGES ── --}}
@elseif($type === 'testimonials')
<section class="bg-slate-50 py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Témoignages' }}">
  <div class="mx-auto max-w-6xl px-5">
    <h2 class="reveal text-center font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
    <div class="mt-12 grid gap-5 md:grid-cols-{{ max(1, min(3, count($items))) }}">
      @foreach($items as $ii => $it)
      <figure class="rev reveal" style="width:auto"><span class="q">"</span><span class="text-amber-400">★★★★★</span><blockquote class="mt-3 text-slate-700" data-edit="{{ $bp }}.items.{{ $ii }}.text">{{ $it['text'] }}</blockquote><figcaption class="mt-5 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-grad font-display font-bold text-white">{{ $initial($it['author']) }}</span><span><span class="block font-semibold text-slate-900" data-edit="{{ $bp }}.items.{{ $ii }}.author">{{ $it['author'] }}</span><span class="block text-xs text-slate-500" data-edit="{{ $bp }}.items.{{ $ii }}.role">{{ $it['role'] }}</span></span></figcaption></figure>
      @endforeach
    </div>
  </div>
</section>

{{-- ── CHIFFRES ── --}}
@elseif($type === 'stats')
<section class="py-16" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Chiffres clés' }}">
  <div class="mx-auto max-w-6xl px-5">
    @if(!empty($blk['title']) || $editMode)<h2 class="reveal mb-10 text-center font-display text-3xl font-bold text-slate-900" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>@endif
    <div class="grid gap-6 rounded-[2rem] bg-slate-900 px-8 py-10 text-center text-white" style="grid-template-columns:repeat({{ max(1, min(4, count($items))) }},minmax(0,1fr))">
      @foreach($items as $ii => $it)<div class="reveal"><p class="font-display text-4xl font-bold sm:text-5xl" data-count data-edit="{{ $bp }}.items.{{ $ii }}.v">{{ $it['v'] }}</p><p class="mt-2 text-sm text-white/70" data-edit="{{ $bp }}.items.{{ $ii }}.l">{{ $it['l'] }}</p></div>@endforeach
    </div>
  </div>
</section>

{{-- ── CTA ── --}}
@elseif($type === 'cta')
<section class="py-16" data-section="block-{{ $bi }}" data-label="Appel à l'action">
  <div class="mx-auto max-w-6xl px-5">
    <div class="cta-band reveal">
      <h2 class="font-display text-3xl font-bold sm:text-5xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
      @if(!empty($blk['text']) || $editMode)<p class="mx-auto mt-4 max-w-2xl text-lg text-white/80" data-edit="{{ $bp }}.text">{{ $blk['text'] ?? '' }}</p>@endif
      <div class="mt-8 flex flex-wrap justify-center gap-4">
        <a href="{{ $ctaHref }}" class="rounded-xl bg-white px-7 py-4 font-bold text-slate-900 shadow-2xl transition hover:-translate-y-0.5"><span data-edit="{{ $bp }}.button">{{ $blk['button'] ?: $ctaLabel }}</span> <i class="fa-solid fa-arrow-right ml-1"></i></a>
        @if($phoneHref)<a href="{{ $phoneHref }}" class="rounded-xl border-2 border-white/30 px-7 py-4 font-bold text-white transition hover:bg-white/10"><i class="fa-solid fa-phone mr-2"></i>{{ $b['phone'] }}</a>@endif
      </div>
    </div>
  </div>
</section>

{{-- ── CONTACT ── --}}
@elseif($type === 'contact')
<section class="bg-slate-50 py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Contact' }}">
  <div class="mx-auto grid max-w-6xl gap-12 px-5 lg:grid-cols-2">
    <div class="reveal">
      <h2 class="font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
      @if(!empty($blk['text']) || $editMode)<p class="mt-4 text-lg text-slate-600" data-edit="{{ $bp }}.text">{{ $blk['text'] ?? '' }}</p>@endif
      <div class="mt-8 space-y-5">
        @if(!empty($b['phone']))<a href="{{ $phoneHref }}" class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl bg-grad text-white shadow-c"><i class="fa-solid fa-phone"></i></span><span><span class="block text-xs text-slate-400">Téléphone</span><span class="font-bold text-slate-800">{{ $b['phone'] }}</span></span></a>@endif
        @if(!empty($b['address']))<div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl bg-grad text-white shadow-c"><i class="fa-solid fa-location-dot"></i></span><span><span class="block text-xs text-slate-400">Adresse</span><span class="font-bold text-slate-800">{{ $b['address'] }}</span></span></div>@endif
        @if(count($hours))<div class="flex items-start gap-4"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-grad text-white shadow-c"><i class="fa-solid fa-clock"></i></span><ul class="text-sm text-slate-700">@foreach(array_slice($hours,0,7) as $h)<li class="flex justify-between gap-6"><span class="capitalize">{{ Str::before($h, ':') }}</span><span class="text-slate-500">{{ trim(Str::after($h, ':')) }}</span></li>@endforeach</ul></div>@endif
      </div>
      <a href="{{ $ctaHref }}" class="mt-8 inline-block rounded-xl bg-grad px-7 py-4 font-bold text-white shadow-c">{{ $ctaLabel }}</a>
    </div>
    @if(!empty($b['address']) && $mapsKey)
    <div class="reveal overflow-hidden rounded-[2rem] shadow-2xl"><iframe class="h-full min-h-[360px] w-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps/embed/v1/place?key={{ $mapsKey }}&q={{ urlencode(($b['name'] ?? '').' '.$b['address']) }}"></iframe></div>
    @endif
  </div>
</section>

{{-- ── VIDÉO ── --}}
@elseif($type === 'video')
<section class="py-20 sm:py-24" data-section="block-{{ $bi }}" data-label="{{ $blk['title'] ?: 'Vidéo' }}">
  <div class="mx-auto max-w-4xl px-5">
    <h2 class="reveal text-center font-display text-3xl font-bold text-slate-900 sm:text-4xl" data-edit="{{ $bp }}.title">{{ $blk['title'] }}</h2>
    @if($embed = $ytId($blk['url'] ?? null))
    <div class="reveal mt-10 aspect-video overflow-hidden rounded-[2rem] shadow-2xl"><iframe class="h-full w-full" src="{{ $embed }}" title="{{ $blk['title'] }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
    @elseif($editMode)
    <div class="mt-10 grid aspect-video place-items-center rounded-[2rem] border-2 border-dashed border-indigo-200 bg-indigo-50 text-indigo-500">Collez un lien YouTube ou Vimeo dans le panneau du bloc.</div>
    @endif
  </div>
</section>
@endif
@endforeach

@if(!count($page['blocks']) && $editMode)
<section class="py-24 text-center text-slate-500"><div class="mx-auto max-w-md rounded-3xl border-2 border-dashed border-indigo-200 bg-indigo-50 p-10"><p class="font-display text-xl font-bold text-slate-800">Page vide</p><p class="mt-2 text-sm">Ajoutez un bloc depuis le panneau « Pages » ou demandez à l'IA de remplir cette page.</p></div></section>
@endif
</main>
