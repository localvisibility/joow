import { chromium } from 'playwright';
const SLUG = process.env.SLUG;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
const p = await ctx.newPage();
const logs = [];
p.on('console', (m) => { if (m.type() === 'error' && !m.text().includes('403')) logs.push('[console] ' + m.text().slice(0, 600)); });
p.on('pageerror', (e) => logs.push('[pageerror] ' + (e.stack || String(e)).slice(0, 900)));
await p.goto('https://app.joow.fr/login', { waitUntil: 'load', timeout: 60000 }); await p.click('text=Mot de passe'); await p.fill('#email', process.env.JOOW_EMAIL); await p.fill('#password', process.env.JOOW_PASS);
await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('button[type=submit]')]);
await p.goto(`https://app.joow.fr/sites/${SLUG}/editeur`, { waitUntil: 'load', timeout: 60000 });
await p.waitForSelector('iframe'); await p.waitForTimeout(6000);
const fr = p.frames().find((f) => f.url().includes('/editeur/preview'));
for (const step of ['tagline-click', 'outside-click']) {
  if (step === 'tagline-click') { const el = await fr.$('[data-edit="content.tagline"], [data-edit]'); await el.click(); await p.waitForTimeout(400); }
  else { await fr.click('header[data-section="hero"]', { position: { x: 900, y: 120 } }); await p.waitForTimeout(1200); }
  const vis = await p.evaluate(() => ({ app: document.getElementById('app')?.innerHTML.length, hdr: !!document.querySelector('header, nav') }));
  logs.push(`[${step}] app html length=${vis.app}`);
}
await p.screenshot({ path: process.env.OUT + '/studio2.png' });
console.log(logs.join('\n'));
await b.close();
