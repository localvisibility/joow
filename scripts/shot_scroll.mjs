import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true })).newPage();
await p.goto(process.env.URL, { waitUntil: 'networkidle', timeout: 60000 });
await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } });
let i = 0; for (const y of (process.env.YS || '0,900,1800').split(',')) { await p.evaluate((y) => window.scrollTo(0, +y), y); await p.waitForTimeout(700); await p.screenshot({ path: `${process.env.OUT}/sc-${i++}.png` }); }
await b.close();
