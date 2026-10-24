<script setup>
/*
 * Spectacle de génération : pendant 60 à 90 s, le visiteur voit l'IA travailler —
 * journal en direct (typewriter), photos scannées et décrites, site qui se construit
 * sous ses yeux, barre de progression calée sur la durée réelle des étapes.
 * Alimenté par `progress` (données publiées par le job) et `ready`.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({ site: Object, progress: { type: Object, default: () => ({}) }, ready: Boolean, failed: Boolean });

/* ── Progression temporelle réaliste (durées attendues par étape, en secondes) ── */
const PLAN = [['lookup', 4, 0, 6], ['photos', 24, 6, 34], ['content', 42, 34, 82], ['render', 10, 82, 96]]; // [étape, durée, % début, % fin]
const started = Date.now();
const elapsed = ref(0);
const pct = ref(0);
const stepStartedAt = ref(Date.now());
const currentStep = computed(() => props.progress?.step || 'lookup');
watch(currentStep, () => { stepStartedAt.value = Date.now(); });

let raf = null;
const tick = () => {
    elapsed.value = (Date.now() - started) / 1000;
    if (props.ready) { pct.value = Math.min(100, pct.value + (100 - pct.value) * 0.18 + 0.6); }
    else {
        const plan = PLAN.find((p) => p[0] === currentStep.value) || PLAN[0];
        const [, dur0, from, to] = plan;
        const dur = dur0 * speed;
        const t = (Date.now() - stepStartedAt.value) / 1000;
        const eased = 1 - Math.exp(-t / (dur * 0.9));           // approche asymptotique : ne dépasse jamais la fin de l'étape
        const target = from + (to - from) * Math.min(0.97, eased);
        pct.value = Math.max(pct.value, pct.value + (target - pct.value) * 0.08);
    }
    raf = requestAnimationFrame(tick);
};
// Refonte : lecture du site existant et reconstruction des sous-pages en plus → ~2,5 min au lieu de 90 s
const speed = (props.site?.source === 'refonte') ? 1.8 : 1;
const remaining = computed(() => Math.max(0, Math.round((100 - pct.value) / 100 * 92 * speed)));

/* ── Journal de l'IA : lignes scriptées + lignes issues des vraies données, tapées lettre par lettre ── */
const lines = ref([]);
const queue = [];
const typing = ref('');
let typingTimer = null;
const said = new Set();
const say = (key, text, tone = '') => { if (said.has(key)) return; said.add(key); queue.push({ text, tone }); };
const pump = () => {
    if (typingTimer || !queue.length) return;
    const item = queue.shift(); let i = 0; typing.value = '';
    typingTimer = setInterval(() => {
        i += 2; typing.value = item.text.slice(0, i);
        if (i >= item.text.length) { clearInterval(typingTimer); typingTimer = null; lines.value.push({ ...item, at: elapsed.value }); if (lines.value.length > 9) lines.value.shift(); typing.value = ''; setTimeout(pump, 260); }
    }, 18);
};
const fmt = (n) => (n == null ? '' : Number(n).toLocaleString('fr-FR'));
const p = computed(() => props.progress || {});

