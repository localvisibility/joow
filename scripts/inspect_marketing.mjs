import { chromium, devices } from 'playwright';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const probe = async (page, pts) => page.evaluate((pts) => pts.map(([x, y]) => { const el = document.elementFromPoint(x, y); if (!el) return `${x},${y}: none`; const cs = getComputedStyle(el); const r = el.getBoundingClientRect(); return `${x},${y}: <${el.tagName.toLowerCase()} class="${(el.className || '').toString().slice(0, 90)}"> z=${cs.zIndex} pos=${cs.position} op=${cs.opacity} bg=${cs.backgroundColor} rect=${Math.round(r.x)},${Math.round(r.y)},${Math.round(r.width)},${Math.round(r.height)}`; }), pts);
for (const [name, ctxOpts, pts] of [['desk', { viewport: { width: 1440, height: 900 } }, [[130, 35], [700, 35], [400, 550], [400, 520]]], ['mob', { ...devices['iPhone 13'] }, [[100, 40], [195, 300], [195, 600], [195, 750]]]]) {
  const ctx = await browser.newContext({ ...ctxOpts, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  await page.goto('https://app.joow.fr/decouvrir', { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(800);
  console.log(`== ${name} /decouvrir top`); (await probe(page, pts)).forEach(l => console.log(' ', l));
  const info = await page.evaluate(() => { const nav = document.querySelector('#mnav'); const r = nav.getBoundingClientRect(); const cs = getComputedStyle(nav); const hero = document.querySelector('.reveal'); return { nav: [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height), cs.opacity, cs.visibility, cs.transform], scrollY: window.scrollY, revealOn: document.querySelectorAll('.reveal.on').length, reveal: document.querySelectorAll('.reveal').length, docH: document.documentElement.scrollHeight, bodyBg: getComputedStyle(document.body).backgroundColor }; });
  console.log('  ', JSON.stringify(info));
  if (name === 'mob') {
    const big = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width >= innerWidth * 0.9 && r.height >= innerHeight * 0.8 && r.top <= 50 && (cs.position === 'fixed' || cs.position === 'absolute') && cs.pointerEvents !== 'none'; }).map(el => `<${el.tagName.toLowerCase()} class="${(el.className || '').toString().slice(0, 100)}"> z=${getComputedStyle(el).zIndex} bg=${getComputedStyle(el).backgroundColor}`).slice(0, 8));
    console.log('  covering:', big);
    await page.goto('https://app.joow.fr/tarifs', { waitUntil: 'networkidle' }); await page.waitForTimeout(500);
    console.log('== mob /tarifs bottom'); (await probe(page, [[195, 780], [195, 830], [50, 800]])).forEach(l => console.log(' ', l));
  }
  if (name === 'desk') {
    await page.evaluate(() => window.scrollTo(0, 4000)); await page.waitForTimeout(1200);
    const ex = await page.evaluate(() => [...document.querySelectorAll('.reveal:not(.on)')].slice(0, 6).map(el => { const r = el.getBoundingClientRect(); return `<${el.tagName.toLowerCase()} class="${(el.className || '').toString().slice(0, 80)}"> top=${Math.round(r.top + scrollY)} h=${Math.round(r.height)}`; }));
    console.log('  reveal not on after scroll to 4000:', ex);
  }
  await ctx.close();
}
await browser.close();
