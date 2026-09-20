import { chromium } from 'playwright';
const PROXY = process.env.HTTPS_PROXY;
const OUT = process.env.OUT || '/tmp';
const URL = process.env.URL || 'https://app.joow.fr/';
const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  proxy: PROXY ? { server: PROXY } : undefined,
  args: ['--ignore-certificate-errors'],
});
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1.5, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
await page.goto(URL, { waitUntil: 'load', timeout: 60000 });
try { await page.waitForLoadState('networkidle', { timeout: 30000 }); } catch {}
await page.waitForTimeout(3000);
await page.screenshot({ path: `${OUT}/live-hero.webp`, type: 'webp', quality: 80 });
// carrousel : descendre jusqu'à la vitrine
await page.evaluate(() => { const el = [...document.querySelectorAll('h2')].find(h=>/donnent envie/i.test(h.textContent)); if (el) el.scrollIntoView({block:'start'}); });
await page.waitForTimeout(2500);
await page.screenshot({ path: `${OUT}/live-carousel.webp`, type: 'webp', quality: 80 });
await browser.close();
console.log('DONE');