const scripted = [ // [secondes écoulées min, étape requise ou null, clé, texte]
    [0, null, 'boot', 'Initialisation du moteur Joow · modèle multimodal chargé'],
    [1.2, null, 'gbp', { google: 'Connexion à la fiche Google Business Profile…', questionnaire: 'Lecture de vos réponses : métier, prestations, points forts…', refonte: 'Lecture de votre site actuel : pages, textes, images…' }],
    [9, 'photos', 'vision', 'Analyse visuelle des photos : cadrage, lumière, sujet, qualité…'],
    [20, 'photos', 'roles', 'Attribution des rôles : visuel principal, portrait, services, galerie'],
    [1, 'content', 'brief', 'Brief métier appliqué · ton, services typiques, règles du secteur'],
    [7, 'content', 'write1', 'Rédaction de l\'accroche et du sous-titre…'],
    [16, 'content', 'write2', 'Rédaction des prestations, des prix et du parcours client…'],
    [27, 'content', 'write3', 'Rédaction de la FAQ à partir des questions réelles des clients…'],
    [38, 'content', 'check', 'Relecture : aucun faux chiffre, aucune promesse non vérifiable'],
    [50, 'content', 'polish', 'Harmonisation des titres de sections…'],
    [0.5, 'render', 'layout', 'Mise en page : grille, typographie, palette du métier'],
    [4, 'render', 'html', 'Génération du HTML, optimisation des images, balises SEO'],
    [8, 'render', 'publish', 'Publication de l\'aperçu sur votre adresse joow.fr…'],
];
let scriptTimer = null;
const runScript = () => {
    const step = currentStep.value;
    const sinceStep = (Date.now() - stepStartedAt.value) / 1000;
    const src = p.value.source || props.site?.source || 'google';
    const srcKey = src === 'questionnaire' ? 'questionnaire' : (src === 'refonte' ? 'refonte' : 'google');
    for (const [at, req, key, text] of scripted) {
        if (req === null ? elapsed.value >= at : (step === req && sinceStep >= at)) say(key, typeof text === 'string' ? text : text[srcKey]);
    }
    const d = p.value;
    if (d.name) say('found', (srcKey === 'google' ? 'Fiche trouvée : ' : (srcKey === 'refonte' ? 'Site analysé : ' : 'Entreprise : ')) + `${d.name}${d.city ? ' · ' + d.city : ''}`, 'ok');
    if (d.reviews_count) say('reviews', `Lecture de ${fmt(d.reviews_count)} avis clients · note ${d.rating ?? '—'}/5`, 'ok');
    if (d.sector) say('sector', `Métier identifié : ${d.sector} → modèle « ${d.sector} » chargé`, 'ok');
    if (d.photos?.length) say('photos', srcKey === 'google' ? `${d.photos.length} photos récupérées sur la fiche` : (srcKey === 'refonte' ? `${d.photos.length} images récupérées sur votre site` : `${d.photos.length} photos reçues`), 'ok');
    if (d.step && d.step !== 'lookup' && !d.photos?.length && srcKey !== 'google') say('stock', 'Aucune photo fournie : sélection de visuels professionnels du métier', 'ok');
    (d.photo_notes || []).forEach((n, i) => { if (n.description) say('note' + i, `Photo ${i + 1} · ${n.category} · ${n.description}`); });
    if (d.hero) say('hero', 'Visuel principal sélectionné pour le haut de page', 'ok');
    if (d.hero_title) say('title', `Accroche : « ${d.hero_title} »`, 'ok');
    (d.services || []).slice(0, 4).forEach((s, i) => say('svc' + i, `Prestation rédigée : ${s}`));
    if (props.ready) say('done', 'Site prêt ✓ Ouverture de l\'aperçu…', 'ok');
    pump();
};

/* ── Scanner de photos : surbrillance qui passe de photo en photo pendant l'analyse ── */
const scanIndex = ref(0);
let scanTimer = null;
const photos = computed(() => p.value.photos || []);
const notes = computed(() => p.value.photo_notes || []);
const noteFor = (i) => notes.value[i]?.description || '';

/* ── Aperçu qui se construit ── */
const built = computed(() => ({
    nav: !!p.value.name,
    hero: !!(p.value.hero || photos.value[0]),
    title: !!p.value.hero_title,
    services: (p.value.services || []).length,
    render: ['render', 'done'].includes(currentStep.value) || props.ready,
}));
const heroUrl = computed(() => p.value.hero || photos.value[0] || null);
const accent = computed(() => p.value.color || '#6366f1');

onMounted(() => {
    raf = requestAnimationFrame(tick);
    scriptTimer = setInterval(runScript, 500);
    scanTimer = setInterval(() => { if (photos.value.length) scanIndex.value = (scanIndex.value + 1) % photos.value.length; }, 1400);
    runScript();
});
onUnmounted(() => { cancelAnimationFrame(raf); clearInterval(scriptTimer); clearInterval(scanTimer); clearInterval(typingTimer); });
</script>

