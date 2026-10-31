<script setup>
/* Mise en page de visuels publicitaires (canvas) : photo + accroche + offre + note + bouton,
   aux formats Meta 1:1 (1080×1080), 4:5 (1080×1350) et story 9:16 (1080×1920). Rendu dans le navigateur, gratuit. */
import { nextTick, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({ images: Array, brand: Object, ads: Array });
const FORMATS = { square: [1080, 1080, '1:1 · fil Facebook / Instagram'], portrait: [1080, 1350, '4:5 · fil mobile (recommandé)'], story: [1080, 1920, '9:16 · Stories / Reels'] };
const STYLES = [['impact', 'Impact'], ['offre', 'Bandeau offre'], ['avis', 'Avis clients'], ['epure', 'Épuré']];

const s = reactive({ image: props.images?.[0] || '', hook: props.ads?.[0]?.hook || '', offer: '', cta: props.ads?.[0]?.cta || 'En savoir plus', style: 'impact', format: 'portrait', showRating: true });
const canvas = ref(null);
const err = ref('');
const cache = {};

const loadImg = (src) => new Promise((res, rej) => {
    if (!src) return res(null);
    if (cache[src]) return res(cache[src]);
    const im = new Image(); im.crossOrigin = 'anonymous';
    im.onload = () => { cache[src] = im; res(im); }; im.onerror = rej; im.src = src;
});
const cover = (ctx, im, x, y, w, h) => {
    if (!im) { ctx.fillStyle = '#1e293b'; ctx.fillRect(x, y, w, h); return; }
    const r = Math.max(w / im.width, h / im.height); const iw = im.width * r, ih = im.height * r;
    ctx.save(); ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip(); ctx.drawImage(im, x + (w - iw) / 2, y + (h - ih) / 2, iw, ih); ctx.restore();
};
const wrap = (ctx, text, maxW) => {
    const words = String(text || '').split(/[ \t\n]+/).filter(Boolean); const lines = []; let line = '';
    for (const w of words) { const t = line ? line + ' ' + w : w; if (ctx.measureText(t).width > maxW && line) { lines.push(line); line = w; } else line = t; }
    if (line) lines.push(line); return lines;
};
const fitText = (ctx, text, maxW, maxLines, start, min, weight = 900) => {
    let size = start, lines;
    do { ctx.font = `${weight} ${size}px Inter, system-ui, sans-serif`; lines = wrap(ctx, text, maxW); size -= 4; } while (lines.length > maxLines && size > min);
    return { lines, size: size + 4 };
};
const rrect = (ctx, x, y, w, h, r) => { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); };
const pill = (ctx, text, x, y, { bg, fg, size = 38, padX = 34, h = 84, weight = 800, align = 'left' }) => {
    ctx.font = `${weight} ${size}px Inter, system-ui, sans-serif`; const w = ctx.measureText(text).width + padX * 2;
    const left = align === 'center' ? x - w / 2 : x;
    ctx.fillStyle = bg; rrect(ctx, left, y, w, h, h / 2); ctx.fill();
    ctx.fillStyle = fg; ctx.textBaseline = 'middle'; ctx.fillText(text, left + padX, y + h / 2 + 2); ctx.textBaseline = 'alphabetic';
    return w;
};
const ratingText = () => (props.brand?.rating && props.brand?.reviews ? `★ ${String(props.brand.rating).replace('.', ',')}/5 · ${props.brand.reviews} avis Google` : '');

