<script setup>
/* Mise en page d'un visuel publicitaire (photo + accroche + offre + note + bouton), aux formats Meta.
   Rendu dans le navigateur avec le moteur commun adRender.js : gratuit et illimité. */
import { FORMATS, LAYOUTS, renderAd } from '@/adRender.js';
import { nextTick, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({ images: Array, brand: Object, ads: Array });
const s = reactive({ layout: 'impact', image: props.images?.[0] || '', hook: props.ads?.[0]?.hook || '', sub: '', offer: '', cta: props.ads?.[0]?.cta || 'En savoir plus', stat: '', problem: '', review: null, showRating: true, format: 'portrait' });
const canvas = ref(null);
const err = ref('');

const draw = async () => { if (canvas.value) err.value = await renderAd(canvas.value, s, props.brand, s.format); };
let t = null;
watch(s, () => { clearTimeout(t); t = setTimeout(draw, 120); }, { deep: true });
watch(() => props.images, (v) => { if (!s.image && v?.length) s.image = v[0]; });
onMounted(() => nextTick(draw));

const useAd = (ad) => { Object.assign(s, { hook: ad.hook, cta: ad.cta || s.cta }); if (ad.image) s.image = ad.image; };
const setSpec = (c) => Object.assign(s, { layout: c.layout || 'impact', image: c.image || s.image, hook: c.hook || '', sub: c.sub || '', offer: c.offer || '', cta: c.cta || s.cta, stat: c.stat || '', problem: c.problem || '', review: c.review || null });
const slug = () => (props.brand?.name || 'pub').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-');
const download = async (format = null) => {
    const formats = format ? [format] : Object.keys(FORMATS); const keep = s.format;
    for (const f of formats) {
        await renderAd(canvas.value, s, props.brand, f);
        try {
            const a = document.createElement('a'); a.href = canvas.value.toDataURL('image/png');
            a.download = `${slug()}-${s.layout}-${FORMATS[f][0]}x${FORMATS[f][1]}.png`; a.click();
            await new Promise((r) => setTimeout(r, 350));
        } catch { err.value = 'Cette photo ne peut pas être exportée (protégée par son site d\'origine). Choisissez une photo du site ou un visuel IA.'; break; }
    }
    s.format = keep; draw();
};
defineExpose({ useAd, setSpec, setImage: (u) => (s.image = u) });
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
        <div class="space-y-4">
            <div v-if="ads?.length">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Partir d'une pub générée</p>
                <div class="mt-2 flex flex-wrap gap-2"><button v-for="(a, i) in ads" :key="i" @click="useAd(a)" class="max-w-[16rem] truncate rounded-lg bg-white/[0.06] px-3 py-1.5 text-left text-xs text-slate-200 hover:bg-white/10">{{ a.hook }}</button></div>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400">Mise en page</p>
                <div class="mt-1 flex flex-wrap gap-2"><button v-for="[k, l] in LAYOUTS" :key="k" @click="s.layout = k" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="s.layout === k ? 'bg-brand-500 text-white' : 'bg-white/[0.06] text-slate-300'">{{ l }}</button></div>
            </div>
            <label class="block text-xs font-semibold text-slate-400">Accroche<textarea v-model="s.hook" rows="2" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"></textarea></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-xs font-semibold text-slate-400">Sous-titre<input v-model="s.sub" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                <label class="block text-xs font-semibold text-slate-400">Bouton<input v-model="s.cta" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                <label class="block text-xs font-semibold text-slate-400">Offre<input v-model="s.offer" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" placeholder="Devis offert" /></label>
                <label v-if="s.layout === 'chiffre'" class="block text-xs font-semibold text-slate-400">Chiffre clé<input v-model="s.stat" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" placeholder="-60 %" /></label>
                <label v-if="s.layout === 'probleme'" class="block text-xs font-semibold text-slate-400">Le problème<input v-model="s.problem" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" placeholder="Factures de chauffage qui explosent" /></label>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400">Format</p>
                <div class="mt-1 flex flex-wrap gap-2"><button v-for="(f, k) in FORMATS" :key="k" @click="s.format = k" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="s.format === k ? 'bg-brand-500 text-white' : 'bg-white/[0.06] text-slate-300'">{{ f[2] }}</button></div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" v-model="s.showRating" class="rounded border-white/20 bg-white/5" /> Afficher la note Google</label>
            <div>
                <p class="text-xs font-semibold text-slate-400">Photo</p>
                <div class="mt-2 grid max-h-60 grid-cols-4 gap-2 overflow-y-auto pr-1">
                    <button v-for="u in images" :key="u" @click="s.image = u" class="overflow-hidden rounded-lg ring-2" :class="s.image === u ? 'ring-brand-400' : 'ring-transparent'"><img :src="u" class="aspect-square w-full object-cover" alt="" loading="lazy" /></button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button @click="download(s.format)" class="btn-brand text-sm">⬇ Télécharger ce format</button>
                <button @click="download()" class="rounded-xl border border-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">⬇ Les 3 formats</button>
            </div>
            <p v-if="err" class="text-sm text-rose-300">{{ err }}</p>
        </div>
        <div class="flex items-start justify-center rounded-2xl bg-black/30 p-4">
            <canvas ref="canvas" class="h-auto max-h-[70vh] w-auto max-w-full rounded-xl shadow-2xl"></canvas>
        </div>
    </div>
</template>
