@php
    $photos = $b['photos'] ?? [];
    $hero = $photos[0] ?? 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=1600&q=80';
    $second = $photos[1] ?? $hero;
    $phoneHref = $b['phone'] ? 'tel:'.preg_replace('/\s/', '', $b['phone']) : '';
    $services = $c['services'] ?? [];
    $faq = $c['faq'] ?? [];
    $badges = $c['badges'] ?? [];
    $reviews = collect($b['reviews'] ?? [])->filter(fn($r) => strlen($r['text'] ?? '') > 20)->take(3);
    $stars = fn($n) => str_repeat('★', (int) floor($n)).($n - floor($n) >= 0.5 ? '½' : '');
@endphp
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $c['hero_title'] ?? $b['name'] }} | {{ $b['name'] }}</title>
<meta name="description" content="{{ $c['hero_subtitle'] ?? $label }} — {{ $b['name'] }}, {{ $b['city'] }}.">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{--c:{{ $color }}}
*{font-family:'Plus Jakarta Sans',sans-serif}
.font-display{font-family:'Space Grotesk',sans-serif}
.accent{color:var(--c)}.bg-accent{background:var(--c)}.border-accent{border-color:var(--c)}
.hero-ov{background:linear-gradient(120deg,rgba(10,12,20,.92),rgba(10,12,20,.55))}
.reveal{opacity:0;transform:translateY(24px);transition:.7s}.reveal.on{opacity:1;transform:none}
</style>
</head>
<body class="bg-white text-slate-800 antialiased">

<nav id="nav" class="fixed inset-x-0 top-0 z-50 transition-all">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
    <a href="#" class="flex items-center gap-2.5 text-white" id="brand">
      <span class="grid h-10 w-10 place-items-center rounded-xl text-white bg-accent"><i class="fa-solid {{ $icon }}"></i></span>
      <span class="font-display text-lg font-bold">{{ \Illuminate\Support\Str::limit($b['name'], 24) }}</span>
    </a>
    <div class="hidden items-center gap-7 md:flex" id="links">
      <a href="#services" class="text-sm font-semibold text-white/90 hover:text-white">Services</a>
      <a href="#apropos" class="text-sm font-semibold text-white/90 hover:text-white">À propos</a>
      <a href="#avis" class="text-sm font-semibold text-white/90 hover:text-white">Avis</a>
      <a href="#contact" class="rounded-xl bg-accent px-5 py-2.5 text-sm font-bold text-white shadow-lg">{{ $cta }}</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<header class="relative flex min-h-[92vh] items-center">
  <img src="{{ $hero }}" class="absolute inset-0 h-full w-full object-cover" alt="{{ $b['name'] }}">
  <div class="hero-ov absolute inset-0"></div>
  <div class="relative mx-auto w-full max-w-6xl px-5 py-32 text-white">
    @if($b['rating'])
    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm backdrop-blur">
      <span class="text-amber-400">{{ $stars($b['rating']) }}</span> {{ $b['rating'] }}/5 · {{ $b['reviews_count'] }} avis Google
    </div>
    @endif
    <p class="mb-3 font-semibold uppercase tracking-[0.25em] accent">{{ $label }}</p>
    <h1 class="font-display text-4xl font-bold leading-tight sm:text-6xl">{{ $c['hero_title'] ?? $b['name'] }}</h1>
    <p class="mt-5 max-w-xl text-lg text-white/80">{{ $c['hero_subtitle'] ?? '' }}</p>
    <div class="mt-8 flex flex-wrap gap-4">
      <a href="#contact" class="rounded-xl bg-accent px-7 py-4 font-bold text-white shadow-xl">{{ $cta }}</a>
      @if($phoneHref)<a href="{{ $phoneHref }}" class="rounded-xl border-2 border-white/30 px-7 py-4 font-bold text-white hover:bg-white/10"><i class="fa-solid fa-phone mr-2"></i>{{ $b['phone'] }}</a>@endif
    </div>
    @if($badges)
    <div class="mt-10 flex flex-wrap gap-x-8 gap-y-3">
      @foreach($badges as $bd)<span class="flex items-center gap-2 text-sm text-white/80"><i class="fa-solid fa-circle-check accent"></i>{{ $bd }}</span>@endforeach
    </div>
    @endif
  </div>
</header>

<!-- SERVICES -->
@if(count($services))
<section id="services" class="py-24">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-14 text-center">
      <p class="font-semibold uppercase tracking-[0.25em] accent">Nos prestations</p>
      <h2 class="mt-2 font-display text-4xl font-bold">Ce que nous faisons</h2>
    </div>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      @foreach($services as $s)
      <div class="reveal rounded-2xl border border-slate-100 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
        <div class="mb-4 grid h-12 w-12 place-items-center rounded-xl text-white bg-accent"><i class="fa-solid fa-check"></i></div>
        <h3 class="font-display text-lg font-bold">{{ $s['name'] ?? '' }}</h3>
        <p class="mt-2 text-sm text-slate-500">{{ $s['desc'] ?? '' }}</p>
        <div class="mt-4 border-t border-slate-100 pt-4 text-sm font-bold accent">{{ $s['price'] ?? 'Sur devis' }}</div>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- À PROPOS -->
