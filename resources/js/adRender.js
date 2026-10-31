/* Moteur de rendu des visuels publicitaires Joow Ads (canvas, dans le navigateur).
   spec = { layout, image, hook, sub, offer, cta, stat, problem, review: {text, author, rating}, showRating }
   brand = { name, color, rating, reviews } */

export const FORMATS = { square: [1080, 1080, '1:1 · fil Facebook / Instagram'], portrait: [1080, 1350, '4:5 · fil mobile (recommandé)'], story: [1080, 1920, '9:16 · Stories / Reels'] };
export const LAYOUTS = [['impact', 'Impact'], ['offre', 'Bandeau offre'], ['chiffre', 'Chiffre clé'], ['probleme', 'Problème → solution'], ['question', 'Question'], ['avis', 'Avis client'], ['ugc', 'Photo client'], ['epure', 'Épuré']];

const FONT = 'Inter, system-ui, sans-serif';
const cache = {};

export const loadImg = (src) => new Promise((res, rej) => {
    if (!src) return res(null);
    if (cache[src]) return res(cache[src]);
    const im = new Image(); im.crossOrigin = 'anonymous';
    im.onload = () => { cache[src] = im; res(im); }; im.onerror = rej; im.src = src;
});

const cover = (ctx, im, x, y, w, h) => {
    if (!im) { const g = ctx.createLinearGradient(x, y, x + w, y + h); g.addColorStop(0, '#1e293b'); g.addColorStop(1, '#0f172a'); ctx.fillStyle = g; ctx.fillRect(x, y, w, h); return; }
    const r = Math.max(w / im.width, h / im.height); const iw = im.width * r, ih = im.height * r;
    ctx.save(); ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip(); ctx.drawImage(im, x + (w - iw) / 2, y + (h - ih) / 2, iw, ih); ctx.restore();
};
const wrap = (ctx, text, maxW) => {
    const words = String(text || '').split(/[ \t\n]+/).filter(Boolean); const lines = []; let line = '';
    for (const w of words) { const t = line ? line + ' ' + w : w; if (ctx.measureText(t).width > maxW && line) { lines.push(line); line = w; } else line = t; }
    if (line) lines.push(line); return lines;
};
const fit = (ctx, text, maxW, maxLines, start, min, weight = 900) => {
    let size = start, lines;
    do { ctx.font = `${weight} ${size}px ${FONT}`; lines = wrap(ctx, text, maxW); size -= 4; } while (lines.length > maxLines && size > min);
    return { lines, size: size + 4, weight };
};
const write = (ctx, t, x, y, lh = 1.08, align = 'left') => {
    ctx.font = `${t.weight} ${t.size}px ${FONT}`; ctx.textAlign = align;
    t.lines.forEach((l, i) => ctx.fillText(l, x, y + t.size * 0.82 + i * t.size * lh));
    ctx.textAlign = 'left';
    return t.lines.length * t.size * lh;
};
const rrect = (ctx, x, y, w, h, r) => { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); };
const pill = (ctx, text, x, y, { bg, fg, size = 38, padX = 34, h = 96, weight = 800, align = 'left', shadow = false }) => {
    ctx.font = `${weight} ${size}px ${FONT}`; const w = ctx.measureText(text).width + padX * 2;
    const left = align === 'center' ? x - w / 2 : x;
    if (shadow) { ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 10; }
    ctx.fillStyle = bg; rrect(ctx, left, y, w, h, h / 2); ctx.fill();
    if (shadow) ctx.restore();
    ctx.fillStyle = fg; ctx.textBaseline = 'middle'; ctx.fillText(text, left + padX, y + h / 2 + 2); ctx.textBaseline = 'alphabetic';
    return w;
};
const darken = (ctx, W, H, from = 0.3, alpha = 0.88) => {
    const g = ctx.createLinearGradient(0, H * from, 0, H); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(0.55, `rgba(0,0,0,${alpha * 0.68})`); g.addColorStop(1, `rgba(0,0,0,${alpha})`);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
};
const ratingText = (b) => (b?.rating && b?.reviews ? `★ ${String(b.rating).replace('.', ',')}/5 · ${b.reviews} avis Google` : '');
const brandPill = (ctx, b, P, top) => pill(ctx, b?.name || '', P, top, { bg: 'rgba(255,255,255,.95)', fg: '#0f172a', size: 32, h: 72, padX: 28 });

