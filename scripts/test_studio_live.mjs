// Test du Studio en ligne (compte admin) : agent IA réel, page créée, servie par nginx.
// Usage : SLUG=… JOOW_EMAIL=… JOOW_PASS=… OUT=… node scripts/test_studio_live.mjs
import { chromium } from 'playwright';
const { SLUG, OUT } = process.env; const BASE = 'https://app.joow.fr';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const ctx = await b.newContext({ viewport: { width: 1600, height: 950 }, ignoreHTTPSErrors: true });
const p = await ctx.newPage();
const errors = []; p.on('pageerror', (e) => errors.push('page: ' + e.message));
const log = (...a) => console.log(...a);
const shot = (n) => p.screenshot({ path: `${OUT}/live-studio-${n}.webp`, type: 'webp', quality: 80 });

await p.goto(`${BASE}/login`, { waitUntil: 'load', timeout: 60000 });
await p.fill('input[type=email]', process.env.JOOW_EMAIL); await p.fill('input[type=password]', process.env.JOOW_PASS);
await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('button[type=submit]')]);
await p.goto(`${BASE}/sites/${SLUG}/editeur`, { waitUntil: 'load', timeout: 60000 });
await p.waitForSelector('iframe'); await p.waitForTimeout(5000);
await shot('ia');

// Agent IA : demande large
await p.fill('textarea', process.env.PROMPT || 'Crée une page « Notre histoire » qui raconte l\'histoire du bouillon depuis 1896, avec des chiffres clés, une galerie de photos et un appel à l\'action pour réserver. Ajoute aussi 2 questions à la FAQ de l\'accueil sur les groupes et les horaires.');
await p.keyboard.press('Enter');
const t0 = Date.now();
try { await p.waitForSelector('text=Annuler ces modifications', { timeout: 170000 }); } catch { log('IA: pas de bouton annuler'); }
log('IA durée:', Math.round((Date.now() - t0) / 1000) + 's');
await p.waitForTimeout(3000);
log('réponse IA:', await p.evaluate(() => [...document.querySelectorAll('.whitespace-pre-line')].at(-1)?.textContent.trim().slice(0, 400)));
log('appliqué:', await p.evaluate(() => [...document.querySelectorAll('.text-emerald-300')].map((e) => e.textContent.trim()).filter((t) => t.startsWith('✓')).join(' | ')));
const tabs = await p.evaluate(() => [...document.querySelectorAll('.ptab')].map((e) => e.textContent.trim()));
log('onglets:', tabs.join(' | '));
await shot('ia-result');
// Ouvrir la nouvelle page dans l'aperçu
if (tabs.length >= 3) {
  const el = (await p.$$('.ptab'))[tabs.length - 2]; await el.click(); await p.waitForTimeout(6000); await shot('page');
  const pslug = await p.evaluate(() => new URL(document.querySelector('iframe').src).searchParams.get('page'));
  log('page slug:', pslug);
  // Servie par nginx ?
  const r = await p.request.get(`https://${SLUG}.joow.fr/${pslug}/`, { ignoreHTTPSErrors: true });
  log('nginx page status:', r.status(), (await r.text()).includes('data-page') ? 'html ok' : 'html?');
  const home = await (await p.request.get(`https://${SLUG}.joow.fr/?v=${Date.now()}`)).text();
  log('home nav has page link:', home.includes(`/${pslug}/`));
}
log('errors:', errors.length ? errors.join('\n') : 'none');
await b.close(); log('DONE');