<section id="apropos" class="bg-slate-50 py-24">
  <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 lg:grid-cols-2">
    <div class="reveal">
      <img src="{{ $second }}" class="aspect-[4/3] w-full rounded-3xl object-cover shadow-2xl" alt="{{ $b['name'] }}">
    </div>
    <div class="reveal">
      <p class="font-semibold uppercase tracking-[0.25em] accent">À propos</p>
      <h2 class="mt-2 font-display text-4xl font-bold">Votre partenaire de confiance</h2>
      <p class="mt-5 text-lg text-slate-600">{{ $c['about_p1'] ?? '' }}</p>
      <p class="mt-3 text-slate-600">{{ $c['about_p2'] ?? '' }}</p>
      @if($phoneHref)<a href="{{ $phoneHref }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-accent px-6 py-3 font-bold text-white"><i class="fa-solid fa-phone"></i>{{ $b['phone'] }}</a>@endif
    </div>
  </div>
</section>

<!-- AVIS -->
@if($reviews->count())
<section id="avis" class="py-24">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-14 text-center">
      <h2 class="font-display text-4xl font-bold">Ils nous recommandent</h2>
      @if($b['rating'])<p class="mt-2 text-slate-500"><span class="text-amber-500">{{ $stars($b['rating']) }}</span> {{ $b['rating'] }}/5 sur Google</p>@endif
    </div>
    <div class="grid gap-6 md:grid-cols-3">
      @foreach($reviews as $r)
      <div class="reveal rounded-2xl border border-slate-100 p-6 shadow-sm">
        <div class="mb-3 text-amber-500">{{ $stars($r['rating'] ?? 5) }}</div>
        <p class="text-sm italic text-slate-600">"{{ \Illuminate\Support\Str::limit($r['text'], 200) }}"</p>
        <p class="mt-4 text-sm font-bold">{{ $r['author'] ?? 'Client' }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- FAQ -->
@if(count($faq))
<section class="bg-slate-50 py-24">
  <div class="mx-auto max-w-3xl px-5">
    <h2 class="reveal mb-12 text-center font-display text-4xl font-bold">Questions fréquentes</h2>
    <div class="space-y-3">
      @foreach($faq as $f)
      <details class="reveal group rounded-2xl border border-slate-100 bg-white p-5">
        <summary class="flex cursor-pointer items-center justify-between font-bold">{{ $f['q'] ?? '' }}<i class="fa-solid fa-chevron-down accent transition group-open:rotate-180"></i></summary>
        <p class="mt-3 text-slate-600">{{ $f['a'] ?? '' }}</p>
      </details>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- CONTACT -->
<section id="contact" class="py-24">
  <div class="mx-auto max-w-6xl px-5">
    <div class="grid gap-10 lg:grid-cols-2">
      <div class="reveal">
        <h2 class="font-display text-4xl font-bold">{{ $cta }}</h2>
        <p class="mt-3 text-slate-600">{{ $c['cta_text'] ?? '' }}</p>
        <div class="mt-8 space-y-4">
          @if($b['phone'])<a href="{{ $phoneHref }}" class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl text-white bg-accent"><i class="fa-solid fa-phone"></i></span><span class="font-bold">{{ $b['phone'] }}</span></a>@endif
          @if($b['address'])<div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl text-white bg-accent"><i class="fa-solid fa-location-dot"></i></span><span>{{ $b['address'] }}</span></div>@endif
        </div>
      </div>
      <div class="reveal overflow-hidden rounded-3xl shadow-xl">
        <iframe class="h-full min-h-[320px] w-full" style="border:0" loading="lazy" allowfullscreen
          src="https://www.google.com/maps/embed/v1/place?key={{ $mapsKey }}&q={{ urlencode(($b['address'] ?? '').' '.($b['city'] ?? '')) }}&zoom=15"></iframe>
      </div>
    </div>
  </div>
</section>

<footer class="bg-slate-900 py-10 text-center text-slate-400">
  <div class="mx-auto max-w-6xl px-5">
    <p class="font-display text-lg font-bold text-white">{{ $b['name'] }}</p>
    <p class="mt-2 text-sm">{{ $b['address'] }}</p>
    <p class="mt-4 text-xs">© {{ date('Y') }} {{ $b['name'] }} — Site par <span class="accent font-semibold">Joow</span></p>
  </div>
</footer>

<script>
const nav=document.getElementById('nav');
const solid=()=>{const s=scrollY>40;nav.classList.toggle('bg-white',s);nav.classList.toggle('shadow-md',s);
document.getElementById('brand').classList.toggle('text-slate-900',s);document.getElementById('brand').classList.toggle('text-white',!s);
document.querySelectorAll('#links a:not(.bg-accent)').forEach(a=>{a.classList.toggle('text-slate-700',s);a.classList.toggle('text-white/90',!s)});};
solid();addEventListener('scroll',solid);
const io=new IntersectionObserver(e=>e.forEach(x=>x.isIntersecting&&x.target.classList.add('on')),{threshold:.1});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
</script>
</body>
</html>