export async function renderAd(cv, spec, brand, format = 'portrait') {
    const [W, H] = FORMATS[format] || FORMATS.portrait; cv.width = W; cv.height = H;
    const ctx = cv.getContext('2d');
    let im = null; let err = '';
    try { im = await loadImg(spec.image); } catch { err = 'Photo inaccessible : choisissez-en une autre.'; }
    try { await document.fonts?.ready; } catch {}
    const C = brand?.color || '#4f46e5', P = 72, story = format === 'story';
    const topSafe = story ? 220 : P, botSafe = story ? 300 : P;
    const cta = (spec.cta || 'En savoir plus') + '  →';
    const showRating = spec.showRating !== false && ratingText(brand);
    ctx.clearRect(0, 0, W, H);

    switch (spec.layout) {
    case 'offre': {
        const ph = Math.round(H * 0.56); cover(ctx, im, 0, 0, W, ph); brandPill(ctx, brand, P, topSafe);
        ctx.fillStyle = C; ctx.fillRect(0, ph, W, H - ph);
        const btnY = H - botSafe - 96, top = ph + 60, room = btnY - 40 - top;
        let hs = 60, os = 96, t, o, need;
        do { t = fit(ctx, spec.hook, W - P * 2, 3, hs, 30, 800); o = spec.offer ? fit(ctx, spec.offer, W - P * 2, 3, os, 40) : null; need = t.lines.length * t.size * 1.12 + (o ? 24 + o.lines.length * o.size * 1.06 : 0); hs -= 4; os -= 6; } while (need > room && hs > 30);
        ctx.fillStyle = 'rgba(255,255,255,.88)'; let y = top + write(ctx, t, P, top, 1.12) + 24;
        if (o) { ctx.fillStyle = '#fff'; write(ctx, o, P, y, 1.06); }
        pill(ctx, cta, P, btnY, { bg: '#fff', fg: C });
        if (showRating) { ctx.font = `700 30px ${FONT}`; ctx.fillStyle = '#fff'; ctx.textAlign = 'right'; ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 12; ctx.fillText(ratingText(brand), W - P, ph - 40); ctx.shadowBlur = 0; ctx.textAlign = 'left'; }
        break;
    }
    case 'chiffre': {
        cover(ctx, im, 0, 0, W, H); ctx.fillStyle = 'rgba(8,10,18,.62)'; ctx.fillRect(0, 0, W, H); brandPill(ctx, brand, P, topSafe);
        const stat = fit(ctx, spec.stat || spec.offer || '', W - P * 2, 1, story ? 360 : 320, 120, 900);
        const hook = fit(ctx, spec.hook, W - P * 2, 3, 72, 40, 800);
        const total = stat.size + 30 + hook.lines.length * hook.size * 1.1;
        let y = (H - total) / 2 - 40;
        ctx.fillStyle = C; ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 40; write(ctx, stat, W / 2, y - stat.size * 0.1, 1, 'center'); ctx.shadowBlur = 0;
        y += stat.size + 30; ctx.fillStyle = '#fff'; y += write(ctx, hook, W / 2, y, 1.1, 'center');
        if (spec.sub) { ctx.fillStyle = 'rgba(255,255,255,.8)'; write(ctx, fit(ctx, spec.sub, W - P * 2, 2, 38, 28, 600), W / 2, y + 20, 1.2, 'center'); }
        pill(ctx, cta, W / 2, H - botSafe - 96, { bg: C, fg: '#fff', align: 'center', shadow: true });
        break;
    }
    case 'probleme': {
        const half = Math.round(H * 0.42);
        ctx.fillStyle = '#0f172a'; ctx.fillRect(0, 0, W, half);
        ctx.font = `800 30px ${FONT}`; ctx.fillStyle = '#f87171'; ctx.fillText('✕  LE PROBLÈME', P, topSafe + 30);
        const pb = fit(ctx, spec.problem || spec.sub || '', W - P * 2, 3, 70, 36, 800); ctx.fillStyle = 'rgba(255,255,255,.6)';
        const ph = write(ctx, pb, P, topSafe + 60, 1.12);
        ctx.strokeStyle = '#f87171'; ctx.lineWidth = 6; pb.lines.forEach((l, i) => { ctx.font = `800 ${pb.size}px ${FONT}`; const w = ctx.measureText(l).width; const ly = topSafe + 60 + pb.size * 0.55 + i * pb.size * 1.12; ctx.beginPath(); ctx.moveTo(P, ly); ctx.lineTo(P + w, ly); ctx.stroke(); });
        void ph;
        cover(ctx, im, 0, half, W, H - half); darken(ctx, W, H, 0.55, 0.9);
        pill(ctx, '✓  LA SOLUTION', P, half + 40, { bg: '#22c55e', fg: '#fff', size: 30, h: 72, padX: 28 });
        const t = fit(ctx, spec.hook, W - P * 2, 3, 84, 44); ctx.fillStyle = '#fff';
        const ty = H - botSafe - 96 - 40 - t.lines.length * t.size * 1.08; write(ctx, t, P, ty);
        pill(ctx, cta, P, H - botSafe - 96, { bg: C, fg: '#fff' });
        break;
    }
    case 'question': {
        ctx.fillStyle = C; ctx.fillRect(0, 0, W, H);
        const ph = Math.round(H * 0.42); cover(ctx, im, 0, H - ph, W, ph);
        const g = ctx.createLinearGradient(0, H - ph, 0, H - ph + 160); g.addColorStop(0, C); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = g; ctx.fillRect(0, H - ph, W, 160);
        ctx.font = `900 ${story ? 260 : 220}px ${FONT}`; ctx.fillStyle = 'rgba(255,255,255,.18)'; ctx.fillText('?', W - P - 140, topSafe + (story ? 230 : 200));
        brandPill(ctx, brand, P, topSafe);
        const t = fit(ctx, spec.hook, W - P * 2, 4, story ? 104 : 92, 50); ctx.fillStyle = '#fff'; const y = topSafe + 130; const h = write(ctx, t, P, y);
        if (spec.sub) { ctx.fillStyle = 'rgba(255,255,255,.85)'; write(ctx, fit(ctx, spec.sub, W - P * 2, 2, 40, 28, 600), P, y + h + 24, 1.2); }
        pill(ctx, cta, P, H - botSafe - 96, { bg: '#fff', fg: C, shadow: true });
        break;
    }
    case 'avis': {
        cover(ctx, im, 0, 0, W, H); ctx.fillStyle = 'rgba(15,23,42,.5)'; ctx.fillRect(0, 0, W, H);
        const rv = spec.review || { text: spec.hook, author: brand?.name, rating: 5 };
        const cw = W - P * 2;
        const quote = '« ' + String(rv.text).trim().replace(/\s+(\S+)$/, ' $1') + ' »';
        const t = fit(ctx, quote, cw - 100, story ? 8 : 6, 56, 32, 700);
        const ch = 140 + t.lines.length * t.size * 1.25 + 140; const cy = Math.max(topSafe + 90, (H - ch) / 2 - 40);
        ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.4)'; ctx.shadowBlur = 50; ctx.fillStyle = '#fff'; rrect(ctx, P, cy, cw, ch, 40); ctx.fill(); ctx.restore();
        ctx.fillStyle = '#f59e0b'; ctx.font = `900 54px ${FONT}`; ctx.fillText('★'.repeat(Math.max(1, Math.min(5, rv.rating || 5))), P + 50, cy + 96);
        ctx.fillStyle = '#0f172a'; write(ctx, t, P + 50, cy + 130, 1.25);
        ctx.fillStyle = '#64748b'; ctx.font = `700 30px ${FONT}`; ctx.fillText(`${rv.author || 'Client'} · avis Google`, P + 50, cy + ch - 52);
        brandPill(ctx, brand, P, topSafe);
        pill(ctx, cta, W / 2, Math.min(cy + ch + 46, H - botSafe - 96), { bg: C, fg: '#fff', align: 'center', shadow: true });
        break;
    }
    case 'ugc': {
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, W, H);
        const head = story ? 200 : 130, foot = story ? 420 : 300;
        ctx.fillStyle = C; ctx.beginPath(); ctx.arc(P + 34, head / 2 + (story ? 60 : 0), 34, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#fff'; ctx.font = `900 34px ${FONT}`; ctx.textAlign = 'center'; ctx.fillText((brand?.name || 'J')[0].toUpperCase(), P + 34, head / 2 + (story ? 60 : 0) + 12); ctx.textAlign = 'left';
        ctx.fillStyle = '#0f172a'; ctx.font = `800 32px ${FONT}`; ctx.fillText(brand?.name || '', P + 86, head / 2 + (story ? 60 : 0) + 10);
        cover(ctx, im, 0, head + (story ? 60 : 0), W, H - head - foot - (story ? 60 : 0));
        let y = H - foot + 40; ctx.font = `400 48px ${FONT}`; ctx.fillStyle = '#0f172a'; ctx.fillText('♡   💬   ➤', P, y + 30);
        if (showRating) { ctx.font = `700 28px ${FONT}`; ctx.fillStyle = '#f59e0b'; ctx.textAlign = 'right'; ctx.fillText(ratingText(brand), W - P, y + 26); ctx.textAlign = 'left'; }
        y += 70; const t = fit(ctx, spec.hook, W - P * 2, 3, 46, 30, 700); ctx.fillStyle = '#0f172a'; write(ctx, t, P, y, 1.25);
        if (spec.sub) { ctx.fillStyle = '#64748b'; ctx.font = `500 32px ${FONT}`; ctx.fillText(spec.sub.slice(0, 48), P, H - (story ? 120 : 50)); }
        break;
    }
    case 'epure': {
        const band = Math.round(H * (story ? 0.3 : 0.32)); cover(ctx, im, 0, 0, W, H - band);
        ctx.fillStyle = '#fff'; ctx.fillRect(0, H - band, W, band);
        ctx.fillStyle = C; ctx.fillRect(P, H - band + 50, 90, 10);
        const t = fit(ctx, spec.hook, W - P * 2, 3, 74, 42); ctx.fillStyle = '#0f172a'; write(ctx, t, P, H - band + 90);
        ctx.font = `700 34px ${FONT}`; ctx.fillStyle = C; ctx.fillText((spec.offer ? spec.offer + '  ·  ' : '') + cta, P, H - (story ? 230 : 60));
        brandPill(ctx, brand, P, topSafe);
        break;
    }
    default: { // impact
        cover(ctx, im, 0, 0, W, H); darken(ctx, W, H, 0.25, 0.9); brandPill(ctx, brand, P, topSafe);
        let y = H - botSafe - 96;
        pill(ctx, cta, P, y, { bg: '#fff', fg: C });
        y -= 34;
        if (spec.offer) { const o = fit(ctx, spec.offer, W - P * 2 - 60, 2, 44, 30, 800); const oh = o.lines.length * o.size * 1.2 + 40; y -= oh; ctx.fillStyle = C; rrect(ctx, P, y, W - P * 2, oh, 26); ctx.fill(); ctx.fillStyle = '#fff'; write(ctx, o, P + 30, y + 20, 1.2); y -= 28; }
        const t = fit(ctx, spec.hook, W - P * 2, story ? 5 : 4, story ? 108 : 96, 52);
        y -= t.lines.length * t.size * 1.06; ctx.fillStyle = '#fff'; write(ctx, t, P, y, 1.06);
        if (spec.sub) { const s2 = fit(ctx, spec.sub, W - P * 2, 2, 36, 26, 600); y -= s2.lines.length * s2.size * 1.2 + 16; ctx.fillStyle = 'rgba(255,255,255,.85)'; write(ctx, s2, P, y, 1.2); }
        if (showRating) { ctx.font = `700 32px ${FONT}`; ctx.fillStyle = '#fde68a'; ctx.fillText(ratingText(brand), P, y - 22); }
    }
    }
    return err;
}

