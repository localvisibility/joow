import { chromium } from 'playwright';
import { readdirSync } from 'node:fs';
import path from 'node:path';

const SRC = process.env.SRC;
const OUT = process.env.OUT;
const files = readdirSync(SRC).filter(f => f.endsWith('.html'));

const PROXY = process.env.HTTPS_PROXY || process.env.https_proxy;
const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  proxy: PROXY ? { server: PROXY } : undefined,
  args: ['--ignore-certificate-errors'],
});
const ctx = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  deviceScaleFactor: 2,
  ignoreHTTPSErrors: true,
});
const page = await ctx.newPage();

for (const f of files) {
  const name = path.basename(f, '.html');
  await page.goto('file://' + path.join(SRC, f), { waitUntil: 'load', timeout: 60000 });
  // laisser charger images distantes + polices + tailwind CDN
  try { await page.waitForLoadState('networkidle', { timeout: 35000 }); } catch {}
  // attendre que Tailwind CDN ait généré les styles (bg-grad appliqué au logo)
  try { await page.waitForFunction(() => {
    const el = document.querySelector('.bg-grad'); if (!el) return false;
    return getComputedStyle(el).backgroundImage.includes('gradient');
  }, { timeout: 15000 }); } catch {}
  try { await page.waitForSelector('.hero-img.ready', { timeout: 20000 }); } catch {}
  await page.waitForTimeout(9500);
  // capture hero (haut de page)
  await page.screenshot({ path: path.join(OUT, `${name}.webp`), type: 'webp', quality: 82, clip: { x: 0, y: 0, width: 1440, height: 900 } });
  console.log('shot', name);
}

await browser.close();
console.log('DONE');
