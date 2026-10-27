/* Audit de contraste : pour chaque élément texte visible, couleur vs fond effectif (hors zones à image). */
import { chromium } from 'playwright';
import fs from 'node:fs';
const files = process.argv.slice(2);
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors', '--allow-file-access-from-files'] });
const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, ignoreHTTPSErrors: true })).newPage();
for (const f of files) {
  await page.goto('file://' + process.cwd() + '/' + f, { waitUntil: 'networkidle', timeout: 90000 }).catch(() => {});
  await page.waitForTimeout(1500);
  // révéler tout, figer les animations, simuler le défilement (nav solide)
  await page.addStyleTag({ content: '*{animation:none!important;transition:none!important}.reveal{opacity:1!important;transform:none!important}#joow-curtain{display:none!important}' });
  await page.evaluate(() => window.scrollTo(0, 600)); await page.waitForTimeout(400);
  const res = await page.evaluate(() => {
    const parse = (c) => { const m = c.match(/rgba?\(([^)]+)\)/); if (!m) return null; const [r, g, b, a = 1] = m[1].split(',').map(parseFloat); return { r, g, b, a }; };
    const lum = ({ r, g, b }) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
    const ratio = (a, b) => { const l1 = lum(a), l2 = lum(b); return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05); };
    const blend = (fg, bg) => ({ r: fg.r * fg.a + bg.r * (1 - fg.a), g: fg.g * fg.a + bg.g * (1 - fg.a), b: fg.b * fg.a + bg.b * (1 - fg.a), a: 1 });
    const bgOf = (el) => { let node = el; const layers = []; while (node && node !== document.documentElement) { const cs = getComputedStyle(node); if (cs.backgroundImage !== 'none' && !cs.backgroundImage.startsWith('linear-gradient') && !cs.backgroundImage.startsWith('radial-gradient')) return { image: true }; if (cs.backgroundImage.includes('gradient')) return { image: true }; const c = parse(cs.backgroundColor); if (c && c.a > 0) { layers.push(c); if (c.a >= 1) break; } if (node.querySelector && node !== el && [...node.children].some(ch => ch.tagName === 'IMG' && getComputedStyle(ch).position === 'absolute')) return { image: true }; node = node.parentElement; } let bg = { r: 255, g: 255, b: 255, a: 1 }; const bodyBg = parse(getComputedStyle(document.body).backgroundColor); if (bodyBg && bodyBg.a > 0) bg = blend(bodyBg, bg); for (const l of layers.reverse()) bg = blend(l, bg); return { color: bg }; };
    const imgs = [...document.querySelectorAll('img, video, [style*="background-image"], .bg-cover')].map(e => ({ r: e.getBoundingClientRect(), e })).filter(x => x.r.width > 80 && x.r.height > 80);
    const overImage = (el) => { const r = el.getBoundingClientRect(); const cx = r.left + r.width / 2, cy = r.top + r.height / 2; return imgs.some(x => !x.e.contains(el) && !el.contains(x.e) && cx >= x.r.left && cx <= x.r.right && cy >= x.r.top && cy <= x.r.bottom && (getComputedStyle(x.e).position === 'absolute' || getComputedStyle(x.e).objectFit === 'cover' || x.e.tagName === 'VIDEO' || (x.e.parentElement && x.e.parentElement.contains(el)))); };
    const out = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const seen = new Set();
    let n;
    while ((n = walker.nextNode())) {
      const t = n.textContent.trim(); if (t.length < 2) continue;
      const el = n.parentElement; if (!el || seen.has(el)) continue; seen.add(el);
      const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none' || parseFloat(cs.opacity) === 0) continue;
      const r = el.getBoundingClientRect(); if (r.width === 0 || r.height === 0) continue;
      if (el.closest('script,style,noscript,dialog,#joow-bot-panel,.joow-panel,[hidden]')) continue;
      const fg = parse(cs.color); if (!fg) continue;
      if (el.closest('.svc-num, .marq, [aria-hidden=true]')) continue;
      const bg = bgOf(el); if (bg.image) continue;
      if (overImage(el)) continue;
      const c = ratio(blend(fg, bg.color), bg.color);
      if (c < 3) out.push({ ratio: +c.toFixed(2), text: t.slice(0, 40), color: cs.color, bg: `rgb(${Math.round(bg.color.r)},${Math.round(bg.color.g)},${Math.round(bg.color.b)})`, path: [el, el.parentElement, el.parentElement?.parentElement].filter(Boolean).map(e => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).slice(0, 3).join('.') : '')).join(' < ') });
    }
    return out;
  });
  console.log(`\n=== ${f} : ${res.length} texte(s) sous 3:1`);
  res.slice(0, 25).forEach(r => console.log(`  ${r.ratio}  "${r.text}"  ${r.color} sur ${r.bg}\n       ${r.path}`));
}
await browser.close();
