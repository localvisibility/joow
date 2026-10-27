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

{{-- Consentement : affiché par le script uniquement si un pixel ou GTM est configuré et qu'aucun choix n'a été fait --}}
<div id="consent" class="consent-card hidden" role="dialog" aria-label="Cookies et mesure d'audience">
    <div class="flex items-start justify-between gap-4"><p class="font-display text-base font-bold text-white">🍪 Cookies &amp; mesure d'audience</p><button type="button" data-consent="none" class="shrink-0 text-xs text-slate-400 underline decoration-slate-600 underline-offset-4 hover:text-white">Continuer sans accepter</button></div>
    <p class="mt-2 text-[13px] leading-relaxed text-slate-300">Nous utilisons des cookies pour améliorer votre expérience et mesurer l'efficacité de nos campagnes. Rien n'est déposé sans votre accord. <button type="button" data-consent="custom" class="underline underline-offset-2 hover:text-white">Personnaliser</button></p>
    <div data-consent-panel class="mt-3 hidden space-y-2 rounded-xl border border-white/10 bg-white/[0.03] p-3 text-[13px]">
        <label class="flex items-center justify-between gap-3"><span><span class="font-semibold text-white">Mesure d'audience</span><span class="block text-xs text-slate-400">Comprendre quelles pages sont utiles (Google Analytics).</span></span><input type="checkbox" name="analytics" checked class="h-5 w-5 rounded accent-brand-500"></label>
        <label class="flex items-center justify-between gap-3"><span><span class="font-semibold text-white">Mesure publicitaire</span><span class="block text-xs text-slate-400">Savoir quelle publicité vous a amené ici (Meta, Google Ads).</span></span><input type="checkbox" name="ads" checked class="h-5 w-5 rounded accent-brand-500"></label>
        <label class="flex items-center justify-between gap-3 opacity-60"><span><span class="font-semibold text-white">Fonctionnement du site</span><span class="block text-xs text-slate-400">Session, sécurité, mémorisation de ce choix.</span></span><input type="checkbox" checked disabled class="h-5 w-5 rounded"></label>
        <button type="button" data-consent="save" class="mt-1 w-full rounded-xl border border-white/15 px-3 py-2 text-xs font-semibold text-white hover:bg-white/5">Enregistrer mes choix</button>
    </div>
    <button type="button" data-consent="all" class="btn-brand mt-3 w-full !py-3 text-sm">Accepter</button>
    <p class="mt-2 text-center text-[11px] text-slate-500"><a href="{{ route('marketing.legal', 'confidentialite') }}" class="hover:text-white">Politique de confidentialité</a></p>
</div>
</body>
</html>
