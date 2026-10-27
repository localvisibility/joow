import { chromium, devices } from 'playwright';
const out = process.env.OUT;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
for (const [name, opts, url] of [['desk-vitrine', { viewport: { width: 1440, height: 900 } }, 'https://joow.fr/'], ['mob-app', { ...devices['iPhone 13'] }, 'https://app.joow.fr/'], ['mob-vitrine-perso', { ...devices['iPhone 13'] }, 'https://joow.fr/tarifs']]) {
  const ctx = await browser.newContext({ ...opts, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 }); await page.waitForTimeout(800);
  if (name.endsWith('perso')) { await page.click('text=Personnaliser'); await page.waitForTimeout(400); }
  await page.screenshot({ path: `${out}/consent-${name}.png` });
  if (name === 'mob-app') { await page.click('button.btn-brand:has-text("Accepter")'); await page.waitForTimeout(1500); const c = await ctx.cookies(); console.log('cookies after accept:', c.filter(k => ['joow_consent', '_fbp'].includes(k.name)).map(k => k.name + '=' + k.value.slice(0, 12)).join(' ')); }
  await ctx.close();
}
await browser.close();
