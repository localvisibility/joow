import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 1, viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true })).newPage();
await p.goto(process.env.URL, { waitUntil: 'networkidle', timeout: 60000 });
console.log(JSON.stringify(await p.evaluate(() => {
  const W = document.getElementById('joow-wizard'); const sec = document.getElementById('reserver');
  const wide = [];
  for (const el of [sec, sec.firstElementChild, W, ...W.querySelectorAll('*')]) { const r = el.getBoundingClientRect(); if (r.width > 352) wide.push([el.tagName, (el.className||'').toString().slice(0,50), Math.round(r.left), Math.round(r.width), getComputedStyle(el).display]); }
  return { gridW: Math.round(sec.querySelector('.grid').getBoundingClientRect().width), wizard: Math.round(W.getBoundingClientRect().width), wide: wide.slice(0, 25) };
}), null, 1));
await b.close();
