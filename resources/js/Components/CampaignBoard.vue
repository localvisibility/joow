<script setup>
/* Campagne créative : un visuel fini par axe (rendu automatique), images IA créées à la volée,
   carrousel narratif, export ZIP de toute la campagne aux 3 formats, lien suivi par visuel. */
import { FORMATS, LAYOUTS, renderAd, renderSlide } from '@/adRender.js';
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({ campaign: Object, brand: Object, slug: String, packId: String, landingUrl: String, campaignName: String });
const emit = defineEmits(['edit', 'usage', 'error', 'landing']);

const cards = ref({});
const slides = ref({});
const busy = ref({});
const zipping = ref(false);
const copied = ref('');
const layoutName = (k) => (LAYOUTS.find((l) => l[0] === k) || [k, k])[1];

const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const api = async (url, body) => {
    const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
    const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.error || d.message || 'Erreur'); return d;
};

const drawAll = async () => {
    await nextTick();
    for (const c of props.campaign?.creatives || []) if (cards.value[c.key]) await renderAd(cards.value[c.key], c, props.brand, 'portrait');
    const sl = props.campaign?.carousel?.slides || [];
    for (let i = 0; i < sl.length; i++) if (slides.value[i]) await renderSlide(slides.value[i], sl[i], i, sl.length, props.brand, props.campaign.carousel.image, props.campaign.creatives?.[0]?.cta);
};

const genImage = async (c) => {
    busy.value[c.key] = true;
    try {
        const d = await api(route('ads.visual', props.slug), { brief: c.scene || c.concept || c.hook, format: 'portrait', pack_id: props.packId, creative: c.key });
        c.image = d.url; c.use_ai_image = false; emit('usage', d.usage);
        await renderAd(cards.value[c.key], c, props.brand, 'portrait');
    } catch (e) { emit('error', e.message); } finally { busy.value[c.key] = false; }
};

// Images IA des concepts qui en demandent une (l'une après l'autre)
const autoImages = async () => {
    for (const c of props.campaign?.creatives || []) {
        if (c.use_ai_image && !c.image) { await genImage(c); if (busy.value.stop) break; }
    }
};

const trackUrl = (c) => {
    const base = props.landingUrl || '';
    const p = new URLSearchParams({ c: c.key, utm_source: 'meta', utm_medium: 'paid', utm_campaign: (props.campaignName || 'joow-ads').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-'), utm_content: c.key + '-' + c.layout });
    return base + (base.includes('?') ? '&' : '?') + p.toString();
};
const copy = async (t, k) => { try { await navigator.clipboard.writeText(t); copied.value = k; setTimeout(() => (copied.value = ''), 1600); } catch { prompt('Copiez :', t); } };

const zipAll = async () => {
    zipping.value = true;
    try {
        const { default: JSZip } = await import('jszip');
        const zip = new JSZip(); const cv = document.createElement('canvas');
        const blob = () => new Promise((r) => cv.toBlob(r, 'image/png'));
        for (const c of props.campaign.creatives) {
            for (const f of Object.keys(FORMATS)) { await renderAd(cv, c, props.brand, f); zip.file(`${c.key}-${c.layout}/${FORMATS[f][0]}x${FORMATS[f][1]}.png`, await blob()); }
            zip.file(`${c.key}-${c.layout}/texte.txt`, `Accroche : ${c.hook}\nSous-titre : ${c.sub || ''}\nAxe : ${c.axis}\nIdée : ${c.concept}\nLien : ${trackUrl(c)}\n`);
        }
        const sl = props.campaign.carousel?.slides || [];
        for (let i = 0; i < sl.length; i++) { await renderSlide(cv, sl[i], i, sl.length, props.brand, props.campaign.carousel.image, props.campaign.creatives?.[0]?.cta); zip.file(`carrousel/${i + 1}.png`, await blob()); }
        const out = await zip.generateAsync({ type: 'blob' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(out); a.download = `campagne-${(props.brand?.name || 'joow').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.zip`; a.click();
    } catch (e) { emit('error', 'Export impossible : une photo est protégée par son site d\'origine. Remplacez-la par une photo du site ou une image IA.'); } finally { zipping.value = false; }
};

const landingMatch = () => Object.fromEntries((props.campaign.creatives || []).map((c) => [c.key, { headline: c.hook, sub: c.sub, image: c.image }]));

watch(() => props.campaign, drawAll, { deep: true });
onMounted(async () => { await drawAll(); autoImages(); });
defineExpose({ landingMatch, trackUrl });
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p v-if="campaign.landing_hint" class="max-w-2xl text-sm text-slate-400">🎯 <b class="text-slate-200">Promesse de la campagne :</b> {{ campaign.landing_hint }}</p>
            <div class="flex flex-wrap gap-2">
                <button @click="emit('landing', landingMatch())" class="rounded-xl border border-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">🎯 Créer la page d'offre de la campagne</button>
                <button @click="zipAll" :disabled="zipping" class="btn-brand text-sm">{{ zipping ? 'Préparation…' : '⬇ Toute la campagne (ZIP)' }}</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="c in campaign.creatives" :key="c.key" class="glass flex flex-col overflow-hidden rounded-2xl">
                <div class="relative bg-black/30">
                    <canvas :ref="(el) => (cards[c.key] = el)" class="block h-auto w-full"></canvas>
                    <div v-if="busy[c.key]" class="absolute inset-0 grid place-items-center bg-black/60 text-sm font-semibold text-white">✨ Création de l'image…</div>
                </div>
                <div class="flex flex-1 flex-col p-4 text-sm">
                    <div class="flex flex-wrap gap-1.5"><span class="chip bg-brand-500/20 text-brand-200">{{ c.axis }}</span><span class="chip bg-white/10 text-slate-300">{{ layoutName(c.layout) }}</span></div>
                    <p class="mt-2 text-slate-300">{{ c.concept }}</p>
                    <div class="mt-auto flex flex-wrap gap-2 pt-3">
                        <button @click="emit('edit', c)" class="rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">✏️ Modifier</button>
                        <button @click="genImage(c)" :disabled="busy[c.key]" class="rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10" :title="c.scene">✨ {{ c.image ? 'Nouvelle image IA' : 'Créer l\'image IA' }}</button>
                        <button v-if="landingUrl" @click="copy(trackUrl(c), c.key)" class="rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">{{ copied === c.key ? 'Lien copié ✓' : '🔗 Lien de la pub' }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="campaign.carousel?.slides?.length" class="glass rounded-2xl p-5">
            <p class="font-semibold text-white">🎠 Carrousel ({{ campaign.carousel.slides.length }} vignettes)</p>
            <p class="mt-1 text-xs text-slate-500">Une histoire qui se fait défiler : problème → solution → preuve → offre. Format idéal pour Facebook et Instagram.</p>
            <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
                <canvas v-for="(sl, i) in campaign.carousel.slides" :key="i" :ref="(el) => (slides[i] = el)" class="h-48 w-48 shrink-0 rounded-xl"></canvas>
            </div>
        </div>
    </div>
</template>
