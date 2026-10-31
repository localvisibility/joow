<script setup>
/* Campagne créative : pour chaque concept, la pub finale créée par l'IA image Pro (4:5), déclinable en 1:1 et 9:16,
   avec son insight, sa raison de convertir, son contrôle qualité et son texte de publication. Carrousel, export ZIP, lien suivi. */
import { FORMATS, renderAd, renderSlide } from '@/adRender.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ campaign: Object, brand: Object, slug: String, packId: String, landingUrl: String, campaignName: String });
const emit = defineEmits(['edit', 'usage', 'error', 'landing']);

const view = ref({});           // format affiché par concept
const slides = ref({});
const zipping = ref(false);
const copied = ref('');
const FMT = [['portrait', '4:5'], ['square', '1:1'], ['story', '9:16']];
const done = computed(() => (props.campaign?.creatives || []).filter((c) => c.renders?.portrait).length);

const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const api = async (url, body) => {
    const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
    const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.error || d.message || 'Erreur'); return d;
};

// Les pubs sont créées en arrière-plan (≈ 1 min chacune) : on lance, puis on suit l'avancement toutes les 4 s.
const jobOf = (c, f = 'portrait') => c.jobs?.[f];
const busyOf = (c) => FMT.map(([f]) => f).find((f) => jobOf(c, f)?.status === 'running') || null;
const errorOf = (c) => Object.values(c.jobs || {}).find((j) => j?.status === 'error')?.error || '';
const detailOf = (c) => Object.values(c.jobs || {}).find((j) => j?.status === 'error')?.detail || '';
const running = () => (props.campaign?.creatives || []).some((c) => busyOf(c));
let timer = null; let auto = false;

const render = async (c, format = 'portrait', redo = false) => {
    c.jobs = { ...(c.jobs || {}), [format]: { status: 'running' } };
    try {
        await api(route('ads.creative', props.slug), { pack_id: props.packId, key: c.key, format, redo });
        view.value[c.key] = format; poll();
    } catch (e) { c.jobs = { ...(c.jobs || {}), [format]: { status: 'error', error: e.message } }; throw e; }
};
const decline = async (c) => { auto = false; for (const f of ['square', 'story']) if (!c.renders?.[f]) { try { await render(c, f); } catch { break; } } };

const sync = async () => {
    const r = await fetch(route('ads.campaign.state', [props.slug, props.packId]), { headers: { Accept: 'application/json' } });
    if (!r.ok) return;
    const d = await r.json();
    for (const s of d.creatives || []) { const c = props.campaign.creatives.find((x) => x.key === s.key); if (c) { c.renders = s.renders || {}; c.jobs = s.jobs || {}; } }
    emit('usage', d.usage);
};
const next = () => {
    if (!auto || running()) return;
    const c = (props.campaign?.creatives || []).find((x) => !x.renders?.portrait && !jobOf(x));
    if (c) render(c, 'portrait').catch(() => { auto = false; }); else auto = false;
};
const poll = () => {
    if (timer) return;
    timer = setInterval(async () => {
        try { await sync(); } catch { /* réseau : on réessaie au prochain tour */ }
        next();
        if (!running() && !auto) { clearInterval(timer); timer = null; }
    }, 4000);
};
// Création automatique des pubs principales, l'une après l'autre
const autoRender = () => { auto = true; next(); if (running()) poll(); };
const queued = computed(() => auto || running());
onBeforeUnmount(() => clearInterval(timer));

const drawSlides = async () => {
    await nextTick();
    const sl = props.campaign?.carousel?.slides || [];
    for (let i = 0; i < sl.length; i++) if (slides.value[i]) await renderSlide(slides.value[i], sl[i], i, sl.length, props.brand, props.campaign.carousel.image, props.campaign.creatives?.[0]?.cta);
};

