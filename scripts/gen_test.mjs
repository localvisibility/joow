import { chromium } from 'playwright';
const Q = process.env.Q; const OUT = process.env.OUT; const TAG = process.env.TAG;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const ctx = await b.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const p = await ctx.newPage();
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type(Q);
await p.waitForSelector('.bg-ink-800 button', { timeout: 20000 }); await p.waitForTimeout(500);
const opts = await p.$$('.bg-ink-800 button'); console.log('choix:', (await opts[0].innerText()).replace(/\n/g, ' / '));
await opts[0].click(); await p.waitForTimeout(500);
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('button[type=submit]:has-text("Générer")')]);
const slug = p.url().split('/site/')[1].split(/[/?]/)[0]; console.log('slug', slug);
const t0 = Date.now(); let st = '';
for (let i = 0; i < 80; i++) { const r = await p.evaluate(async (s) => (await (await fetch(`/site/${s}/status`, { headers: { Accept: 'application/json' } })).json()), slug); st = r.status; if (['preview', 'paid', 'published', 'failed'].includes(st)) break; await p.waitForTimeout(3000); }
console.log('statut', st, 'en', Math.round((Date.now() - t0) / 1000), 's');
// état structuré (session invitée → accès Studio)
const state = await p.evaluate(async (s) => (await (await fetch(`/sites/${s}/editeur/state`, { headers: { Accept: 'application/json' } })).json()), slug);
const c = state.content || {};
console.log(JSON.stringify({ sector: state.defaults?.label, hero_title: c.hero_title, hero_subtitle: c.hero_subtitle, tagline: c.tagline, about_title: c.about_title, services_title: c.services_title, services: (c.services || []).map((s) => [s.name, s.price, s.image ? 'img' : '-']), faq_title: c.faq_title, gallery_title: c.gallery_title, stats: c.stats, images: Object.fromEntries(Object.entries(state.images || {}).map(([k, v]) => [k, Array.isArray(v) ? v.length + ' photos' : (v ? 'ok' : null)])), modules: Object.keys(state.modules || {}) }, null, 1));
const site = await ctx.newPage(); await site.goto(`https://${slug}.joow.fr/?v=${Date.now()}`, { waitUntil: 'load', timeout: 60000 }); await site.waitForTimeout(4000);
await site.addStyleTag({ content: '.reveal{opacity:1!important;transform:none!important} *{animation-play-state:paused!important}' }); await site.evaluate(() => document.getElementById('joow-curtain')?.remove());
await site.screenshot({ path: `${OUT}/gen-${TAG}.png`, fullPage: true });
console.log('SLUG=' + slug);
await b.close();
