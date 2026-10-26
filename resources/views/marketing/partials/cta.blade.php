<section id="generateur" class="relative mx-auto max-w-5xl px-5 py-20">
    <div class="cta-final relative overflow-hidden rounded-[2.5rem] border border-white/10 bg-ink-900 px-6 py-14 text-center sm:px-12">
        <div class="pointer-events-none absolute -left-24 -top-24 h-80 w-80 rounded-full bg-brand-500/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-fuchsia-500/25 blur-3xl"></div>
        <p class="relative text-xs font-bold uppercase tracking-[0.3em] text-brand-400">{{ $kicker ?? 'Essayez, c\'est gratuit' }}</p>
        <h2 class="relative mt-3 font-display text-3xl font-bold text-white sm:text-5xl">{{ $title ?? 'Votre site vous attend.' }}</h2>
        <p class="relative mx-auto mt-4 max-w-xl text-slate-400">{{ $text ?? 'Tapez le nom de votre établissement. Dans 90 secondes, vous verrez votre site, pas une maquette. Vous ne payez que si vous décidez de le mettre en ligne.' }}</p>
        <div class="relative mt-8">@include('marketing.partials.generator', ['compact' => true])</div>
    </div>
</section>
