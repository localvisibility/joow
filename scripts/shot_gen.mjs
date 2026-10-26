import { chromium, devices } from 'playwright';
const out = process.env.OUT;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
for (const [name, ctxOpts] of [['desk', { viewport: { width: 1440, height: 900 } }], ['mob', { ...devices['iPhone 13'] }]]) {
  const ctx = await browser.newContext({ ...ctxOpts, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  await page.goto('https://app.joow.fr/decouvrir', { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(800);
  const el = await page.$('.search-shell');
  if (name === 'mob') await el.scrollIntoViewIfNeeded();
  await page.fill('.search-shell input', 'Bouillon Chartier');
  await page.waitForTimeout(2500);
  await page.screenshot({ path: `${out}/v3-${name}-gen.png` });
  console.log(name, await page.evaluate(() => { const r = document.querySelector('.gen-results'); return r ? `results hidden=${r.classList.contains('hidden')} items=${r.children.length} pos=${getComputedStyle(r).position}` : 'no results box'; }));
  await ctx.close();
}
await browser.close();
