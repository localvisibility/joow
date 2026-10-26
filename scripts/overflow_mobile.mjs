import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 1, viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true })).newPage();
await p.goto(process.env.URL, { waitUntil: 'networkidle', timeout: 60000 });
await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 100)); } });
const out = await p.evaluate(() => {
  const res = []; const W = 390;
  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect(); if (r.width === 0) continue;
    const sx = r.left + window.scrollX, ex = r.right;
    if (ex > W + 1 || r.left < -1) {
      const cs = getComputedStyle(el); if (cs.position === 'fixed') continue;
      res.push({ tag: el.tagName.toLowerCase(), cls: (el.className || '').toString().slice(0, 60), id: el.id, left: Math.round(r.left), right: Math.round(ex), top: Math.round(r.top + window.scrollY), text: (el.innerText || '').replace(/\s+/g, ' ').slice(0, 40) });
    }
  }
  return { sw: document.documentElement.scrollWidth, items: res.slice(0, 40) };
});
console.log(JSON.stringify(out, null, 1));
await p.evaluate(() => window.scrollTo(0, 11400)); await p.waitForTimeout(600);
await p.screenshot({ path: process.env.OUT + '/low-foot.png', fullPage: false });
await b.close();
