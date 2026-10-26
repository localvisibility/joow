@php $appUrl = rtrim(config('marketing.app_url', config('app.url')), '/'); $popular = collect(config('sectors'))->only(['restaurant', 'plombier', 'coiffeur', 'electricien', 'sante', 'renovation', 'beaute', 'automobile', 'avocat', 'agence_web', 'hebergement', 'boucherie'])->map(fn ($c, $k) => ['key' => $k, 'label' => $c['label']]); @endphp
<footer class="relative mt-24 border-t border-white/[0.06] bg-ink-900/60">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 md:grid-cols-[1.3fr_1fr_1fr_1fr]">
        <div>
            <img src="/brand/joow-logo-white.svg" alt="Joow" class="h-8 w-auto" width="96" height="32">
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-400">Le site professionnel de chaque commerce, artisan et indépendant, généré par IA à partir de ses informations réelles et modifiable en quelques clics.</p>
            <a href="#generateur" class="btn-brand mt-6 !px-5 !py-2.5 text-sm">Créer mon site gratuitement</a>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Produit</p>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach(array_slice(config('marketing.features'), 0, 7, true) as $k => $f)<li><a href="{{ route('marketing.feature', $k) }}" class="text-slate-300 hover:text-white">{{ $f['title'] }}</a></li>@endforeach
                <li><a href="{{ route('marketing.pricing') }}" class="text-slate-300 hover:text-white">Tarifs</a></li>
                <li><a href="{{ route('marketing.examples') }}" class="text-slate-300 hover:text-white">Exemples</a></li>
            </ul>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Métiers populaires</p>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach($popular as $s)<li><a href="{{ route('marketing.sector', $s['key']) }}" class="text-slate-300 hover:text-white">Site pour {{ lcfirst($s['label']) }}</a></li>@endforeach
                <li><a href="{{ route('marketing.sectors') }}" class="font-semibold text-brand-400 hover:text-white">Tous les métiers →</a></li>
            </ul>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Joow</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('marketing.blog') }}" class="text-slate-300 hover:text-white">Blog</a></li>
                <li><a href="{{ route('marketing.faq') }}" class="text-slate-300 hover:text-white">Questions fréquentes</a></li>
                <li><a href="{{ route('marketing.about') }}" class="text-slate-300 hover:text-white">À propos</a></li>
                <li><a href="{{ route('marketing.contact') }}" class="text-slate-300 hover:text-white">Contact</a></li>
                <li><a href="{{ $appUrl }}/login" class="text-slate-300 hover:text-white">Espace client</a></li>
                <li class="pt-2"><a href="{{ route('marketing.legal', 'mentions-legales') }}" class="text-slate-500 hover:text-white">Mentions légales</a></li>
                <li><a href="{{ route('marketing.legal', 'confidentialite') }}" class="text-slate-500 hover:text-white">Confidentialité</a></li>
                <li><a href="{{ route('marketing.legal', 'cgv') }}" class="text-slate-500 hover:text-white">CGV</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/[0.06] px-5 py-5 text-center text-xs text-slate-500">© {{ date('Y') }} Joow · Fait en France · <a href="{{ route('marketing.sitemap') }}" class="hover:text-white">Plan du site</a></div>
</footer>
