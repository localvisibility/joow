// Captures des sites de démonstration : hero desktop, page complète (défilement), mobile, et zooms produit.
import { chromium } from 'playwright';
import { readdirSync } from 'node:fs';
import path from 'node:path';

const SRC = process.env.SRC, OUT = process.env.OUT;
const files = readdirSync(SRC).filter(f => f.endsWith('.html'));
const PROXY = process.env.HTTPS_PROXY || process.env.https_proxy;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: PROXY ? { server: PROXY } : undefined, args: ['--ignore-certificate-errors'] });

const settle = async (page) => {
  try { await page.waitForLoadState('networkidle', { timeout: 35000 }); } catch {}
  try { await page.waitForFunction(() => { const el = document.querySelector('.bg-grad'); return el && getComputedStyle(el).backgroundImage.includes('gradient'); }, { timeout: 15000 }); } catch {}
  try { await page.waitForSelector('.hero-img.ready', { timeout: 20000 }); } catch {}
  await page.waitForTimeout(6000);
  await page.evaluate(() => { document.getElementById('joow-curtain')?.remove(); document.querySelectorAll('.reveal').forEach(e => e.classList.add('in', 'on')); });
};

const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, ignoreHTTPSErrors: true });
const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, ignoreHTTPSErrors: true });
const page = await desktop.newPage(); const mp = await mobile.newPage();

for (const f of files) {
  const name = path.basename(f, '.html');
  await page.goto('file://' + path.join(SRC, f), { waitUntil: 'load', timeout: 60000 }); await settle(page);
  await page.screenshot({ path: path.join(OUT, `${name}.webp`), type: 'webp', quality: 82, clip: { x: 0, y: 0, width: 1440, height: 900 } });
  // page complète (limitée) pour l'effet de défilement au survol
  const h = Math.min(await page.evaluate(() => document.documentElement.scrollHeight), 5200);
  await page.evaluate(() => { document.querySelectorAll('.reveal,.stagger>*').forEach(e => { e.style.opacity = 1; e.style.transform = 'none'; }); });
  await page.screenshot({ path: path.join(OUT, `${name}-full.webp`), type: 'webp', quality: 72, fullPage: false, clip: { x: 0, y: 0, width: 1440, height: h } });
  // mobile
  await mp.goto('file://' + path.join(SRC, f), { waitUntil: 'load', timeout: 60000 }); await settle(mp);
  await mp.screenshot({ path: path.join(OUT, `${name}-mobile.webp`), type: 'webp', quality: 82, clip: { x: 0, y: 0, width: 390, height: 844 } });
  console.log('shot', name, h);
}

// Zooms produit (modules) sur des échantillons
const shots = [['restaurant', '#joow-resa', 'module-resa'], ['renovation', '#joow-form', 'module-form'], ['restaurant', '#carte', 'module-menu'], ['sante', '#services', 'module-bento']];
for (const [sector, sel, out] of shots) {
  await page.goto('file://' + path.join(SRC, sector + '.html'), { waitUntil: 'load', timeout: 60000 }); await settle(page);
  const el = page.locator(sel).first();
  if (!(await el.count())) { console.log('absent', sector, sel); continue; }
  await el.scrollIntoViewIfNeeded(); await page.waitForTimeout(1500);
  await page.evaluate(() => { document.querySelectorAll('.reveal,.stagger>*').forEach(e => { e.style.opacity = 1; e.style.transform = 'none'; }); });
  await el.screenshot({ path: path.join(OUT, `${out}.webp`), type: 'webp', quality: 82 });
  console.log('shot', out);
}
await browser.close(); console.log('DONE');
