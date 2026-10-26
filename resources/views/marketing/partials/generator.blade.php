{{-- Générateur embarqué : trois points de départ, un seul champ. $compact : version courte pour les sections d'appel. --}}
@php $compact = $compact ?? false; @endphp
<div data-generator class="mx-auto w-full max-w-xl {{ $compact ? '' : 'text-left' }}">
    <div class="mb-3 flex flex-wrap items-center gap-1.5 {{ $compact ? 'justify-center' : 'justify-center lg:justify-start' }}" role="tablist">
        <button type="button" data-mode="google" class="gen-mode is-active">📍 J'ai une fiche Google <span class="ml-1 hidden text-[10px] font-bold uppercase tracking-wider text-emerald-300 sm:inline">· recommandé</span></button>
        <button type="button" data-mode="site" class="gen-mode">🌐 J'ai déjà un site</button>
        <button type="button" data-mode="none" class="gen-mode">✎ Ni l'un ni l'autre</button>
    </div>
    <form method="post" class="relative" autocomplete="off">
        <div class="search-shell relative">
            <span class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-400">🔍</span>
            <input name="q" type="text" class="field !rounded-2xl !border-white/20 !bg-white/[0.06] !py-4 pl-12 pr-4 text-base text-white" placeholder="Nom de votre établissement…" aria-label="Nom de votre établissement ou adresse de votre site">
            <div class="gen-results absolute z-20 mt-2 hidden w-full overflow-hidden rounded-2xl border border-white/10 bg-ink-800 text-left shadow-2xl"></div>
        </div>
        <p class="gen-hint mt-2 min-h-[1rem] text-xs text-amber-200/80"></p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <input name="email" type="email" class="field sm:flex-1" placeholder="Votre email (optionnel, pour recevoir le lien)">
            <button type="submit" class="btn-brand shrink-0 !px-6">Créer mon site</button>
        </div>
    </form>
    <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 {{ $compact ? 'justify-center' : 'justify-center lg:justify-start' }}"><span>✓ Gratuit</span><span>✓ Sans carte bancaire</span><span>✓ Aperçu en 90 secondes</span><span>✓ Modifiable en 2 clics</span></p>
</div>
