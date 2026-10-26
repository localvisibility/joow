import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const p = await (await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 1, viewport: { width: 390, height: 844 } })).newPage();
await p.goto('file://' + process.env.FILE, { waitUntil: 'load', timeout: 60000 });
const r = await p.evaluate(() => { const W = document.getElementById('joow-wizard'); return { w: Math.round(W.getBoundingClientRect().width), sw: document.documentElement.scrollWidth, titles: [...W.querySelectorAll('.jw-ptitle')].map(t => getComputedStyle(t).display) }; });
console.log(JSON.stringify(r));
await p.evaluate(() => document.getElementById('joow-wizard').scrollIntoView()); await p.waitForTimeout(700);
await p.screenshot({ path: process.env.OUT + '/wiz-local.png' });
await b.close();
