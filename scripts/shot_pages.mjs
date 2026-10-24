import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true })).newPage();
let i = 0;
for (const path of process.env.PATHS.split(',')) {
  await p.goto(process.env.BASE + path, { waitUntil: 'networkidle', timeout: 60000 });
  await p.evaluate(async () => { for (let y = 0; y < Math.min(document.body.scrollHeight, 3000); y += 400) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
  await p.waitForTimeout(600);
  await p.screenshot({ path: `${process.env.OUT}/pg-${i++}.png`, fullPage: false });
  const h1 = await p.$eval('h1', (e) => e.innerText).catch(() => '—');
  console.log(path, '→', h1.replace(/\s+/g, ' ').slice(0, 80), '| blocs:', await p.$$eval('section', (s) => s.length));
}
await b.close();
