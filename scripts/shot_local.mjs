import { chromium, devices } from 'playwright';
const out = process.env.OUT;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
for (const [name, ctxOpts] of [['desk', { viewport: { width: 1440, height: 900 } }], ['mob', { ...devices['iPhone 13'] }]]) {
  const ctx = await browser.newContext({ ...ctxOpts, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  await page.goto('https://joow.fr/site-internet/plombier/lyon', { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${out}/local-${name}-top.png` });
  await page.evaluate(() => window.scrollTo({ top: 900, behavior: 'instant' })); await page.waitForTimeout(1000);
  await page.screenshot({ path: `${out}/local-${name}-mid.png` });
  await ctx.close();
}
await browser.close();
