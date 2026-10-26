import { chromium, devices } from 'playwright';
const out = process.env.OUT;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
for (const [name, ctxOpts] of [['desk', { viewport: { width: 1440, height: 900 } }], ['mob', { ...devices['iPhone 13'] }]]) {
  const ctx = await browser.newContext({ ...ctxOpts, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  for (const p of ['/decouvrir', '/tarifs']) {
    await page.goto('https://app.joow.fr' + p, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(900);
    await page.screenshot({ path: `${out}/v2-${name}${p.replace(/\W/g, '_')}-top.png` });
    if (p === '/decouvrir') {
      for (const y of [1400, 3200, 5600]) { await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), y); await page.waitForTimeout(1100); await page.screenshot({ path: `${out}/v2-${name}-home-${y}.png` }); }
    } else {
      const r = await page.evaluate(() => [[195, 600], [195, 650], [100, 640]].map(([x, y]) => { const el = document.elementFromPoint(x, y); return el ? `${x},${y}: <${el.tagName.toLowerCase()} class="${(el.className || '').toString().slice(0, 80)}"> pos=${getComputedStyle(el).position} bg=${getComputedStyle(el).backgroundColor}` : `${x},${y}: none`; }));
      console.log(name, p, r);
    }
  }
  await ctx.close();
}
await browser.close();
