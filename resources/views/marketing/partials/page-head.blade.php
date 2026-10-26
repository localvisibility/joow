<section class="relative overflow-hidden pt-32 pb-12 sm:pt-40">
    <div class="aurora opacity-60"></div><div class="grain"></div>
    <div class="relative mx-auto max-w-4xl px-5 text-center">
        @if(!empty($crumbs))<nav class="mb-4 text-xs text-slate-500" aria-label="Fil d'Ariane">@foreach($crumbs as $i => [$label, $url])@if($i)<span class="mx-1.5">/</span>@endif @if($url)<a href="{{ $url }}" class="hover:text-white">{{ $label }}</a>@else<span class="text-slate-300">{{ $label }}</span>@endif @endforeach</nav>@endif
        @if(!empty($kicker))<p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">{{ $kicker }}</p>@endif
        <h1 class="mt-3 font-display text-4xl font-bold leading-[1.05] text-white sm:text-6xl">{!! $heading !!}</h1>
        @if(!empty($lead))<p class="mx-auto mt-5 max-w-2xl text-lg text-slate-400">{{ $lead }}</p>@endif
    </div>
</section>
