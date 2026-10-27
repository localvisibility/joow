@extends('marketing.layout')
@push('jsonld')<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post['title'], 'description' => $post['description'], 'datePublished' => $post['date'], 'dateModified' => $post['date'], 'inLanguage' => 'fr-FR', 'author' => ['@type' => 'Organization', 'name' => 'Joow'], 'publisher' => ['@type' => 'Organization', 'name' => 'Joow', 'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/joow-logo-white@2x.png')]], 'mainEntityOfPage' => 'https://'.config('marketing.host').route('marketing.post', $post['slug'], false)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>@endpush
@section('content')
<article class="relative overflow-hidden pt-32 sm:pt-40">
    <div class="aurora opacity-50"></div><div class="grain"></div>
    <div class="relative mx-auto max-w-3xl px-5">
        <nav class="text-xs text-slate-500" aria-label="Fil d'Ariane"><a href="{{ route('marketing.home') }}" class="hover:text-white">Accueil</a><span class="mx-1.5">/</span><a href="{{ route('marketing.blog') }}" class="hover:text-white">Blog</a></nav>
        <p class="mt-4 text-xs font-bold uppercase tracking-[0.3em] text-brand-400">{{ $post['category'] }} · {{ $post['minutes'] }} min de lecture · {{ \Carbon\Carbon::parse($post['date'])->translatedFormat('j F Y') }}</p>
        <h1 class="mt-3 font-display text-4xl font-bold leading-[1.08] text-white sm:text-5xl">{{ $post['title'] }}</h1>
        <p class="mt-5 text-xl text-slate-400">{{ $post['description'] }}</p>
        <div class="prose-joow mt-10 border-t border-white/10 pt-8">{!! $post['html'] !!}</div>
        @if(count($sectors))<div class="mt-10 rounded-2xl border border-white/10 bg-white/[0.03] p-5"><p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Modèles de sites pour ces métiers</p><div class="mt-3 flex flex-wrap gap-2">@foreach($sectors as $s)<a href="{{ route('marketing.sector', $s['key']) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 hover:border-brand-400/60 hover:text-white">{{ $s['label'] }}</a>@endforeach</div></div>@endif
        <div class="mt-12 rounded-3xl border border-brand-500/30 bg-brand-500/[0.06] p-6 text-center sm:p-8"><p class="font-display text-2xl font-bold text-white">Et si votre site était prêt dans 90 secondes ?</p><p class="mt-2 text-slate-400">Tapez le nom de votre établissement, l'IA fait le reste. Gratuit jusqu'à la mise en ligne.</p><a href="#generateur" class="btn-brand mt-5">Créer mon site</a></div>
    </div>
</article>
@if(count($more))<section class="mx-auto max-w-7xl px-5 py-20"><h2 class="font-display text-2xl font-bold text-white">À lire ensuite</h2><div class="stagger mt-6 grid gap-5 md:grid-cols-3">@foreach($more as $p)@include('marketing.partials.post-card', ['p' => $p])@endforeach</div></section>@endif
@include('marketing.partials.cta')
@endsection