const trackUrl = (c) => {
    const base = props.landingUrl || '';
    const p = new URLSearchParams({ c: c.key, utm_source: 'meta', utm_medium: 'paid', utm_campaign: (props.campaignName || 'joow-ads').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-'), utm_content: c.key + '-' + (c.axis || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z]+/gi, '-') });
    return base + (base.includes('?') ? '&' : '?') + p.toString();
};
const copy = async (t, k) => { try { await navigator.clipboard.writeText(t); copied.value = k; setTimeout(() => (copied.value = ''), 1600); } catch { prompt('Copiez :', t); } };
const shown = (c) => c.renders?.[view.value[c.key] || 'portrait'] || c.renders?.portrait;

const zipAll = async () => {
    zipping.value = true;
    try {
        const { default: JSZip } = await import('jszip');
        const zip = new JSZip(); const cv = document.createElement('canvas');
        const blob = () => new Promise((r) => cv.toBlob(r, 'image/png'));
        for (const c of props.campaign.creatives) {
            const dir = `${c.key}-${(c.axis || 'pub').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '-')}`;
            for (const [f] of FMT) {
                if (c.renders?.[f]) { const r = await fetch(c.renders[f].url); zip.file(`${dir}/${FORMATS[f][0]}x${FORMATS[f][1]}.jpg`, await r.blob()); }
                else if (!c.renders?.portrait) { await renderAd(cv, { ...c, hook: c.headline || c.hook, offer: c.badge }, props.brand, f); zip.file(`${dir}/${FORMATS[f][0]}x${FORMATS[f][1]}-gabarit.png`, await blob()); }
            }
            zip.file(`${dir}/publication.txt`, `Texte de la publication :\n${c.primary_text || ''}\n\nTitre : ${c.title || c.headline || ''}\nBouton : ${c.cta || ''}\nLien : ${trackUrl(c)}\n\nAxe : ${c.axis}\nInsight : ${c.insight || ''}\nIdée : ${c.concept}\nPourquoi ça convertit : ${c.why || ''}\n`);
        }
        const sl = props.campaign.carousel?.slides || [];
        for (let i = 0; i < sl.length; i++) { await renderSlide(cv, sl[i], i, sl.length, props.brand, props.campaign.carousel.image, props.campaign.creatives?.[0]?.cta); zip.file(`carrousel/${i + 1}.png`, await blob()); }
        const out = await zip.generateAsync({ type: 'blob' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(out); a.download = `campagne-${(props.brand?.name || 'joow').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-')}.zip`; a.click();
    } catch { emit('error', 'Export impossible pour le moment, réessayez.'); } finally { zipping.value = false; }
};

const landingMatch = () => Object.fromEntries((props.campaign.creatives || []).map((c) => [c.key, { headline: c.headline || c.hook, sub: c.sub, image: c.refs?.[0] || c.image }]));
onMounted(async () => { await drawSlides(); autoRender(); });
defineExpose({ landingMatch, trackUrl });
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="max-w-2xl text-sm text-slate-400">
                <p v-if="campaign.landing_hint">🎯 <b class="text-slate-200">Promesse :</b> {{ campaign.landing_hint }}</p>
                <p v-if="queued" class="mt-1 text-brand-300">✨ Création des pubs par l'IA… {{ done }} / {{ campaign.creatives.length }} prêtes (≈ 1 min chacune, contrôle qualité compris — vous pouvez quitter la page, elles continuent)</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button @click="emit('landing', landingMatch())" class="rounded-xl border border-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">🎯 Créer la page d'offre de la campagne</button>
                <button @click="zipAll" :disabled="zipping" class="btn-brand text-sm">{{ zipping ? 'Préparation…' : '⬇ Toute la campagne (ZIP)' }}</button>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="c in campaign.creatives" :key="c.key" class="glass flex flex-col overflow-hidden rounded-2xl">
                <div class="relative grid min-h-[22rem] place-items-center bg-black/40">
                    <img v-if="shown(c)" :src="shown(c).url" class="block h-auto max-h-[34rem] w-full object-contain" alt="" />
                    <div v-else class="p-6 text-center text-sm text-slate-400"><template v-if="!busyOf(c)"><p v-if="errorOf(c)" class="text-rose-300">{{ errorOf(c) }}</p><p v-if="detailOf(c)" class="mt-2 break-words font-mono text-[11px] text-slate-500">Diagnostic (admin) : {{ detailOf(c) }}</p><p v-else>Pub pas encore créée</p></template></div>
                    <div v-if="busyOf(c)" class="absolute inset-0 grid place-items-center bg-black/70 p-6 text-center text-sm font-semibold text-white">
                        <div><div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-white/30 border-t-white"></div>{{ busyOf(c) === 'portrait' ? 'Création de la pub…' : 'Déclinaison ' + FMT.find((f) => f[0] === busyOf(c))[1] + '…' }}<p class="mt-1 text-xs font-normal text-slate-300">Direction artistique, typographie, puis contrôle qualité</p></div>
                    </div>
                    <span v-if="shown(c)?.qa?.score" class="absolute right-3 top-3 rounded-full px-2.5 py-1 text-xs font-bold" :class="shown(c).qa.score >= 8 ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-ink-950'" :title="(shown(c).qa.issues || []).join(' · ')">Contrôle {{ shown(c).qa.score }}/10</span>
                </div>
                <div v-if="c.renders?.portrait" class="flex gap-1 border-b border-white/[0.06] px-4 py-2">
                    <button v-for="[f, l] in FMT" :key="f" @click="c.renders?.[f] ? (view[c.key] = f) : null" class="rounded-md px-2 py-1 text-xs font-semibold" :class="(view[c.key] || 'portrait') === f ? 'bg-brand-500 text-white' : c.renders?.[f] ? 'bg-white/[0.06] text-slate-300' : 'text-slate-600'">{{ l }}</button>
                    <button v-if="!c.renders?.square || !c.renders?.story" @click="decline(c)" :disabled="!!busyOf(c)" class="ml-auto rounded-md bg-white/[0.06] px-2 py-1 text-xs font-semibold text-slate-200">Décliner 1:1 + 9:16</button>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-4 text-sm">
                    <p v-if="errorOf(c) && shown(c)" class="rounded-lg bg-rose-500/10 px-3 py-2 text-xs text-rose-300">{{ errorOf(c) }}</p>
                    <div class="flex flex-wrap gap-1.5"><span class="chip bg-brand-500/20 text-brand-200">{{ c.axis }}</span></div>
                    <p class="font-semibold text-white">{{ c.concept }}</p>
                    <p v-if="c.insight" class="text-xs text-slate-400"><b class="text-slate-300">Insight :</b> {{ c.insight }}</p>
                    <p v-if="c.why" class="text-xs text-emerald-300/90"><b>Pourquoi ça convertit :</b> {{ c.why }}</p>
                    <details v-if="c.primary_text" class="rounded-lg bg-white/[0.03] px-3 py-2 text-xs text-slate-300"><summary class="cursor-pointer font-semibold text-slate-200">Texte de la publication</summary><p class="mt-2 whitespace-pre-line">{{ c.primary_text }}</p>
                        <button @click="copy(c.primary_text, 't' + c.key)" class="mt-2 text-brand-300">{{ copied === 't' + c.key ? 'Copié ✓' : 'Copier le texte' }}</button></details>
                    <div class="mt-auto flex flex-wrap gap-2 pt-2">
                        <button @click="render(c, 'portrait', true).catch(() => {})" :disabled="!!busyOf(c)" class="rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">{{ c.renders?.portrait ? '↻ Nouvelle version' : '✨ Créer la pub' }}</button>
                        <a v-if="shown(c)" :href="shown(c).url" target="_blank" download class="rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">⬇ Image</a>
                        <button v-if="landingUrl" @click="copy(trackUrl(c), c.key)" class="rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">{{ copied === c.key ? 'Lien copié ✓' : '🔗 Lien' }}</button>
                        <button @click="emit('edit', { ...c, hook: c.headline || c.hook, offer: c.badge, image: c.refs?.[0] || c.image })" class="rounded-lg px-3 py-1.5 text-xs text-slate-400 hover:text-white" title="Retoucher le texte dans l'éditeur de mise en page">✏️ Gabarit</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="campaign.carousel?.slides?.length" class="glass rounded-2xl p-5">
            <p class="font-semibold text-white">🎠 Carrousel ({{ campaign.carousel.slides.length }} vignettes)</p>
            <p class="mt-1 text-xs text-slate-500">Une histoire qui se fait défiler : problème → solution → preuve → offre.</p>
            <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
                <canvas v-for="(sl, i) in campaign.carousel.slides" :key="i" :ref="(el) => (slides[i] = el)" class="h-48 w-48 shrink-0 rounded-xl"></canvas>
            </div>
        </div>
    </div>
</template>
