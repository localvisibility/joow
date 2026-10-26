// Capture du spectacle de génération à ~12 s, ~40 s et ~75 s sur une vraie génération
import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true })).newPage();
p.on('pageerror', (e) => console.log('[pageerror]', String(e).slice(0, 200)));
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type(process.env.Q);
await p.waitForSelector('.bg-ink-800 button', { timeout: 20000 }); await p.waitForTimeout(500);
const opts = await p.$$('.bg-ink-800 button'); console.log('choix:', (await opts[0].innerText()).replace(/\n/g, ' / '));
await opts[0].click(); await p.waitForTimeout(400);
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('button[type=submit]:has-text("Générer")')]);
const t0 = Date.now();
for (const at of [12, 40, 75]) { while (Date.now() - t0 < at * 1000) await p.waitForTimeout(500); await p.screenshot({ path: `${process.env.OUT}/show-${at}.png` }); console.log('capture', at, 's :', (await p.textContent('body')).replace(/\s+/g, ' ').slice(0, 160)); }
for (let i = 0; i < 60; i++) { if (await p.$('text=Votre site est prêt')) break; await p.waitForTimeout(2000); }
console.log('prêt après', Math.round((Date.now() - t0) / 1000), 's');
await b.close();
