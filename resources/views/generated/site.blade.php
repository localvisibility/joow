@php
    use Illuminate\Support\Str;
    $photos = array_values(array_filter($b['photos'] ?? []));
    $hero   = $photos[0] ?? 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1920&q=80';
    $second = $photos[1] ?? $hero;
    $gallery = array_slice($photos, 2, 6);
    $phoneHref = !empty($b['phone']) ? 'tel:'.preg_replace('/\s/', '', $b['phone']) : '';
    $services = $c['services'] ?? [];
    $faq      = $c['faq'] ?? [];
    $badges   = $c['badges'] ?? [];
    $stats    = $c['stats'] ?? [];
    $hours    = $b['opening_hours'] ?? [];
    $reviews  = collect($b['reviews'] ?? [])->filter(fn($r) => strlen($r['text'] ?? '') > 20)->take(6)->values();
    $tagline  = $c['tagline'] ?? $label.' · '.($b['city'] ?? 'France');
    $stars = fn($n) => str_repeat('★', max(0, (int) round($n)));
    $initial = fn($name) => Str::upper(Str::substr(trim($name) ?: 'C', 0, 1));
    // Stats par défaut si l'IA n'en fournit pas
    if (!count($stats)) {
        $stats = array_values(array_filter([
            $b['rating'] ? ['v' => $b['rating'].'/5', 'l' => 'Note Google'] : null,
            $b['reviews_count'] ? ['v' => $b['reviews_count'].'+', 'l' => 'Avis clients'] : null,
            $b['city'] ? ['v' => $b['city'], 'l' => "Zone d'intervention"] : null,
            ['v' => '100%', 'l' => 'Clients satisfaits'],
        ]));
        $stats = array_slice($stats, 0, 3);
    }