const draw = async () => {
    const cv = canvas.value; if (!cv) return;
    const [W, H] = FORMATS[s.format]; cv.width = W; cv.height = H;
    const ctx = cv.getContext('2d'); err.value = '';
    let im = null; try { im = await loadImg(s.image); } catch { err.value = 'Photo inaccessible : choisissez-en une autre.'; }
    try { await document.fonts?.ready; } catch {}
    const C = props.brand?.color || '#4f46e5', P = 72, story = s.format === 'story';
    const topSafe = story ? 220 : P, botSafe = story ? 300 : P;
    ctx.clearRect(0, 0, W, H);

    if (s.style === 'impact') {
        cover(ctx, im, 0, 0, W, H);
        const g = ctx.createLinearGradient(0, H * 0.3, 0, H); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(0.55, 'rgba(0,0,0,.6)'); g.addColorStop(1, 'rgba(0,0,0,.88)'); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        pill(ctx, props.brand?.name || '', P, topSafe, { bg: 'rgba(255,255,255,.95)', fg: '#0f172a', size: 32, h: 72 });
        let y = H - botSafe - 96;
        pill(ctx, s.cta + '  →', P, y, { bg: '#fff', fg: C, size: 38, h: 96 });
        y -= 34;
        if (s.offer) { const o = fitText(ctx, s.offer, W - P * 2 - 60, 2, 44, 30, 800); y -= o.lines.length * o.size * 1.2 + 44; ctx.fillStyle = C; rrect(ctx, P, y, W - P * 2, o.lines.length * o.size * 1.2 + 40, 26); ctx.fill(); ctx.fillStyle = '#fff'; o.lines.forEach((l, i) => ctx.fillText(l, P + 30, y + 20 + o.size + i * o.size * 1.2 - 6)); y -= 28; }
        const t = fitText(ctx, s.hook, W - P * 2, story ? 5 : 4, story ? 104 : 92, 52);
        y -= t.lines.length * t.size * 1.08; ctx.fillStyle = '#fff'; t.lines.forEach((l, i) => ctx.fillText(l, P, y + t.size + i * t.size * 1.08));
        if (s.showRating && ratingText()) { ctx.font = '700 32px Inter, system-ui, sans-serif'; ctx.fillStyle = '#fde68a'; ctx.fillText(ratingText(), P, y - 18); }
    } else if (s.style === 'offre') {
        const ph = Math.round(H * (story ? 0.55 : 0.56)); cover(ctx, im, 0, 0, W, ph);
        pill(ctx, props.brand?.name || '', P, topSafe, { bg: 'rgba(255,255,255,.95)', fg: '#0f172a', size: 32, h: 72 });
        ctx.fillStyle = C; ctx.fillRect(0, ph, W, H - ph);
        // Bouton réservé en bas, textes ajustés dans l'espace restant (jamais de chevauchement)
        const btnY = H - botSafe - 96, top = ph + 64, room = btnY - 40 - top;
        let hs = 64, os = 96, t, o, need;
        do {
            t = fitText(ctx, s.hook, W - P * 2, 3, hs, 30, 800);
            o = s.offer ? fitText(ctx, s.offer, W - P * 2, 3, os, 40) : { lines: [], size: 0 };
            need = t.lines.length * t.size * 1.12 + (o.lines.length ? 24 + o.lines.length * o.size * 1.06 : 0);
            hs -= 4; os -= 6;
        } while (need > room && hs > 30);
        let y = top;
        ctx.font = `800 ${t.size}px Inter, system-ui, sans-serif`; ctx.fillStyle = 'rgba(255,255,255,.85)'; t.lines.forEach((l, i) => ctx.fillText(l, P, y + t.size + i * t.size * 1.12)); y += t.lines.length * t.size * 1.12 + 24;
        if (o.lines.length) { ctx.font = `900 ${o.size}px Inter, system-ui, sans-serif`; ctx.fillStyle = '#fff'; o.lines.forEach((l, i) => ctx.fillText(l, P, y + o.size * 0.9 + i * o.size * 1.06)); }
        pill(ctx, s.cta + '  →', P, btnY, { bg: '#fff', fg: C, size: 38, h: 96 });
        if (s.showRating && ratingText()) { ctx.font = '700 30px Inter, system-ui, sans-serif'; ctx.fillStyle = '#fff'; ctx.textAlign = 'right'; ctx.fillText(ratingText(), W - P, ph - 40); ctx.textAlign = 'left'; }
    } else if (s.style === 'avis') {
        cover(ctx, im, 0, 0, W, H); ctx.fillStyle = 'rgba(15,23,42,.45)'; ctx.fillRect(0, 0, W, H);
        const cw = W - P * 2; ctx.font = '900 64px Inter, system-ui, sans-serif';
        const t = fitText(ctx, '« ' + String(s.hook).trim().replace(/\s+(\S+)$/, '\u00a0$1') + '\u00a0»', cw - 100, 5, 66, 40, 800);
        const ch = 140 + t.lines.length * t.size * 1.18 + 150; const cy = (H - ch) / 2;
        ctx.fillStyle = '#fff'; rrect(ctx, P, cy, cw, ch, 40); ctx.fill();
        ctx.fillStyle = '#f59e0b'; ctx.font = '900 56px Inter, system-ui, sans-serif'; ctx.fillText('★★★★★', P + 50, cy + 100);
        ctx.fillStyle = '#0f172a'; ctx.font = `800 ${t.size}px Inter, system-ui, sans-serif`; t.lines.forEach((l, i) => ctx.fillText(l, P + 50, cy + 150 + t.size + i * t.size * 1.18 - 10));
        ctx.fillStyle = '#64748b'; ctx.font = '700 32px Inter, system-ui, sans-serif'; ctx.fillText((props.brand?.name || '') + (ratingText() ? '  ·  ' + ratingText().replace('★ ', '') : ''), P + 50, cy + ch - 56);
        pill(ctx, s.cta + '  →', W / 2, Math.min(cy + ch + 50, H - botSafe - 96), { bg: C, fg: '#fff', size: 38, h: 96, align: 'center' });
    } else {
        const band = Math.round(H * (story ? 0.3 : 0.32)); cover(ctx, im, 0, 0, W, H - band);
        ctx.fillStyle = '#fff'; ctx.fillRect(0, H - band, W, band);
        ctx.fillStyle = C; ctx.fillRect(P, H - band + 50, 90, 10);
        const t = fitText(ctx, s.hook, W - P * 2, 3, 74, 42, 900); ctx.fillStyle = '#0f172a'; t.lines.forEach((l, i) => ctx.fillText(l, P, H - band + 100 + t.size * 0.8 + i * t.size * 1.08));
        ctx.font = '700 34px Inter, system-ui, sans-serif'; ctx.fillStyle = C; ctx.fillText((s.offer ? s.offer + '  ·  ' : '') + s.cta + ' →', P, H - (story ? 230 : 60));
        pill(ctx, props.brand?.name || '', P, topSafe, { bg: 'rgba(255,255,255,.95)', fg: '#0f172a', size: 32, h: 72 });
    }
};

