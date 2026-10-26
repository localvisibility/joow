import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 1, viewport: { width: 390, height: 1200 }, ignoreHTTPSErrors: true })).newPage();
await p.goto('https://garage-degriff-auto-toulouse-ulco.joow.fr/', { waitUntil: 'networkidle', timeout: 60000 });
await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 150)); } });
let i = 0;
for (const y of [5400, 6600, 7800, 9000, 10200, 11400]) { await p.evaluate((y) => window.scrollTo(0, y), y); await p.waitForTimeout(700); await p.screenshot({ path: `${process.env.OUT}/low-${i++}.png` }); }
await b.close();
