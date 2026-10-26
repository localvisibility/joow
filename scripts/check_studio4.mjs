import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true })).newPage();
const logs = []; const log = (s) => { logs.push(s); console.log(s); };
p.on('console', (m) => { if (m.type() === 'error' && !/403|favicon/.test(m.text())) log('[console] ' + m.text().slice(0, 700)); });
p.on('pageerror', (e) => log('[pageerror] ' + (e.stack || String(e)).slice(0, 1200)));
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type('Fana Paris 18');
await p.waitForSelector('text=Fana', { timeout: 20000 }); await p.waitForTimeout(800);
const opt = await p.$$('[role=option], button:has-text("Fana"), li:has-text("Fana")'); log('options: ' + opt.length);
await opt[0].click(); await p.waitForTimeout(600);
await p.click('text=Créer mon site');
await p.waitForURL(/\/site\//, { timeout: 60000 }); log('site page: ' + p.url());
try { await p.waitForSelector('text=Modifier', { timeout: 150000 }); } catch { log('timeout prêt'); }
await p.waitForTimeout(1500);
const slug = p.url().split('/site/')[1].split(/[/?]/)[0]; log('slug ' + slug);
await p.goto(`https://app.joow.fr/sites/${slug}/editeur`, { waitUntil: 'load', timeout: 60000 });
await p.waitForSelector('iframe', { timeout: 30000 }); await p.waitForTimeout(7000);
const fr = p.frames().find((f) => f.url().includes('/editeur/preview'));
const el = await fr.$('[data-edit="content.hero_subtitle"]') || await fr.$('p[data-edit]'); await el.click(); await p.waitForTimeout(500); await p.keyboard.type(' X');
for (const pos of [{ x: 1200, y: 150 }, { x: 300, y: 820 }, { x: 1100, y: 500 }]) {
  await fr.click('header[data-section="hero"]', { position: pos, force: true }).catch((e) => log('click err ' + e.message.slice(0, 100)));
  await p.waitForTimeout(1200);
  log(`after click ${JSON.stringify(pos)} → app html length=${await p.evaluate(() => document.getElementById('app')?.innerHTML.length)}`);
}
await p.screenshot({ path: process.env.OUT + '/studio4.png' });
await b.close();
