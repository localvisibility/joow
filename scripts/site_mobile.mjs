import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], ignoreHTTPSErrors: true })).newPage();
await p.goto('https://garage-degriff-auto-toulouse-ulco.joow.fr/', { waitUntil: 'networkidle', timeout: 60000 });
await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
await p.waitForTimeout(800);
await p.screenshot({ path: process.env.OUT + '/m-gsite.png', fullPage: true });
console.log('h', await p.evaluate(() => document.body.scrollHeight));
await b.close();