/** Vignette de carrousel (carré) : slides = [{title, text}], index i sur n. */
export async function renderSlide(cv, slide, i, n, brand, image, cta) {
    const W = 1080, H = 1080; cv.width = W; cv.height = H; const ctx = cv.getContext('2d');
    const C = brand?.color || '#4f46e5', P = 80;
    let im = null; try { im = await loadImg(image); } catch {}
    try { await document.fonts?.ready; } catch {}
    const last = i === n - 1, first = i === 0;
    if (first || last) { cover(ctx, im, 0, 0, W, H); ctx.fillStyle = last ? 'rgba(8,10,18,.55)' : 'rgba(8,10,18,.45)'; ctx.fillRect(0, 0, W, H); darken(ctx, W, H, 0.3, 0.85); }
    else { ctx.fillStyle = i % 2 ? C : '#0f172a'; ctx.fillRect(0, 0, W, H); }
    ctx.font = `800 30px ${FONT}`; ctx.fillStyle = 'rgba(255,255,255,.8)'; ctx.fillText(`${i + 1} / ${n}`, W - P - 60, P + 30);
    pill(ctx, brand?.name || '', P, P - 10, { bg: 'rgba(255,255,255,.95)', fg: '#0f172a', size: 28, h: 64, padX: 24 });
    const t = fit(ctx, slide.title, W - P * 2, 3, first ? 104 : 90, 50); ctx.fillStyle = '#fff';
    const tx = fit(ctx, slide.text || '', W - P * 2, 4, 44, 30, 600);
    const blockH = t.lines.length * t.size * 1.06 + 30 + tx.lines.length * tx.size * 1.3;
    let y = last ? H - P - 96 - 50 - blockH : (H - blockH) / 2 + 20;
    y += write(ctx, t, P, y, 1.06) + 30; ctx.fillStyle = 'rgba(255,255,255,.85)'; write(ctx, tx, P, y, 1.3);
    if (last) pill(ctx, (cta || 'En savoir plus') + '  →', P, H - P - 96, { bg: C, fg: '#fff', shadow: true });
    else { ctx.font = `800 40px ${FONT}`; ctx.fillStyle = 'rgba(255,255,255,.9)'; ctx.fillText('→', W - P - 30, H - P); }
}