@endphp
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $c['hero_title'] ?? $b['name'] }} — {{ $b['name'] }}{{ $b['city'] ? ', '.$b['city'] : '' }}</title>
<meta name="description" content="{{ $c['hero_subtitle'] ?? $label }} — {{ $b['name'] }}{{ $b['city'] ? ', '.$b['city'] : '' }}. {{ $c['cta_text'] ?? '' }}">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --c:{{ $color }};
  --c-d:color-mix(in srgb, {{ $color }} 62%, #000);
  --c-l:color-mix(in srgb, {{ $color }} 12%, #fff);
  --grad:linear-gradient(135deg, {{ $color }} 0%, color-mix(in srgb, {{ $color }} 55%, #7c3aed) 100%);
}
*{font-family:'Plus Jakarta Sans',sans-serif}
.font-display{font-family:'Space Grotesk',sans-serif;letter-spacing:-.01em}
.accent{color:var(--c)}.bg-accent{background:var(--c)}.bg-grad{background:var(--grad)}
.text-grad{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.border-accent{border-color:var(--c)}
.ring-accent{--tw-ring-color:color-mix(in srgb,var(--c) 40%,transparent)}
.shadow-c{box-shadow:0 20px 45px -20px color-mix(in srgb,var(--c) 70%,transparent)}
.hero-ov{background:linear-gradient(115deg,rgba(7,8,14,.94) 0%,rgba(7,8,14,.66) 42%,rgba(7,8,14,.30) 100%)}
.grain{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E");mix-blend-mode:overlay;opacity:.5}
.reveal{opacity:0;transform:translateY(28px);transition:opacity .8s cubic-bezier(.22,1,.36,1),transform .8s cubic-bezier(.22,1,.36,1)}
.reveal.on{opacity:1;transform:none}
@keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.floaty{animation:floaty 5s ease-in-out infinite}
@keyframes marq{to{transform:translateX(-50%)}}
.marq{animation:marq 28s linear infinite}
.card-hover{transition:transform .35s cubic-bezier(.22,1,.36,1),box-shadow .35s}
.card-hover:hover{transform:translateY(-6px)}
::selection{background:var(--c);color:#fff}
html{scroll-padding-top:80px}
</style>
</head>
<body class="bg-white text-slate-800 antialiased">

<!-- NAV -->
<nav id="nav" class="fixed inset-x-0 top-0 z-50 transition-all duration-300">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
    <a href="#top" class="flex items-center gap-2.5 text-white" id="brand">
      <span class="grid h-10 w-10 place-items-center rounded-xl text-white bg-grad shadow-c"><i class="fa-solid {{ $icon }}"></i></span>
      <span class="font-display text-lg font-bold">{{ Str::limit($b['name'], 26) }}</span>
    </a>
    <div class="hidden items-center gap-7 md:flex" id="links">
      <a href="#services" class="text-sm font-semibold text-white/90 transition hover:text-white">Services</a>
      <a href="#apropos" class="text-sm font-semibold text-white/90 transition hover:text-white">À propos</a>
      @if($reviews->count())<a href="#avis" class="text-sm font-semibold text-white/90 transition hover:text-white">Avis</a>@endif
      <a href="#contact" class="rounded-xl bg-grad px-5 py-2.5 text-sm font-bold text-white shadow-c transition hover:-translate-y-0.5">{{ $cta }}</a>
    </div>
    @if($phoneHref)<a href="{{ $phoneHref }}" class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-white backdrop-blur md:hidden"><i class="fa-solid fa-phone"></i></a>@endif
  </div>
</nav>

<!-- HERO -->
<header id="top" class="relative flex min-h-[100svh] items-center overflow-hidden">
  <img src="{{ $hero }}" id="heroimg" class="absolute inset-0 h-[115%] w-full object-cover" alt="{{ $b['name'] }}" fetchpriority="high">
  <div class="hero-ov absolute inset-0"></div>
  <div class="grain absolute inset-0"></div>
  <div class="pointer-events-none absolute -bottom-24 right-0 h-96 w-96 rounded-full blur-3xl" style="background:var(--grad);opacity:.28"></div>

  <div class="relative mx-auto grid w-full max-w-6xl items-center gap-10 px-5 py-28 text-white lg:grid-cols-[1.15fr,.85fr]">
    <div>
      @if($b['rating'])
      <div class="reveal on mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm backdrop-blur">
        <span class="text-amber-400">{{ $stars($b['rating']) }}</span>
        <span class="font-semibold">{{ $b['rating'] }}/5</span>
        <span class="text-white/70">· {{ $b['reviews_count'] }} avis Google</span>
      </div>
      @endif
      <p class="reveal on mb-3 text-sm font-bold uppercase tracking-[0.3em] accent">{{ $label }}</p>
      <h1 class="reveal on font-display text-[2.6rem] font-bold leading-[1.03] sm:text-6xl">{{ $c['hero_title'] ?? $b['name'] }}</h1>
      <p class="reveal on mt-5 max-w-xl text-lg text-white/80">{{ $c['hero_subtitle'] ?? $tagline }}</p>
      <div class="reveal on mt-9 flex flex-wrap gap-4">
        <a href="#contact" class="group rounded-xl bg-grad px-7 py-4 font-bold text-white shadow-c transition hover:-translate-y-0.5">{{ $cta }} <i class="fa-solid fa-arrow-right ml-1 transition group-hover:translate-x-1"></i></a>
        @if($phoneHref)<a href="{{ $phoneHref }}" class="rounded-xl border-2 border-white/25 px-7 py-4 font-bold text-white backdrop-blur transition hover:bg-white/10"><i class="fa-solid fa-phone mr-2"></i>{{ $b['phone'] }}</a>@endif
      </div>
      @if($badges)
      <div class="reveal on mt-10 flex flex-wrap gap-x-7 gap-y-3">
        @foreach(array_slice($badges,0,4) as $bd)<span class="flex items-center gap-2 text-sm text-white/85"><i class="fa-solid fa-circle-check accent"></i>{{ $bd }}</span>@endforeach
      </div>
      @endif
    </div>

    <!-- Carte flottante -->
    <div class="reveal on hidden lg:block">
      <div class="floaty rounded-3xl border border-white/15 bg-white/10 p-6 backdrop-blur-xl shadow-2xl">
        <div class="flex items-center gap-3 border-b border-white/15 pb-4">
          <span class="grid h-11 w-11 place-items-center rounded-xl bg-grad"><i class="fa-solid fa-location-dot text-white"></i></span>
          <div class="min-w-0">
            <p class="truncate font-display font-bold text-white">{{ Str::limit($b['name'],22) }}</p>
            <p class="truncate text-xs text-white/70">{{ $b['city'] ?: 'France' }}</p>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3 py-5 text-center">
          @foreach($stats as $st)
          <div><p class="font-display text-xl font-bold text-white">{{ $st['v'] }}</p><p class="mt-0.5 text-[11px] leading-tight text-white/65">{{ $st['l'] }}</p></div>
          @endforeach
        </div>
        @if($phoneHref)<a href="{{ $phoneHref }}" class="flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-900 transition hover:bg-white/90"><i class="fa-solid fa-phone accent"></i>Appeler maintenant</a>@endif
      </div>
    </div>
  </div>

  <a href="#services" class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/50 transition hover:text-white"><i class="fa-solid fa-chevron-down animate-bounce"></i></a>
</header>

<!-- STATS BAND (mobile + renfort) -->
<section class="border-b border-slate-100 bg-white">
  <div class="mx-auto grid max-w-5xl grid-cols-3 gap-4 px-5 py-8 text-center lg:hidden">
    @foreach($stats as $st)
    <div><p class="font-display text-2xl font-bold accent">{{ $st['v'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $st['l'] }}</p></div>
    @endforeach
  </div>
</section>

<!-- SERVICES -->
@if(count($services))
<section id="services" class="py-24">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-14 max-w-2xl">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Nos prestations</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">Un savoir-faire complet</h2>
      <p class="mt-4 text-lg text-slate-500">{{ $c['about_p1'] ?? '' }}</p>
    </div>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      @foreach($services as $i => $s)
      <div class="reveal card-hover group relative overflow-hidden rounded-3xl border border-slate-100 bg-white p-8 shadow-sm hover:shadow-2xl">
        <span class="absolute right-5 top-4 font-display text-5xl font-bold text-slate-100 transition group-hover:text-slate-200">{{ sprintf('%02d', $i+1) }}</span>
        <div class="relative mb-5 grid h-14 w-14 place-items-center rounded-2xl bg-grad text-white shadow-c"><i class="fa-solid fa-check text-lg"></i></div>
        <h3 class="relative font-display text-xl font-bold">{{ $s['name'] ?? '' }}</h3>
        <p class="relative mt-2 text-slate-500">{{ $s['desc'] ?? '' }}</p>
        <div class="relative mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
          <span class="text-sm font-bold accent">{{ $s['price'] ?? 'Sur devis' }}</span>
          <a href="#contact" class="text-sm font-semibold text-slate-400 transition group-hover:accent">En savoir plus →</a>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- GALERIE -->
@if(count($gallery) >= 3)
<section class="overflow-hidden pb-4">
  <div class="reveal mx-auto mb-10 max-w-6xl px-5">
    <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Galerie</p>
    <h2 class="mt-3 font-display text-4xl font-bold">En images</h2>
  </div>
  <div class="flex w-max marq gap-5 pl-5">
    @foreach(array_merge($gallery, $gallery) as $g)
    <div class="h-64 w-96 shrink-0 overflow-hidden rounded-3xl"><img src="{{ $g }}" class="h-full w-full object-cover" alt="{{ $b['name'] }}" loading="lazy"></div>
    @endforeach
  </div>
</section>
@endif

<!-- À PROPOS -->
<section id="apropos" class="bg-slate-50 py-24">
  <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 lg:grid-cols-2">
    <div class="reveal relative">
      <img src="{{ $second }}" class="aspect-[4/3] w-full rounded-[2rem] object-cover shadow-2xl" alt="{{ $b['name'] }}">
      @if($b['rating'])
      <div class="absolute -bottom-6 -right-4 rounded-2xl bg-white p-5 shadow-xl sm:-right-6">
        <div class="text-amber-500">{{ $stars($b['rating']) }}</div>
        <p class="mt-1 font-display text-2xl font-bold">{{ $b['rating'] }}<span class="text-base text-slate-400">/5</span></p>
        <p class="text-xs text-slate-500">{{ $b['reviews_count'] }} avis Google</p>
      </div>
      @endif
    </div>
    <div class="reveal">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">À propos</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">Votre partenaire de confiance</h2>
      <p class="mt-5 text-lg text-slate-600">{{ $c['about_p1'] ?? '' }}</p>
      <p class="mt-4 text-slate-600">{{ $c['about_p2'] ?? '' }}</p>
      @if($badges)
      <div class="mt-8 grid grid-cols-2 gap-4">
        @foreach(array_slice($badges,0,4) as $bd)
        <div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-check text-xs"></i></span><span class="text-sm font-semibold text-slate-700">{{ $bd }}</span></div>
        @endforeach
      </div>
      @endif
      @if($phoneHref)<a href="{{ $phoneHref }}" class="mt-9 inline-flex items-center gap-2 rounded-xl bg-grad px-6 py-3.5 font-bold text-white shadow-c transition hover:-translate-y-0.5"><i class="fa-solid fa-phone"></i>{{ $b['phone'] }}</a>@endif
    </div>
  </div>
</section>

<!-- AVIS -->
@if($reviews->count())
<section id="avis" class="py-24">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-14 text-center">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Témoignages</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">Ils nous recommandent</h2>
      @if($b['rating'])<p class="mt-3 text-slate-500"><span class="text-amber-500">{{ $stars($b['rating']) }}</span> {{ $b['rating'] }}/5 · {{ $b['reviews_count'] }} avis sur Google</p>@endif
    </div>
    <div class="grid gap-6 md:grid-cols-3">
      @foreach($reviews->take(6) as $r)
      <figure class="reveal card-hover rounded-3xl border border-slate-100 bg-white p-7 shadow-sm hover:shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <div class="text-amber-500">{{ $stars($r['rating'] ?? 5) }}</div>
          <i class="fa-brands fa-google text-slate-300"></i>
        </div>
        <blockquote class="text-slate-600">"{{ Str::limit($r['text'], 220) }}"</blockquote>
        <figcaption class="mt-5 flex items-center gap-3">
          <span class="grid h-10 w-10 place-items-center rounded-full bg-grad font-display font-bold text-white">{{ $initial($r['author'] ?? 'C') }}</span>
          <span class="font-bold text-slate-800">{{ $r['author'] ?: 'Client' }}</span>
        </figcaption>
      </figure>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- FAQ -->
@if(count($faq))
<section class="bg-slate-50 py-24">
  <div class="mx-auto max-w-3xl px-5">
    <div class="reveal mb-12 text-center">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">FAQ</p>
      <h2 class="mt-3 font-display text-4xl font-bold">Questions fréquentes</h2>
    </div>
    <div class="space-y-3">
      @foreach($faq as $f)
      <details class="reveal group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between font-bold text-slate-800">{{ $f['q'] ?? '' }}<span class="grid h-7 w-7 place-items-center rounded-full bg-slate-100 accent transition group-open:rotate-45"><i class="fa-solid fa-plus text-xs"></i></span></summary>
        <p class="mt-3 text-slate-600">{{ $f['a'] ?? '' }}</p>
      </details>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- CONTACT -->
<section id="contact" class="relative py-24">
  <div class="mx-auto max-w-6xl px-5">
    <div class="grid gap-10 lg:grid-cols-2">
      <div class="reveal">
        <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Contact</p>
        <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">{{ $cta }}</h2>
        <p class="mt-4 text-lg text-slate-600">{{ $c['cta_text'] ?? '' }}</p>
        <div class="mt-9 space-y-4">
          @if($b['phone'])<a href="{{ $phoneHref }}" class="group flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl bg-grad text-white shadow-c"><i class="fa-solid fa-phone"></i></span><span><span class="block text-xs text-slate-400">Téléphone</span><span class="font-bold text-slate-800">{{ $b['phone'] }}</span></span></a>@endif
          @if($b['address'])<div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl bg-grad text-white shadow-c"><i class="fa-solid fa-location-dot"></i></span><span><span class="block text-xs text-slate-400">Adresse</span><span class="font-bold text-slate-800">{{ $b['address'] }}</span></span></div>@endif
        </div>
        @if(count($hours))
        <div class="mt-8 rounded-2xl border border-slate-100 bg-slate-50 p-5">
          <p class="mb-3 flex items-center gap-2 font-bold text-slate-800"><i class="fa-solid fa-clock accent"></i>Horaires</p>
          <ul class="space-y-1 text-sm text-slate-600">
            @foreach($hours as $h)<li class="flex justify-between gap-4"><span>{{ Str::before($h, ':') }}</span><span class="font-medium">{{ trim(Str::after($h, ':')) }}</span></li>@endforeach
          </ul>
        </div>
        @endif
      </div>
      <div class="reveal overflow-hidden rounded-[2rem] shadow-xl">
        <iframe class="h-full min-h-[380px] w-full" style="border:0" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps/embed/v1/place?key={{ $mapsKey }}&q={{ urlencode(($b['address'] ?? '').' '.($b['city'] ?? '')) }}&zoom=15"></iframe>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="bg-slate-900 py-14 text-slate-400">
  <div class="mx-auto max-w-6xl px-5">
    <div class="flex flex-col items-center justify-between gap-6 border-b border-white/10 pb-8 sm:flex-row">
      <a href="#top" class="flex items-center gap-2.5 text-white">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-grad"><i class="fa-solid {{ $icon }}"></i></span>
        <span class="font-display text-lg font-bold">{{ $b['name'] }}</span>
      </a>
      <div class="flex items-center gap-6 text-sm">
        <a href="#services" class="transition hover:text-white">Services</a>
        <a href="#apropos" class="transition hover:text-white">À propos</a>
        <a href="#contact" class="transition hover:text-white">Contact</a>
      </div>
    </div>
    <div class="mt-8 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
      <p>{{ $b['address'] }}</p>
      <p class="text-xs">© {{ date('Y') }} {{ $b['name'] }} — Site créé avec <a href="https://joow.fr" class="font-semibold accent">Joow</a></p>
    </div>
  </div>
</footer>

<!-- BARRE MOBILE STICKY -->
@if($phoneHref)
<a href="{{ $phoneHref }}" class="fixed inset-x-4 bottom-4 z-50 flex items-center justify-center gap-2 rounded-2xl bg-grad px-6 py-4 font-bold text-white shadow-2xl md:hidden"><i class="fa-solid fa-phone"></i>Appeler {{ Str::limit($b['name'],18) }}</a>
@endif

<script>
const nav=document.getElementById('nav'),brand=document.getElementById('brand'),hero=document.getElementById('heroimg');
const onScroll=()=>{const s=scrollY>40;
  nav.classList.toggle('bg-white/95',s);nav.classList.toggle('backdrop-blur-lg',s);nav.classList.toggle('shadow-lg',s);
  brand.classList.toggle('text-slate-900',s);brand.classList.toggle('text-white',!s);
  document.querySelectorAll('#links a:not(.bg-grad)').forEach(a=>{a.classList.toggle('text-slate-700',s);a.classList.toggle('text-white/90',!s)});
  if(hero) hero.style.transform='translateY('+Math.min(scrollY*.28,180)+'px)';
};
onScroll();addEventListener('scroll',onScroll,{passive:true});
const io=new IntersectionObserver(e=>e.forEach(x=>{if(x.isIntersecting){x.target.classList.add('on');io.unobserve(x.target)}}),{threshold:.12});
document.querySelectorAll('.reveal:not(.on)').forEach(el=>io.observe(el));
</script>
</body>
</html>
