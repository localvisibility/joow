@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => $city['dept_name'].' · '.$city['region'], 'heading' => 'Un site internet pour votre activité <span class="text-shimmer">'.$city['loc'].'</span>', 'lead' => 'Les '.$city['gentile'].' cherchent leurs commerçants, artisans et praticiens sur Google, depuis leur téléphone. Joow crée votre site à partir de votre fiche Google, avec les modules de votre métier, en 90 secondes.', 'crumbs' => [['Accueil', route('marketing.home')], ['Villes', route('marketing.cities')], [$city['name'], null]]])
<section class="mx-auto max-w-7xl px-5 pb-10">
    <div class="stagger grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($sectors as $s)<a href="{{ route('marketing.local', [$s['key'], $city['slug']]) }}" class="reveal glass glass-hover group flex items-center gap-4 rounded-2xl p-4"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white" style="background: {{ $s['color'] }}"><i class="fa-solid {{ $s['icon'] }}"></i></span><span class="min-w-0"><span class="block truncate font-semibold text-white group-hover:text-brand-400">{{ ucfirst($s['noun']['sing']) }} {{ $city['loc'] }}</span><span class="block text-xs text-slate-500">{{ $s['cta'] }}</span></span></a>@endforeach
    </div>
    <div class="mt-10 grid gap-6 lg:grid-cols-2">
        <div class="reveal glass rounded-3xl p-7"><p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Votre zone</p><h2 class="mt-3 font-display text-2xl font-bold text-white">{{ $city['name'] }} et ses environs</h2><p class="mt-3 text-slate-400">{{ $city['pop'] }} habitants, des quartiers aux identités marquées ({{ implode(', ', array_slice($city['quartiers'], 0, 4)) }}…) et des communes voisines où vos clients vous cherchent aussi : {{ implode(', ', $city['voisines']) }}.</p></div>
        <div class="reveal glass rounded-3xl p-7"><p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Votre métier n'est pas listé ?</p><h2 class="mt-3 font-display text-2xl font-bold text-white">73 modèles de sites, un par métier</h2><p class="mt-3 text-slate-400">Les 15 métiers ci-dessus sont les plus recherchés {{ $city['loc'] }}. Joow en couvre 73 : <a href="{{ route('marketing.sectors') }}" class="text-brand-400 hover:text-white">voir tous les métiers</a>, ou tapez simplement le nom de votre établissement.</p></div>
    </div>
    <p class="mt-10 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Villes proches</p>
    <div class="mt-4 flex flex-wrap gap-2">@foreach($nearby as $n)<a href="{{ route('marketing.city', $n['slug']) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 hover:border-brand-400/60 hover:text-white">{{ $n['name'] }}</a>@endforeach</div>
</section>
@include('marketing.partials.cta', ['title' => 'Votre site '.$city['loc'].', en 90 secondes.', 'text' => 'Tapez le nom de votre établissement : votre fiche Google apparaît, l\'IA fait le reste. Gratuit jusqu\'à la mise en ligne.'])
@endsection
