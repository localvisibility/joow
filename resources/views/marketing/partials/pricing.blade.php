<div class="stagger mt-12 grid gap-5 md:grid-cols-2">
    @foreach($pricing as $k => $p)
    <div class="reveal relative rounded-3xl border p-8 {{ $k === 'pro' ? 'border-brand-500/50 bg-brand-500/[0.06] shadow-glow' : 'border-white/10 bg-white/[0.03]' }}">
        @if($k === 'pro')<span class="absolute -top-3 left-8 rounded-full bg-brand-gradient px-3 py-1 text-xs font-bold text-white">Le plus choisi</span>@endif
        <h3 class="font-display text-2xl font-bold text-white">{{ $p['name'] }}</h3>
        <p class="mt-2 font-display text-5xl font-bold text-white">{{ $p['price'] }} €<span class="text-base font-medium text-slate-400"> {{ $p['period'] }}</span></p>
        <p class="mt-1 text-sm text-slate-400">{{ $p['note'] }}</p>
        <ul class="mt-6 space-y-2.5 text-sm text-slate-300">@foreach($p['items'] as $it)<li class="flex items-start gap-2.5"><span class="mt-0.5 text-emerald-300">✓</span>{{ $it }}</li>@endforeach</ul>
        <a href="#generateur" class="{{ $k === 'pro' ? 'btn-brand' : 'inline-flex items-center justify-center rounded-xl border border-white/15 px-5 py-3 font-semibold text-white transition hover:bg-white/5' }} mt-8 w-full">Commencer gratuitement</a>
    </div>
    @endforeach
</div>
