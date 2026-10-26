import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 1, viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true })).newPage();
await p.goto(process.env.URL, { waitUntil: 'networkidle', timeout: 60000 });
console.log(JSON.stringify(await p.evaluate(() => {
  const W = document.getElementById('joow-wizard'); W.style.width = '350px'; W.style.maxWidth = '350px';
  const o = [];
  for (const el of [W, ...W.querySelectorAll('*')]) { if (el.scrollWidth > el.clientWidth + 1 && el.clientWidth > 0) o.push([el.tagName, (el.className||'').toString().slice(0,40), el.clientWidth, el.scrollWidth, (el.innerText||'').slice(0,30)]); }
  const css = [...document.styleSheets].flatMap(s => { try { return [...s.cssRules] } catch { return [] } }).filter(r => r.selectorText && /jw-step|jw-card\b|jw-nav|jw-next|jw-back/.test(r.selectorText)).map(r => r.cssText.slice(0, 200));
  return { o: o.slice(0, 20), css };
}), null, 1));
await b.close();
