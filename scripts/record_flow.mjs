/* Enregistre le parcours mobile réel (app.joow.fr) image par image via CDP, avec repères temporels. */
import { chromium } from 'playwright';
import fs from 'node:fs';
const out = process.env.OUT; fs.mkdirSync(out + '/frames', { recursive: true });
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' });
await ctx.addCookies([{ name: 'joow_consent', value: 'essential', domain: 'app.joow.fr', path: '/' }]);
const page = await ctx.newPage();
const cdp = await ctx.newCDPSession(page);
const frames = []; const marks = {}; const t0 = Date.now();
const mark = (k) => { marks[k] = (Date.now() - t0) / 1000; console.log('mark', k, marks[k].toFixed(2)); };
let n = 0;
cdp.on('Page.screencastFrame', async (ev) => {
  const i = n++; fs.writeFileSync(`${out}/frames/f${String(i).padStart(5, '0')}.jpg`, Buffer.from(ev.data, 'base64'));
  frames.push({ i, t: (Date.now() - t0) / 1000 });
  try { await cdp.send('Page.screencastFrameAck', { sessionId: ev.sessionId }); } catch {}
});
await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: 780, maxHeight: 1688, everyNthFrame: 1 });
await page.goto('https://app.joow.fr/', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(1500); mark('landing');
const input = page.locator('#search-input');
await input.tap(); await page.waitForTimeout(400); mark('typing');
await input.pressSequentially('Le Petit Bistrot Lyon', { delay: 95 });
await page.waitForSelector('button:has-text("Lyon")', { timeout: 15000 }); await page.waitForTimeout(900); mark('results');
const first = page.locator('button[type=button].w-full').first(); console.log('first:', (await first.innerText()).replace(/\n/g, ' | '));
await first.tap(); await page.waitForTimeout(700); mark('picked');
await Promise.all([page.waitForURL(/\/site\//, { timeout: 60000 }), page.locator('button[type=submit]').first().tap()]);
mark('generating');
const slug = page.url().split('/site/')[1].split(/[?#]/)[0]; console.log('slug', slug);
// Attente de la fin de génération (poll du statut)
for (let i = 0; i < 180; i++) {
  await page.waitForTimeout(1000);
  const st = await page.evaluate(async (s) => { try { const r = await fetch('/site/' + s + '/status'); return (await r.json()).status; } catch { return '?'; } }, slug);
  if (st === 'ready' || st === 'published' || st === 'failed') { console.log('status', st, 'after', i + 1, 's'); break; }
}
await page.waitForTimeout(2500); mark('ready');
// Défilement du site dans l'aperçu (iframe) puis de la page
const frame = page.frames().find((f) => f.url().includes('joow.fr') && f !== page.mainFrame());
for (let y = 0; y <= 2400 && frame; y += 60) { await frame.evaluate((y) => window.scrollTo(0, y), y).catch(() => {}); await page.waitForTimeout(45); }
mark('scrolled');
await page.waitForTimeout(800);
await cdp.send('Page.stopScreencast');
fs.writeFileSync(out + '/timeline.json', JSON.stringify({ frames, marks, slug }, null, 1));
await browser.close();
console.log('frames', frames.length);
