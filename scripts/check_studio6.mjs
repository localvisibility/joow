import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true })).newPage();
const log = (s) => console.log(s);
p.on('console', (m) => { if (m.type() === 'error' && !/403|favicon/.test(m.text())) log('[console] ' + m.text().slice(0, 700)); });
p.on('pageerror', (e) => log('[pageerror] ' + (e.stack || String(e)).slice(0, 1200)));
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type('Fana restaurant Paris');
await p.waitForSelector('.bg-ink-800 button', { timeout: 20000 }); await p.waitForTimeout(500);
await (await p.$$('.bg-ink-800 button'))[0].click(); await p.waitForTimeout(600);
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('button[type=submit]:has-text("Générer")')]);
const slug = p.url().split('/site/')[1].split(/[/?]/)[0]; log('slug ' + slug);
for (let i = 0; i < 60; i++) { const r = await p.evaluate(async (s) => (await (await fetch(`/site/${s}/status`, { headers: { Accept: 'application/json' } })).json()), slug); if (['preview', 'paid', 'published', 'failed'].includes(r.status)) { log('status ' + r.status); break; } await p.waitForTimeout(3000); }
await p.goto(`https://app.joow.fr/sites/${slug}/editeur`, { waitUntil: 'load', timeout: 60000 });
await p.waitForSelector('iframe', { timeout: 30000 }); await p.waitForTimeout(7000);
const fr = () => p.frames().find((f) => f.url().includes('/editeur/preview'));
const state = async (label) => { const s = await p.evaluate(() => { const ov = document.querySelector('.fixed.inset-0'); return { app: document.getElementById('app')?.innerHTML.length, overlay: ov ? ov.innerText.trim().slice(0, 60) || '(vide)' : 'aucun', header: !!document.querySelector('iframe') }; }); log(`[${label}] ${JSON.stringify(s)}`); };
await state('initial');
const targets = await fr().evaluate(() => [...document.querySelectorAll('header [data-edit-img], header button, header a, header [data-section], header img')].map((e, i) => ({ i, tag: e.tagName, cls: (e.className || '').toString().slice(0, 40), de: e.getAttribute('data-edit-img'), ds: e.getAttribute('data-section'), txt: (e.innerText || '').trim().slice(0, 30) })));
log('cibles: ' + JSON.stringify(targets));
// 1) édition d'un texte puis clic sur chaque cible
for (const t of targets) {
  const el = await fr().$('[data-edit="content.hero_subtitle"]') || await fr().$('p[data-edit]'); if (el) { await el.click().catch(() => {}); await p.waitForTimeout(300); }
  const handles = await fr().$$('header [data-edit-img], header button, header a, header [data-section], header img');
  const h = handles[t.i]; if (!h) continue;
  await h.click({ force: true, timeout: 3000 }).catch((e) => log('click err ' + t.i + ' ' + e.message.slice(0, 60)));
  await p.waitForTimeout(900);
  await state(`après clic ${t.i} ${t.tag} ${t.de || t.ds || t.txt}`);
  await p.keyboard.press('Escape'); await p.evaluate(() => { document.querySelector('.fixed.inset-0')?.click(); }); await p.waitForTimeout(300);
}
await p.screenshot({ path: process.env.OUT + '/studio6.png' });
await b.close();
