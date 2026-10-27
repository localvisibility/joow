/* Enregistre le site généré, plein écran mobile, avec un défilement fluide. */
import { chromium } from 'playwright';
import fs from 'node:fs';
const out = process.env.OUT; fs.mkdirSync(out + '/site', { recursive: true });
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const cdp = await ctx.newCDPSession(page);
const frames = []; const t0 = Date.now(); let n = 0;
cdp.on('Page.screencastFrame', async (ev) => { const i = n++; fs.writeFileSync(`${out}/site/s${String(i).padStart(5, '0')}.jpg`, Buffer.from(ev.data, 'base64')); frames.push({ i, t: (Date.now() - t0) / 1000 }); try { await cdp.send('Page.screencastFrameAck', { sessionId: ev.sessionId }); } catch {} });
await page.goto(process.env.SITE, { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(1500);
await page.addStyleTag({ content: 'html{scroll-behavior:auto !important}' });
await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: 780, maxHeight: 1688, everyNthFrame: 1 });
await page.waitForTimeout(1200);
const marks = { start: (Date.now() - t0) / 1000 };
const H = await page.evaluate(() => document.documentElement.scrollHeight);
const target = Math.min(H - 844, 5200);
for (let y = 0; y <= target; y += 14) { await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), y); await page.waitForTimeout(16); }
marks.end = (Date.now() - t0) / 1000;
await page.waitForTimeout(800);
await cdp.send('Page.stopScreencast');
fs.writeFileSync(out + '/site-timeline.json', JSON.stringify({ frames, marks, H }, null, 1));
await browser.close(); console.log('site frames', frames.length, 'H', H, marks);
