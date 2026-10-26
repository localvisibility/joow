import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 1, viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true })).newPage();
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type(process.env.Q);
await p.waitForSelector('.bg-ink-800 button', { timeout: 30000 }); await p.waitForTimeout(500);
const opts = await p.$$('.bg-ink-800 button'); console.log('choix:', (await opts[0].innerText()).replace(/\n/g, ' / '));
await opts[0].click(); await p.waitForTimeout(400);
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('button[type=submit]:has-text("Générer")')]);
const t0 = Date.now();
for (let i = 0; i < 80; i++) { if (await p.$('text=Votre site est prêt')) break; await p.waitForTimeout(2000); }
console.log('prêt après', Math.round((Date.now() - t0) / 1000), 's');
const slug = p.url().split('/site/')[1].split(/[/?]/)[0];
await p.goto(`https://${slug}.joow.fr/`, { waitUntil: 'networkidle', timeout: 60000 });
const r = await p.evaluate(() => { const W = document.getElementById('joow-wizard'); if (!W) return { wizard: null }; return { wizard: Math.round(W.getBoundingClientRect().width), grid: Math.round(W.parentElement.getBoundingClientRect().width), sw: document.documentElement.scrollWidth }; });
console.log(slug, JSON.stringify(r));
if (r.wizard) { await p.evaluate(() => document.getElementById('joow-wizard').scrollIntoView({ block: 'start' })); await p.waitForTimeout(900); await p.screenshot({ path: process.env.OUT + '/wiz-live.png' }); }
await b.close();
