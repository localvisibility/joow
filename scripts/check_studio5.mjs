import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true })).newPage();
const log = (s) => console.log(s);
p.on('console', (m) => { if (m.type() === 'error' && !/403|favicon/.test(m.text())) log('[console] ' + m.text().slice(0, 700)); });
p.on('pageerror', (e) => log('[pageerror] ' + (e.stack || String(e)).slice(0, 1200)));
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type('Fana restaurant Paris');
await p.waitForSelector(".bg-ink-800 button", { timeout: 20000 }); await p.waitForTimeout(500);
const opts = await p.$$(".bg-ink-800 button"); log('options: ' + opts.length + ' → ' + (await opts[0].innerText()).replace(/\n/g, ' / '));
await opts[0].click(); await p.waitForTimeout(600);
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click("button[type=submit]:has-text(\"Générer\")")]);
log('site page: ' + p.url());
const slug = p.url().split('/site/')[1].split(/[/?]/)[0];
for (let i = 0; i < 60; i++) { const r = await p.evaluate(async (s) => (await (await fetch(`/site/${s}/status`, { headers: { Accept: 'application/json' } })).json()), slug); if (['preview', 'paid', 'published', 'failed'].includes(r.status)) { log('status ' + r.status); break; } await p.waitForTimeout(3000); }
await p.goto(`https://app.joow.fr/sites/${slug}/editeur`, { waitUntil: 'load', timeout: 60000 });
await p.waitForSelector('iframe', { timeout: 30000 }); await p.waitForTimeout(7000);
const fr = p.frames().find((f) => f.url().includes('/editeur/preview'));
const el = await fr.$('[data-edit="content.hero_subtitle"]') || await fr.$('p[data-edit]'); await el.click(); await p.waitForTimeout(500); await p.keyboard.type(' X');
for (const pos of [{ x: 1200, y: 150 }, { x: 300, y: 820 }, { x: 1100, y: 500 }]) {
  await fr.click('header[data-section="hero"]', { position: pos, force: true }).catch((e) => log('click err ' + e.message.slice(0, 100)));
  await p.waitForTimeout(1200);
  log(`after click ${JSON.stringify(pos)} → app html length=${await p.evaluate(() => document.getElementById('app')?.innerHTML.length)}`);
}
await p.screenshot({ path: process.env.OUT + '/studio5.png' });
log('SLUG=' + slug);
await b.close();