let t = null;
watch(s, () => { clearTimeout(t); t = setTimeout(draw, 120); }, { deep: true });
watch(() => props.images, (v) => { if (!s.image && v?.length) s.image = v[0]; });
onMounted(() => nextTick(draw));

const useAd = (ad) => { s.hook = ad.hook; s.cta = ad.cta || s.cta; if (ad.image) s.image = ad.image; };
const download = async (format = null) => {
    const formats = format ? [format] : Object.keys(FORMATS); const keep = s.format;
    for (const f of formats) {
        s.format = f; await draw();
        try {
            const a = document.createElement('a'); a.href = canvas.value.toDataURL('image/png');
            a.download = `${(props.brand?.name || 'pub').toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${s.style}-${FORMATS[f][0]}x${FORMATS[f][1]}.png`; a.click();
            await new Promise((r) => setTimeout(r, 350));
        } catch { err.value = 'Cette photo ne peut pas être exportée (protégée par son site d\'origine). Choisissez une photo du site ou un visuel IA.'; break; }
    }
    s.format = keep;
};
defineExpose({ useAd, setImage: (u) => (s.image = u) });
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
        <div class="space-y-4">
            <div v-if="ads?.length">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Partir d'une pub générée</p>
                <div class="mt-2 flex flex-wrap gap-2"><button v-for="(a, i) in ads" :key="i" @click="useAd(a)" class="max-w-[16rem] truncate rounded-lg bg-white/[0.06] px-3 py-1.5 text-left text-xs text-slate-200 hover:bg-white/10">{{ a.hook }}</button></div>
            </div>
            <label class="block text-xs font-semibold text-slate-400">Accroche<textarea v-model="s.hook" rows="2" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"></textarea></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-xs font-semibold text-slate-400">Offre (facultatif)<input v-model="s.offer" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" placeholder="Devis offert" /></label>
                <label class="block text-xs font-semibold text-slate-400">Bouton<input v-model="s.cta" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400">Style</p>
                <div class="mt-1 flex flex-wrap gap-2"><button v-for="[k, l] in STYLES" :key="k" @click="s.style = k" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="s.style === k ? 'bg-brand-500 text-white' : 'bg-white/[0.06] text-slate-300'">{{ l }}</button></div>
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
