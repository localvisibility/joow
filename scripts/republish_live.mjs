// Republie un site via le Studio (session admin) puis capture le site en ligne.
// Usage : SLUG=... OUT=... node scripts/republish_live.mjs
import { chromium } from 'playwright';
const PROXY = process.env.HTTPS_PROXY;
const OUT = process.env.OUT || '/tmp';
const SLUG = process.env.SLUG;
const EMAIL = process.env.JOOW_EMAIL, PASS = process.env.JOOW_PASS;
if (!SLUG || !EMAIL || !PASS) { console.error('SLUG/JOOW_EMAIL/JOOW_PASS requis'); process.exit(1); }

const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  proxy: PROXY ? { server: PROXY } : undefined,
  args: ['--ignore-certificate-errors'],
});
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1.25, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

// 1. Connexion
await page.goto('https://app.joow.fr/login', { waitUntil: 'load', timeout: 60000 });
await page.fill('input[type=email]', EMAIL);
await page.fill('input[type=password]', PASS);
await Promise.all([page.waitForNavigation({ timeout: 60000 }), page.click('button[type=submit]')]);
console.log('logged in:', page.url());

// 2. Publication (fetch same-origin avec XSRF)
const res = await page.evaluate(async (slug) => {
  const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch(`/sites/${slug}/editeur/publish`, {
    method: 'POST', headers: { 'X-XSRF-TOKEN': xsrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  return { status: r.status, body: await r.text() };
}, SLUG);
console.log('publish:', res.status, res.body.slice(0, 200));

// 3. Captures du site en ligne
const live = `https://${SLUG}.joow.fr/?v=${Date.now()}`;
await page.goto(live, { waitUntil: 'load', timeout: 60000 });
try { await page.waitForLoadState('networkidle', { timeout: 30000 }); } catch {}
await page.waitForTimeout(3500);
await page.evaluate(() => document.getElementById('joow-curtain')?.remove());
await page.screenshot({ path: `${OUT}/live-hero.webp`, type: 'webp', quality: 80 });

const html = await page.content();
const marks = ['class="trust', 'class="bento', 'svc feat', 'class="steps', 'rating-big', 'rev-track', 'cta-band', 'id="joow-lb"', 'foot-grid', 'mobile-bar'];
console.log('markers:', marks.map(m => `${m}=${html.includes(m) ? 1 : 0}`).join(' '));

for (const sel of ['#services', '#parcours', '#avis', '.cta-band', 'footer']) {
  const ok = await page.evaluate((s) => { const el = document.querySelector(s); if (!el) return false; el.scrollIntoView({ block: 'start' }); return true; }, sel);
  if (!ok) { console.log('absent:', sel); continue; }
  await page.waitForTimeout(1800);
  await page.screenshot({ path: `${OUT}/live-${sel.replace(/[^a-z]/g, '')}.webp`, type: 'webp', quality: 80 });
}

// Mobile
const m = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, ignoreHTTPSErrors: true });
const mp = await m.newPage();
await mp.goto(live, { waitUntil: 'load', timeout: 60000 });
await mp.waitForTimeout(3500);
await mp.evaluate(() => document.getElementById('joow-curtain')?.remove());
await mp.screenshot({ path: `${OUT}/live-mobile.webp`, type: 'webp', quality: 80 });
await mp.evaluate(() => document.querySelector('#services')?.scrollIntoView());
await mp.waitForTimeout(1500);
await mp.screenshot({ path: `${OUT}/live-mobile-services.webp`, type: 'webp', quality: 80 });

await browser.close();
console.log('DONE');
