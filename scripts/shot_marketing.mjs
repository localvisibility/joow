import { chromium, devices } from 'playwright';
const out = process.env.OUT; const base = 'https://app.joow.fr';
const pages = ['/decouvrir', '/fonctionnalites', '/site-internet/plombier', '/tarifs', '/blog', '/blog/fiche-google-business-10-optimisations', '/exemples', '/contact'];
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const errors = [];
for (const [name, ctxOpts] of [['desk', { viewport: { width: 1440, height: 900 } }], ['mob', { ...devices['iPhone 13'] }]]) {
  const ctx = await browser.newContext({ ...ctxOpts, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  page.on('pageerror', e => errors.push(`${name} pageerror: ${e.message}`));
  page.on('console', m => { if (m.type() === 'error') errors.push(`${name} console: ${m.text().slice(0, 160)}`); });
  for (const p of pages) {
    await page.goto(base + p, { waitUntil: 'networkidle', timeout: 60000 });
    await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
    await page.waitForTimeout(600);
    const hs = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (hs) errors.push(`${name} ${p}: horizontal scroll`);
    await page.screenshot({ path: `${out}/mk-${name}-${p.replace(/[^a-z0-9]+/g, '_')}.png`, fullPage: name === 'mob' ? false : false });
    if (name === 'desk' && p === '/decouvrir') await page.screenshot({ path: `${out}/mk-desk-home-full.png`, fullPage: true });
  }
  await ctx.close();
}
await browser.close();
console.log(errors.length ? errors.join('\n') : 'no errors');