<template>
    <section class="relative mx-auto max-w-6xl px-5 py-10 sm:py-14">
        <!-- En-tête + progression -->
        <div class="mb-8 text-center">
            <p class="mb-3 inline-flex items-center gap-2 rounded-full border border-brand-500/30 bg-brand-500/10 px-4 py-1.5 text-xs font-semibold text-brand-200">
                <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-brand-400"></span></span>
                IA en cours de création · {{ Math.round(pct) }} %
            </p>
            <h1 class="font-display text-3xl font-bold text-white sm:text-4xl">{{ ready ? 'Votre site est prêt' : `L'IA construit le site de ${site.name}` }}</h1>
            <p class="mt-2 text-slate-400">{{ ready ? 'Ouverture de l\'aperçu…' : (remaining > 5 ? `Encore environ ${remaining} s · ${Math.round(elapsed)} s écoulées` : 'Finalisation…') }}</p>
            <div class="mx-auto mt-6 h-2.5 max-w-2xl overflow-hidden rounded-full bg-white/[0.06]">
                <div class="gen-bar h-full rounded-full" :style="{ width: pct + '%' }"></div>
            </div>
            <div class="mx-auto mt-3 grid max-w-2xl grid-cols-4 gap-2 text-[11px] font-semibold uppercase tracking-wider">
                <span v-for="(s, i) in [['lookup', (site.source === 'questionnaire' ? 'Vos infos' : (site.source === 'refonte' ? 'Votre site' : 'Fiche Google'))],['photos','Photos'],['content','Rédaction'],['render','Mise en ligne']]" :key="s[0]" :class="PLAN.findIndex(x=>x[0]===currentStep) > i || ready ? 'text-emerald-300' : (currentStep===s[0] ? 'text-white' : 'text-slate-600')">
                    <span v-if="PLAN.findIndex(x=>x[0]===currentStep) > i || ready">✓ </span><span v-else-if="currentStep===s[0]" class="animate-pulse">◐ </span>{{ s[1] }}
                </span>
            </div>
        </div>

        <!-- Capture d'email pendant l'attente (fourni par la page) -->
        <slot name="capture" />

        <div class="grid gap-5 lg:grid-cols-[1.05fr,1fr]">
            <!-- Journal de l'IA -->
            <div class="glass relative overflow-hidden rounded-3xl p-5 sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Journal de l'IA</p>
                    <span class="flex gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-rose-500/70"></i><i class="h-2.5 w-2.5 rounded-full bg-amber-400/70"></i><i class="h-2.5 w-2.5 rounded-full bg-emerald-400/70"></i></span>
                </div>
                <div class="min-h-[18rem] space-y-2 font-mono text-[13px] leading-relaxed">
                    <p v-for="(l, i) in lines" :key="i" class="flex gap-3" :class="l.tone === 'ok' ? 'text-emerald-200' : 'text-slate-300'" :style="{ opacity: 0.45 + (i + 1) / lines.length * 0.55 }">
                        <span class="shrink-0 text-slate-600">{{ String(Math.floor(l.at)).padStart(3, '0') }}s</span><span>{{ l.text }}</span>
                    </p>
                    <p class="flex gap-3 text-white"><span class="shrink-0 text-slate-600">{{ String(Math.floor(elapsed)).padStart(3, '0') }}s</span><span>{{ typing }}<span class="gen-cursor">▌</span></span></p>
                </div>

                <!-- Photos scannées -->
                <div v-if="photos.length" class="mt-5 border-t border-white/[0.06] pt-4">
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Vision · {{ notes.length ? notes.length + ' photos décrites' : 'analyse en cours' }}</p>
                    <div class="grid grid-cols-4 gap-2">
                        <div v-for="(u, i) in photos" :key="u" class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-white/5" :class="{ 'gen-scan': !notes.length && i === scanIndex }">
                            <img :src="u" class="h-full w-full object-cover transition" :class="noteFor(i) ? '' : 'opacity-70 saturate-50'" alt="" />
                            <span v-if="noteFor(i)" class="absolute left-1 top-1 grid h-4 w-4 place-items-center rounded-full bg-emerald-400 text-[9px] font-bold text-ink-950">✓</span>
                            <span v-if="noteFor(i)" class="absolute inset-x-0 bottom-0 line-clamp-2 bg-gradient-to-t from-black/80 to-transparent px-1.5 pb-1 pt-4 text-[10px] leading-tight text-white">{{ noteFor(i) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Site qui se construit -->
            <div class="glass overflow-hidden rounded-3xl p-3 sm:p-4">
                <div class="mb-3 flex items-center gap-2 px-1"><span class="flex gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-white/15"></i><i class="h-2.5 w-2.5 rounded-full bg-white/15"></i><i class="h-2.5 w-2.5 rounded-full bg-white/15"></i></span><span class="ml-2 rounded-md bg-white/5 px-2 py-0.5 text-[11px] text-slate-400">{{ site.slug }}.joow.fr</span></div>
                <div class="relative overflow-hidden rounded-2xl bg-ink-900 text-white" :style="{ '--acc': accent }">
                    <!-- nav -->
                    <div class="flex items-center justify-between px-4 py-3 text-[11px]">
                        <span class="flex items-center gap-2"><span class="h-5 w-5 rounded-md" :class="built.nav ? '' : 'gen-shimmer'" :style="built.nav ? { background: accent } : {}"></span><span v-if="built.nav" class="font-semibold">{{ p.name }}</span><span v-else class="gen-shimmer h-3 w-20 rounded"></span></span>
                        <span class="flex gap-3 text-slate-400"><span class="gen-shimmer h-2.5 w-10 rounded" :class="built.render ? 'opacity-0' : ''"></span><span class="rounded-md px-2 py-1 text-[10px] font-semibold text-white" :style="{ background: accent }">{{ p.sector ? 'Réserver' : '…' }}</span></span>
                    </div>
                    <!-- hero -->
                    <div class="relative h-44 overflow-hidden sm:h-52">
                        <img v-if="heroUrl" :src="heroUrl" class="gen-kb h-full w-full object-cover" alt="" />
                        <div v-else class="gen-shimmer h-full w-full"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-ink-950 via-ink-950/40 to-transparent"></div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <p v-if="p.rating" class="mb-2 inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] backdrop-blur"><span class="text-amber-300">★</span> {{ p.rating }}/5 · {{ fmt(p.reviews_count) }} avis</p>
                            <p v-if="built.title" class="font-display text-xl font-bold leading-tight sm:text-2xl">{{ p.hero_title }}</p>
                            <template v-else><span class="gen-shimmer mb-2 block h-5 w-3/4 rounded"></span><span class="gen-shimmer block h-5 w-1/2 rounded"></span></template>
                            <p v-if="p.tagline" class="mt-1 text-[11px] uppercase tracking-[0.2em]" :style="{ color: accent }">{{ p.tagline }}</p>
                        </div>
                    </div>
                    <!-- services -->
                    <div class="grid grid-cols-3 gap-2 p-3">
                        <div v-for="i in 3" :key="i" class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-2.5">
                            <span class="mb-2 block h-5 w-5 rounded-md" :style="{ background: accent, opacity: built.services >= i ? 1 : 0.25 }"></span>
                            <p v-if="built.services >= i" class="line-clamp-2 text-[11px] font-semibold leading-snug">{{ p.services[i - 1] }}</p>
                            <template v-else><span class="gen-shimmer mb-1 block h-2.5 w-full rounded"></span><span class="gen-shimmer block h-2.5 w-2/3 rounded"></span></template>
                        </div>
                    </div>
                    <!-- bande finale -->
                    <div class="mx-3 mb-3 rounded-xl p-3 text-center text-[11px] font-semibold" :class="built.render ? 'text-white' : 'gen-shimmer text-transparent'" :style="built.render ? { background: `linear-gradient(135deg, ${accent}, #7c3aed)` } : {}">{{ built.render ? (p.sector ? `${p.sector} · ${p.city || ''}` : 'Mise en ligne…') : '…' }}</div>
                    <div v-if="ready" class="absolute inset-0 grid place-items-center bg-ink-950/70 backdrop-blur-sm"><span class="rounded-full bg-emerald-500/20 px-4 py-2 text-sm font-semibold text-emerald-200">✓ Aperçu généré</span></div>
                </div>
                <p class="mt-3 px-1 text-center text-[11px] text-slate-500">{{ built.render ? 'Dernières optimisations avant affichage…' : (site.source === 'questionnaire' ? 'Le site se construit en direct à partir de vos réponses.' : (site.source === 'refonte' ? 'Le site se construit en direct à partir de votre site actuel.' : 'Le site se construit en direct à partir de votre fiche et de vos photos.')) }}</p>
            </div>
        </div>
    </section>
</template>

<style scoped>
.gen-bar { background: linear-gradient(90deg, #6366f1, #a855f7, #38bdf8, #6366f1); background-size: 300% 100%; animation: genBar 2.2s linear infinite; box-shadow: 0 0 24px rgba(129, 140, 248, .55); transition: width .35s ease-out; }
@keyframes genBar { to { background-position: 300% 0; } }
.gen-cursor { animation: genBlink 1s steps(2) infinite; color: #a5b4fc; }
@keyframes genBlink { 50% { opacity: 0; } }
.gen-shimmer { position: relative; overflow: hidden; background: rgba(255,255,255,.06); }
.gen-shimmer::after { content: ""; position: absolute; inset: 0; transform: translateX(-100%); background: linear-gradient(90deg, transparent, rgba(255,255,255,.12), transparent); animation: genShimmer 1.6s infinite; }
@keyframes genShimmer { to { transform: translateX(100%); } }
.gen-scan { outline: 2px solid #a5b4fc; box-shadow: 0 0 0 4px rgba(165,180,252,.25), 0 0 30px rgba(129,140,248,.6); }
.gen-scan::before { content: ""; position: absolute; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, #c7d2fe, transparent); animation: genScan 1.3s ease-in-out infinite; z-index: 2; }
@keyframes genScan { 0% { top: 0; } 100% { top: 100%; } }
.gen-kb { animation: genKb 18s ease-in-out infinite alternate; }
@keyframes genKb { from { transform: scale(1); } to { transform: scale(1.12); } }
@media (prefers-reduced-motion: reduce) { .gen-bar, .gen-shimmer::after, .gen-scan::before, .gen-kb { animation: none; } }
</style>
