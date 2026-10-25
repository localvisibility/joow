import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const OUT = process.env.OUT;
for (const [name, ctxOpts] of [['m', { ...devices['iPhone 13'] }], ['d', { viewport: { width: 1280, height: 900 } }]]) {
  const p = await (await b.newContext({ ...ctxOpts, ignoreHTTPSErrors: true })).newPage();
  await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 }); await p.waitForTimeout(800);
  await p.screenshot({ path: `${OUT}/lm-${name}-google.png` });
  await p.click('button:has-text("J\'ai déjà un site")'); await p.waitForTimeout(400);
  await p.keyboard.type('www.bouillon-chartier.com'); await p.waitForTimeout(500);
  await p.screenshot({ path: `${OUT}/lm-${name}-site.png` });
  console.log(name, 'placeholder:', await p.getAttribute('#search-input', 'placeholder'), '| carte refonte:', !!(await p.$('text=Refaire le site')));
  await p.close();
}
await b.close();
