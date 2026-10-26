@php $appUrl = rtrim(config('marketing.app_url', config('app.url')), '/'); @endphp
<header id="mnav" class="fixed inset-x-0 top-0 z-50 transition-all duration-300 [&.is-scrolled]:border-b [&.is-scrolled]:border-white/[0.06] [&.is-scrolled]:bg-ink-950/85 [&.is-scrolled]:backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
        <a href="{{ \App\Http\Middleware\MarketingHost::isMarketing(request()) ? '/' : route('marketing.home') }}" class="flex items-center" aria-label="Joow, accueil"><img src="/brand/joow-logo-white.svg" alt="Joow" class="h-8 w-auto" width="96" height="32"></a>
        <nav class="hidden items-center gap-7 md:flex" aria-label="Principale">
            @foreach(config('marketing.nav') as $item)
                <a href="{{ route($item['route']) }}" class="text-sm font-semibold text-slate-300 transition hover:text-white {{ request()->routeIs($item['route'].'*') ? 'text-white' : '' }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            <a href="{{ $appUrl }}/login" class="hidden rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25 hover:text-white sm:inline-flex">Se connecter</a>
            <a href="#generateur" class="btn-brand !px-4 !py-2 text-sm">Créer mon site</a>
            <button id="mnav-toggle" type="button" class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 text-white md:hidden" aria-expanded="false" aria-controls="mnav-menu" aria-label="Menu"><span class="text-lg">☰</span></button>
        </div>
    </div>
    <div id="mnav-menu" class="hidden border-t border-white/[0.06] bg-ink-950/95 px-5 py-4 backdrop-blur-xl md:hidden">
        @foreach(config('marketing.nav') as $item)<a href="{{ route($item['route']) }}" class="block py-2.5 text-base font-semibold text-slate-200">{{ $item['label'] }}</a>@endforeach
        <a href="{{ $appUrl }}/login" class="mt-2 block py-2.5 text-base font-semibold text-slate-400">Se connecter</a>
    </div>
</header>
