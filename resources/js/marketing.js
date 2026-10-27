/* Site vitrine joow.fr : générateur embarqué, animations, consentement + pixel, menus. */
import '../css/app.css';
import { consent, choose, initPixel, initGtm, track, fire } from './tracking';

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const csrf = () => $('meta[name=csrf-token]')?.content || '';
const APP = document.documentElement.dataset.app || '';
// La recherche se fait sur l'hôte courant (même application, pas de CORS) ; les soumissions partent vers l'app
const routes = { search: '/recherche', generate: APP + '/generer', intake: APP + '/creer', redesign: APP + '/refonte' };

/* ── Navigation : fond au défilement, menu mobile ── */
const nav = $('#mnav');
const onScroll = () => nav?.classList.toggle('is-scrolled', window.scrollY > 24);
onScroll(); addEventListener('scroll', onScroll, { passive: true });
$('#mnav-toggle')?.addEventListener('click', () => { const open = $('#mnav-menu').classList.toggle('hidden'); $('#mnav-toggle').setAttribute('aria-expanded', String(!open)); });

/* ── Apparitions au défilement, compteurs ── */
const io = new IntersectionObserver((entries) => entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('on'); io.unobserve(e.target); } }), { threshold: 0.12 });
$$('.reveal').forEach((el) => io.observe(el));
$$('[data-count]').forEach((el) => {
    const target = parseFloat(el.dataset.count), dec = (el.dataset.count.split('.')[1] || '').length, suffix = el.dataset.suffix || '';
    new IntersectionObserver((es, o) => es.forEach((e) => { if (!e.isIntersecting) return; o.disconnect(); const t0 = performance.now(); const tick = (t) => { const p = Math.min(1, (t - t0) / 1400); const v = target * (1 - Math.pow(1 - p, 3)); el.textContent = v.toLocaleString('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suffix; if (p < 1) requestAnimationFrame(tick); }; requestAnimationFrame(tick); }), { threshold: 0.5 }).observe(el);
});

/* ── Inclinaison 3D légère des maquettes (ordinateur) ── */
if (matchMedia('(hover:hover)').matches) {
    $$('.tilt').forEach((el) => {
        el.addEventListener('pointermove', (e) => { const r = el.getBoundingClientRect(); const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5; el.style.transform = `perspective(1200px) rotateX(${-y * 6}deg) rotateY(${x * 8}deg) translateY(-4px)`; });
        el.addEventListener('pointerleave', () => { el.style.transform = ''; });
    });
}

/* ── Comparateur avant / après ── */
$$('.compare').forEach((c) => { const range = $('input[type=range]', c); const after = $('.compare-after', c); const line = $('.compare-line', c); const set = (v) => { after.style.clipPath = `inset(0 ${100 - v}% 0 0)`; line.style.left = v + '%'; }; set(range.value); range.addEventListener('input', () => set(range.value)); });

/* ── Générateur embarqué : trois points de départ, un champ ── */
$$('[data-generator]').forEach((g) => {
    const input = $('input[name=q]', g), list = $('.gen-results', g), form = $('form', g), modes = $$('[data-mode]', g), emailInput = $('input[name=email]', g), hint = $('.gen-hint', g), submit = $('button[type=submit]', g);
    let mode = 'google', selected = null, timer = null, results = [];
    const URL_RE = /^(https?:\/\/)?(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(\/\S*)?$/i;
    const setMode = (m) => { mode = m; modes.forEach((b) => b.classList.toggle('is-active', b.dataset.mode === m)); input.placeholder = m === 'site' ? 'www.mon-site.fr' : 'Nom de votre établissement…'; selected = null; list.innerHTML = ''; list.classList.add('hidden'); input.focus(); };
    modes.forEach((b) => b.addEventListener('click', () => { if (b.dataset.mode === 'none') { location.href = routes.intake + '?q=' + encodeURIComponent(input.value.trim()); return; } setMode(b.dataset.mode); }));
    const render = (items, none) => {
        list.innerHTML = items.map((r, i) => `<button type="button" data-i="${i}" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/5"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/5">📍</span><span class="min-w-0"><span class="block truncate font-semibold text-white">${r.main}</span><span class="block truncate text-xs text-slate-500">${r.secondary || ''}</span></span></button>`).join('')
            + (none ? `<p class="px-4 pt-3 text-xs text-slate-400">Aucune fiche Google ne correspond.</p>` : '')
            + `<a href="${routes.intake}?q=${encodeURIComponent(input.value.trim())}" class="flex w-full items-center gap-3 border-t border-white/[0.06] px-4 py-3 text-left transition hover:bg-white/5 ${none ? 'bg-brand-500/10' : ''}"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-gradient text-sm text-white">✎</span><span><span class="block font-semibold text-white">Je n'ai pas de fiche Google</span><span class="block text-xs text-slate-400">Créer mon site en 5 questions · 1 minute</span></span></a>`;
        list.classList.remove('hidden');
        $$('button[data-i]', list).forEach((b) => b.addEventListener('click', () => { selected = results[+b.dataset.i]; input.value = selected.main; list.classList.add('hidden'); track('Search', { search_string: selected.main, content_category: 'fiche_google' }); submit.textContent = 'Générer mon site →'; }));
    };
    input.addEventListener('input', () => {
        selected = null; submit.textContent = 'Créer mon site'; clearTimeout(timer); const v = input.value.trim();
        if (mode === 'site') { hint.textContent = URL_RE.test(v) ? 'Entrée pour analyser et refaire ce site.' : (v.length > 3 ? 'Collez l\'adresse complète, par exemple www.mon-site.fr' : ''); return; }
        if (v.length < 3 || URL_RE.test(v)) { list.classList.add('hidden'); return; }
        timer = setTimeout(async () => { try { const r = await fetch(routes.search + '?q=' + encodeURIComponent(v), { headers: { Accept: 'application/json' } }); results = (await r.json()).results || []; render(results, results.length === 0); } catch { results = []; } }, 300);
    });
    document.addEventListener('click', (e) => { if (!g.contains(e.target)) list.classList.add('hidden'); });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const v = input.value.trim(); const email = emailInput?.value.trim() || '';
        const post = (action, fields) => { const f = document.createElement('form'); f.method = 'POST'; const attr = new URLSearchParams([...new URLSearchParams(location.search)].filter(([k]) => /^(utm_|fbclid|gclid|ref$)/.test(k))).toString(); f.action = action + (attr ? '?' + attr : ''); const add = (n, val) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = val; f.appendChild(i); }; add('_token', csrf()); Object.entries(fields).forEach(([k, val]) => add(k, val)); document.body.appendChild(f); f.submit(); };
        if (URL_RE.test(v) && !/\s/.test(v)) { track('Search', { search_string: v, content_category: 'site_existant' }); post(routes.redesign, { url: v, email }); return; }
        if (selected) { post(routes.generate, { query: selected.place_id, email }); return; }
        if (v.length >= 3 && results.length) { selected = results[0]; post(routes.generate, { query: selected.place_id, email }); return; }
        if (v.length >= 3) { location.href = routes.intake + '?q=' + encodeURIComponent(v) + (email ? '&email=' + encodeURIComponent(email) : ''); return; }
        input.focus(); input.classList.add('animate-shake'); setTimeout(() => input.classList.remove('animate-shake'), 600);
    });
    /* Machine à écrire : exemples dans le champ tant qu'il est vide */
    const samples = ['Le Bistrot de la Place', 'Plomberie Martin', 'Salon Élégance', 'Cabinet Dr Morel', 'Garage du Centre', 'Fleurs de Léa'];
    let si = 0, ci = 0, dir = 1, stopped = false, twTimer;
    const tw = () => { if (stopped || document.activeElement === input || input.value) return; const s = samples[si]; ci += dir; input.placeholder = s.slice(0, ci) + (ci < s.length ? '|' : ''); if (ci >= s.length + 8) dir = -1; if (ci <= 0 && dir < 0) { dir = 1; si = (si + 1) % samples.length; } twTimer = setTimeout(tw, dir > 0 ? 70 : 28); };
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) { setTimeout(tw, 900); input.addEventListener('focus', () => { stopped = true; clearTimeout(twTimer); input.placeholder = mode === 'site' ? 'www.mon-site.fr' : 'Nom de votre établissement…'; }); }
});

/* ── Consentement + pixel Meta ── */
const pixelId = document.documentElement.dataset.pixel || '';
const gtmId = document.documentElement.dataset.gtm || '';
if (pixelId || gtmId) {
    initPixel(pixelId);
    initGtm(gtmId);
    const banner = $('#consent');
    if (banner && consent() === '') {
        banner.classList.remove('hidden');
        $('[data-consent=all]', banner).addEventListener('click', () => { choose(true); banner.remove(); });
        $('[data-consent=none]', banner).addEventListener('click', () => { choose([]); banner.remove(); });
        $('[data-consent=custom]', banner).addEventListener('click', () => $('[data-consent-panel]', banner).classList.toggle('hidden'));
        $('[data-consent=save]', banner).addEventListener('click', () => { choose([$('input[name=ads]', banner).checked && 'ads', $('input[name=analytics]', banner).checked && 'analytics']); banner.remove(); });
    }
    try { fire(JSON.parse(document.documentElement.dataset.track || '[]')); } catch {}
}
