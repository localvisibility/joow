@php
    $apex = 'https://'.config('marketing.host', 'joow.fr');
    $isApex = \App\Http\Middleware\MarketingHost::isMarketing(request());
    $path = request()->getRequestUri();
    $canonical = $apex.($path === '/decouvrir' ? '/' : $path);
    $seo = $seo ?? [];
    $title = $seo['title'] ?? 'Joow — Le site pro de votre métier, créé par IA';
    $description = $seo['description'] ?? 'Joow crée le site professionnel de votre établissement en 90 secondes à partir de votre fiche Google.';
    $ogImage = $seo['image'] ?? url('/og.png');
    $noindex = ($seo['noindex'] ?? false) || ! $isApex;
    $appUrl = rtrim(config('marketing.app_url', config('app.url')), '/');
    $track = \App\Services\Tracking::pull();
@endphp
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth" data-app="{{ $appUrl }}" data-pixel="{{ config('services.meta.pixel_id') }}" data-gtm="{{ config('services.gtm.id') }}" data-track='@json($track)'>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.gtag')
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
@if($noindex)<meta name="robots" content="noindex,follow">@endif
<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:site_name" content="Joow">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:locale" content="fr_FR">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#08080c">
<link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="icon" href="/favicon.ico" sizes="32x32"><link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Joow', 'url' => $apex, 'logo' => url('/brand/joow-logo-white@2x.png'), 'sameAs' => [], 'contactPoint' => [['@type' => 'ContactPoint', 'email' => config('mail.from.address', 'contact@joow.fr'), 'contactType' => 'customer support', 'availableLanguage' => 'French']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@stack('jsonld')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" media="print" onload="this.media='all'">
@vite(['resources/js/marketing.js'])
</head>
<body class="bg-ink-950 font-sans text-slate-200 antialiased">
<a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-3 focus:py-2 focus:text-ink-950">Aller au contenu</a>

@include('marketing.partials.nav')

<main id="contenu">
    @yield('content')
</main>

@include('marketing.partials.footer')

{{-- Consentement : affiché par le script uniquement si un pixel est configuré et qu'aucun choix n'a été fait --}}
<div id="consent" class="fixed inset-x-3 bottom-3 z-[90] mx-auto hidden max-w-xl rounded-2xl border border-white/10 bg-ink-900/95 p-4 text-sm text-slate-200 shadow-2xl backdrop-blur sm:bottom-5" role="dialog" aria-label="Cookies">
    <p class="text-[13px] leading-relaxed">Nous utilisons un cookie de mesure publicitaire (Meta) pour savoir quelles campagnes vous ont amené ici. Aucune donnée n'est revendue. <a href="{{ route('marketing.legal', 'confidentialite') }}" class="underline underline-offset-2 hover:text-white">En savoir plus</a></p>
    <div class="mt-3 flex items-center justify-end gap-2"><button type="button" data-consent="essential" class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-300 hover:text-white">Refuser</button><button type="button" data-consent="all" class="btn-brand !px-4 !py-2 text-xs">Accepter</button></div>
</div>
</body>
</html>
