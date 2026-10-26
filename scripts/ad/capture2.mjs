// Captures « image par image » en haute résolution (mobile 3x, desktop 2x) : chaque état de l'interface
// devient une image avec une durée de maintien, consommée ensuite par le compositeur.
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const OUT = process.env.OUT, SLUG = process.env.SLUG, READY_URL = process.env.READY_URL;
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

class Seq {
  constructor(name) { this.dir = path.join(OUT, name); fs.mkdirSync(this.dir, { recursive: true }); this.items = []; }
  async shot(page, hold, opts = {}) { const f = `s${String(this.items.length).padStart(4, '0')}.jpg`; await page.screenshot({ path: path.join(this.dir, f), type: 'jpeg', quality: 92, ...opts }); this.items.push({ f, hold }); }
  save() { fs.writeFileSync(path.join(this.dir, 'seq.json'), JSON.stringify(this.items)); log(path.basename(this.dir), this.items.length, 'images'); }
}

/* ── Mobile : landing, recherche, site prêt, site généré ── */
const mctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true });
const m = await mctx.newPage();
await m.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
try { await m.waitForLoadState('networkidle', { timeout: 15000 }); } catch {}
await sleep(800);
// Désactiver les animations de révélation pour des images nettes
await m.addStyleTag({ content: '.reveal{opacity:1!important;transform:none!important} .aurora{animation:none!important}' });

// M1 : typewriter (états successifs du texte tapé)
const s1 = new Seq('m1-typewriter');
await m.evaluate(() => document.getElementById('search-input').scrollIntoView({ block: 'center' })); await sleep(300);
for (let i = 0; i < 14; i++) { await s1.shot(m, 0.09); await sleep(70); }
s1.save();

// M2 : saisie « Bouillon Chartier » lettre par lettre
const s2 = new Seq('m2-typing');
await m.click('#search-input'); await sleep(200); await s2.shot(m, 0.25);
for (const ch of 'Bouillon Chartier') { await m.keyboard.type(ch); await sleep(40); await s2.shot(m, 0.075); }
try { await m.waitForSelector('text=Grands Boulevards', { timeout: 15000 }); } catch {}
await sleep(400); await s2.shot(m, 1.4);
s2.save();

// M3 : sélection → état « établissement choisi »
const s3 = new Seq('m3-selected');
await m.click('text=Grands Boulevards'); await sleep(500); await s3.shot(m, 1.2);
s3.save();

// M4 : page « site prêt » (site généré précédemment)
const s4 = new Seq('m4-ready');
await m.goto(READY_URL, { waitUntil: 'load', timeout: 60000 }); try { await m.waitForLoadState('networkidle', { timeout: 20000 }); } catch {} await sleep(2500);
await s4.shot(m, 2.0);
s4.save();

// M5 : site généré, défilement (une image tous les 36 px)
const s5 = new Seq('m5-site-scroll');
await m.goto(`https://${SLUG}.joow.fr/?v=${Date.now()}`, { waitUntil: 'load', timeout: 60000 }); try { await m.waitForLoadState('networkidle', { timeout: 20000 }); } catch {} await sleep(4000);
await m.evaluate(() => { document.getElementById('joow-curtain')?.remove(); document.querySelectorAll('.reveal').forEach((e) => e.classList.add('on')); document.querySelectorAll('.kb,.trust-track,.rev-track,.cta-band::before').forEach((e) => { e.style.animation = 'none'; }); });
await m.addStyleTag({ content: '.reveal{opacity:1!important;transform:none!important} *{animation-play-state:paused!important}' });
await sleep(400);
const H = Math.min(await m.evaluate(() => document.body.scrollHeight), 4600);
for (let y = 0; y <= H - 844; y += 36) { await m.evaluate((yy) => window.scrollTo(0, yy), y); await sleep(60); await s5.shot(m, 1 / 30); }
s5.save();
await mctx.close();

/* ── Desktop 2x : Studio (IA), console ── */
const dctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, ignoreHTTPSErrors: true });
const d = await dctx.newPage();
await d.goto('https://app.joow.fr/login', { waitUntil: 'load', timeout: 60000 }); await d.click('text=Mot de passe'); await d.fill('#email', process.env.JOOW_EMAIL); await d.fill('#password', process.env.JOOW_PASS);
await Promise.all([d.waitForNavigation({ timeout: 60000 }), d.click('button[type=submit]')]);
await d.goto(`https://app.joow.fr/sites/${SLUG}/editeur`, { waitUntil: 'load', timeout: 60000 }); await d.waitForSelector('iframe'); await sleep(7000);
const s6 = new Seq('d1-studio');
await s6.shot(d, 1.0);
await d.click('textarea'); await sleep(150);
const prompt = 'Passe le site en thème sombre et ajoute une FAQ sur la terrasse';
for (let i = 0; i < prompt.length; i++) { await d.keyboard.type(prompt[i]); if (i % 2 === 1 || i === prompt.length - 1) { await sleep(30); await s6.shot(d, 0.07); } }
await d.keyboard.press('Enter'); await sleep(1200); await s6.shot(d, 1.2);           // état « je réfléchis »
try { await d.waitForSelector('text=Annuler ces modifications', { timeout: 150000 }); } catch { log('IA timeout'); }
await sleep(6500); await s6.shot(d, 2.5);                                            // résultat + aperçu mis à jour
s6.save();
for (const [url, name] of [['/dashboard', 'dashboard'], ['/reservations', 'reservations'], ['/modules', 'modules'], ['/demandes', 'demandes'], ['/hebergement', 'hebergement']]) {
  await d.goto('https://app.joow.fr' + url, { waitUntil: 'load', timeout: 60000 }); try { await d.waitForLoadState('networkidle', { timeout: 10000 }); } catch {} await sleep(1500);
  await d.screenshot({ path: `${OUT}/${name}.png` });
}
await dctx.close();
await b.close(); log('DONE');
