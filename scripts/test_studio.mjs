// Test local du Studio : accès invité par lien signé, pages, blocs, agent IA.
// Usage : BASE=http://127.0.0.1:8123 SLUG=… TOKEN=… OUT=… node scripts/test_studio.mjs
import { chromium } from 'playwright';
const { BASE, SLUG, TOKEN, OUT } = process.env;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY, bypass: '127.0.0.1,localhost' }, args: ['--ignore-certificate-errors'] });
const ctx = await b.newContext({ viewport: { width: 1600, height: 950 }, ignoreHTTPSErrors: true });
const p = await ctx.newPage();
const errors = []; p.on('pageerror', (e) => errors.push('page: ' + e.message)); p.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 200)); });
const shot = (n) => p.screenshot({ path: `${OUT}/studio-${n}.webp`, type: 'webp', quality: 80 });
const log = (...a) => console.log(...a);

// 0. Sans jeton → 403
const r0 = await p.goto(`${BASE}/sites/${SLUG}/editeur`, { waitUntil: 'load' }); log('sans jeton:', r0.status());
// 1. Avec jeton → Studio
const r1 = await p.goto(`${BASE}/sites/${SLUG}/editeur?t=${TOKEN}`, { waitUntil: 'load' }); log('avec jeton:', r1.status());
await p.waitForSelector('iframe'); await p.waitForTimeout(4000);
await shot('ia');
// 2. Session mémorisée → accès sans jeton
const r2 = await p.goto(`${BASE}/sites/${SLUG}/editeur`, { waitUntil: 'load' }); log('session:', r2.status());
await p.waitForSelector('iframe'); await p.waitForTimeout(3000);

// 3. Page vide via l'UI
await p.click('text=+ Page'); await p.fill('input[placeholder^="Ex : Nos réalisations"]', 'Nos prestations');
await p.click('text=Page vide'); await p.click('text=Créer la page'); await p.waitForTimeout(3500);
log('onglets:', await p.evaluate(() => [...document.querySelectorAll('.ptab')].map((e) => e.textContent.trim()).join(' | ')));
await shot('page-vide');
// 4. Ajouter un bloc FAQ
await p.click('text=+ Ajouter un bloc'); await p.click('text=Questions / réponses'); await p.waitForTimeout(3500);
await shot('bloc-ajoute');
// URL de l'aperçu et contenu
const iframeSrc = await p.evaluate(() => document.querySelector('iframe').src); log('iframe:', iframeSrc);
const fr = p.frames().find((f) => f.url().includes('/editeur/preview'));
if (fr) { log('preview h1:', await fr.evaluate(() => document.querySelector('h1')?.textContent.trim())); log('preview blocks:', await fr.evaluate(() => [...document.querySelectorAll('[data-section^=block-]')].map((e) => e.dataset.label).join(' | '))); log('nav pages:', await fr.evaluate(() => [...document.querySelectorAll('#links a[data-page]')].map((a) => a.textContent.trim() + '→' + a.getAttribute('href')).join(' | '))); }

// 5. Édition en place dans l'aperçu (titre du bloc)
if (fr) {
  const el = fr.locator('[data-edit$=".blocks.1.title"]').first();
  if (await el.count()) { await el.click(); await fr.page().keyboard.press('Control+A'); await fr.page().keyboard.type('Vos questions, nos réponses'); await fr.page().keyboard.press('Enter'); await p.waitForTimeout(1500); log('titre bloc édité:', await p.evaluate(() => document.body.innerText.includes('Vos questions, nos réponses'))); }
}

// 6. Agent IA (appel Gemini réel)
if (process.env.AI !== '0') {
  await p.click('text=✨ IA'); await p.waitForTimeout(300);
  await p.fill('textarea', 'Crée une page « Nos réalisations » avec une galerie, trois témoignages et un appel à l\'action. Passe aussi la couleur principale en bordeaux.');
  await p.keyboard.press('Enter');
  const t0 = Date.now();
  try { await p.waitForSelector('text=Annuler ces modifications', { timeout: 150000 }); } catch { log('IA: pas de bouton annuler (timeout ou aucune modif)'); }
  log('IA durée:', Math.round((Date.now() - t0) / 1000) + 's');
  await p.waitForTimeout(3000);
  log('réponse IA:', await p.evaluate(() => [...document.querySelectorAll('.whitespace-pre-line')].at(-1)?.textContent.trim().slice(0, 300)));
  log('appliqué:', await p.evaluate(() => [...document.querySelectorAll('.text-emerald-300')].map((e) => e.textContent.trim()).filter((t) => t.startsWith('✓')).join(' | ')));
  log('onglets:', await p.evaluate(() => [...document.querySelectorAll('.ptab')].map((e) => e.textContent.trim()).join(' | ')));
  await shot('ia-result');
  const tabs = await p.$$('.ptab'); if (tabs.length >= 3) { await tabs[tabs.length - 2].click(); await p.waitForTimeout(4000); await shot('page-ia'); }
}
log('errors:', errors.length ? errors.join('\n') : 'none');
await b.close(); log('DONE');
