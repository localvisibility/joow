<a href="{{ route('marketing.post', $p['slug']) }}" class="reveal glass glass-hover group block rounded-3xl p-6">
    <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">{{ $p['category'] }} · {{ $p['minutes'] }} min</p>
    <h3 class="mt-3 font-display text-xl font-bold text-white group-hover:text-brand-400">{{ $p['title'] }}</h3>
    <p class="mt-2 text-sm text-slate-400">{{ $p['description'] }}</p>
    <p class="mt-4 text-xs text-slate-500">{{ \Carbon\Carbon::parse($p['date'])->translatedFormat('j F Y') }}</p>
</a>
