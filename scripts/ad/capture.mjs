// Captures réelles pour la vidéo publicitaire : parcours mobile (landing → génération → site), site généré,
// Studio IA (desktop), console. Sorties : dossiers de frames + list.txt (concat ffmpeg) dans OUT/<clip>/.
import { chromium } from 'playwright';
import fs from 'node:fs';
import { startRecorder, sleep } from './recorder.mjs';

const OUT = process.env.OUT; const SLUG = process.env.SLUG;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);
const typeHuman = async (page, sel, text) => { await page.click(sel); for (const ch of text) { await page.keyboard.type(ch); await sleep(55 + Math.random() * 70); } };

/* ── R1 : mobile, landing → recherche → génération → site prêt ── */
{
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
  try { await p.waitForLoadState('networkidle', { timeout: 15000 }); } catch {}
  await sleep(500);
  const rec = await startRecorder(p, OUT + '/r1-mobile-flow', { maxWidth: 1170, maxHeight: 2532 });
  await sleep(2500);                                   // typewriter
  await p.evaluate(() => document.getElementById('search-input').scrollIntoView({ block: 'center', behavior: 'smooth' })); await sleep(900);
  await typeHuman(p, '#search-input', 'Bouillon Chartier');
  await p.waitForSelector('text=Grands Boulevards', { timeout: 15000 }); await sleep(1200);
  await p.click('text=Grands Boulevards'); await sleep(1200);
  await p.click('text=Générer mon site'); log('génération lancée');
  await p.waitForURL(/\/site\//, { timeout: 60000 }); await sleep(2000);
  try { await p.waitForSelector('text=Votre site est prêt', { timeout: 120000 }); } catch { log('timeout site prêt'); }
  await sleep(3500);
  const r = await rec.stop(); log('R1', r); fs.writeFileSync(OUT + '/r1-url.txt', p.url());
  await ctx.close();
}

/* ── R2 : site généré sur mobile, défilement ── */
{
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto(`https://${SLUG}.joow.fr/?v=${Date.now()}`, { waitUntil: 'load', timeout: 60000 });
  try { await p.waitForLoadState('networkidle', { timeout: 20000 }); } catch {}
  await sleep(4500);
  const rec = await startRecorder(p, OUT + '/r2-mobile-site', { maxWidth: 1170, maxHeight: 2532 });
  await sleep(1500);
  await p.evaluate(async () => { const H = document.body.scrollHeight; for (let y = 0; y < Math.min(H, 4200); y += 14) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 16)); } });
  await sleep(800);
  const r = await rec.stop(); log('R2', r); await ctx.close();
}

/* ── R3 : Studio (desktop) : IA applique un changement ── */
{
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://app.joow.fr/login', { waitUntil: 'load', timeout: 60000 }); await p.click('text=Mot de passe'); await p.fill('#email', process.env.JOOW_EMAIL); await p.fill('#password', process.env.JOOW_PASS);
  await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('button[type=submit]')]);
  await p.goto(`https://app.joow.fr/sites/${SLUG}/editeur`, { waitUntil: 'load', timeout: 60000 }); await p.waitForSelector('iframe'); await sleep(6000);
  const rec = await startRecorder(p, OUT + '/r3-studio', { maxWidth: 2880, maxHeight: 1800 });
  await sleep(1200);
  await typeHuman(p, 'textarea', 'Rends le titre plus percutant et ajoute une FAQ sur la terrasse');
  await sleep(400); await p.keyboard.press('Enter');
  try { await p.waitForSelector('text=Annuler ces modifications', { timeout: 150000 }); } catch { log('IA timeout'); }
  await sleep(5000);
  const r = await rec.stop(); log('R3', r);
  // R4 : console (dashboard, réservations, modules) — plans fixes animés au montage
  for (const [url, name] of [['/dashboard', 'dashboard'], ['/reservations', 'reservations'], ['/modules', 'modules'], ['/demandes', 'demandes']]) {
    await p.goto('https://app.joow.fr' + url, { waitUntil: 'load', timeout: 60000 }); try { await p.waitForLoadState('networkidle', { timeout: 10000 }); } catch {} await sleep(1500);
    await p.screenshot({ path: `${OUT}/${name}.png` });
  }
  await p.goto(`https://app.joow.fr/sites/${SLUG}/editeur`, { waitUntil: 'load', timeout: 60000 }); await p.waitForSelector('iframe'); await sleep(6000); await p.screenshot({ path: `${OUT}/studio.png` });
  await ctx.close();
}

/* ── R5 : landing desktop, scène 3D ── */
{
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 }); try { await p.waitForLoadState('networkidle', { timeout: 15000 }); } catch {} await sleep(2000);
  const rec = await startRecorder(p, OUT + '/r5-landing', { maxWidth: 2880, maxHeight: 1800 });
  await p.mouse.move(900, 300); await sleep(300);
  for (let i = 0; i <= 60; i++) { await p.mouse.move(900 + Math.sin(i / 10) * 300, 300 + Math.cos(i / 10) * 150); await sleep(50); }
  await sleep(2500);
  const r = await rec.stop(); log('R5', r); await ctx.close();
  // stills : sites de démo
  const pp = await (await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, ignoreHTTPSErrors: true })).newPage();
  await pp.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 }); await sleep(2000);
  await pp.screenshot({ path: `${OUT}/landing.png` });
}
await b.close(); log('DONE');
