// Questionnaire sur iPhone 13 : accueil → « Je n'ai pas de fiche Google » → 5 étapes → génération → Studio
import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], ignoreHTTPSErrors: true })).newPage();
p.on('pageerror', (e) => console.log('[pageerror]', String(e).slice(0, 200)));
const OUT = process.env.OUT; const shot = (n) => p.screenshot({ path: `${OUT}/i-${n}.png` });
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type('Zorglub Rénovation Albi');
await p.waitForSelector('text=Je n\'ai pas de fiche Google', { timeout: 30000 }); await p.waitForTimeout(600); await shot('0-landing');
await Promise.all([p.waitForURL(/\/creer/, { timeout: 30000 }), p.click('text=Je n\'ai pas de fiche Google')]);
await p.waitForTimeout(800); await shot('1-who');
// Étape 1 : nom prérempli, ville
const nameVal = await p.inputValue('input[placeholder="Ex : Plomberie Martin"]'); console.log('nom prérempli :', nameVal);
await p.fill('input[placeholder="Ex : Plomberie Martin"]', 'Zorglub Rénovation'); await p.fill('input[placeholder="Ex : Toulouse"]', 'Albi');
await p.waitForTimeout(1800); console.log('fiche suggérée :', !!(await p.$('text=C\'est vous ?')));
await p.click(".fixed button:has-text(\"Continuer\")"); await p.waitForTimeout(800); await shot('1b-after'); console.log('erreur affichée :', await (await p.$('[role=alert]'))?.innerText() || 'non', '| url', p.url());
// Étape 2 : métier
await p.waitForSelector('input[placeholder^="Tapez votre métier"]'); await p.fill('input[placeholder^="Tapez votre métier"]', 'rénovation');
await p.waitForTimeout(300); await shot('2-sector');
const chips = await p.$$('main button.rounded-full'); console.log('métiers proposés :', (await Promise.all(chips.slice(0, 5).map((c) => c.innerText()))).join(' · '));
await chips[0].click(); await p.click(".fixed button:has-text(\"Continuer\")");
// Étape 3 : coordonnées
await p.fill('input[type=tel]', '06 11 22 33 44'); await p.fill('input[type=email]', process.env.MAIL); await p.fill('input[placeholder^="Ex : Toulouse et"]', 'Albi et 40 km');
await shot('3-contact'); await p.click(".fixed button:has-text(\"Continuer\")");
// Étape 4 : prestations (suggestions IA)
await p.waitForTimeout(4000); await shot('4-offer');
const sugg = await p.$$('main button.rounded-full'); console.log('suggestions IA :', sugg.length);
for (const s of sugg.slice(0, 3)) await s.click();
await p.fill('textarea', 'Artisan depuis 2012, je rénove cuisines et salles de bain, je travaille seul et je tiens mes délais.');
await p.click(".fixed button:has-text(\"Continuer\")");
// Étape 5 : photos (on passe)
await p.waitForSelector('text=Choisir des photos'); await shot('5-photos');
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('button:has-text("Générer sans photo")')]);
console.log('génération lancée :', p.url());
await p.waitForTimeout(7000); await shot('6-wait');
const t0 = Date.now(); for (let i = 0; i < 90; i++) { if (await p.$('text=Votre site est prêt')) break; await p.waitForTimeout(2000); }
console.log('prêt après', Math.round((Date.now() - t0) / 1000) + 7, 's'); await p.waitForTimeout(2500); await shot('7-ready');
console.log('bouton Studio direct (email déjà donné) :', !!(await p.$('a:has-text("Personnaliser mon site dans le Studio")')));
const slug = p.url().split('/site/')[1].split(/[/?]/)[0];
await p.goto(`https://${slug}.joow.fr/`, { waitUntil: 'networkidle', timeout: 60000 }); await shot('8-site');
const txt = (await p.textContent('body')).replace(/\s+/g, ' ');
console.log('avis Google mentionnés :', /avis Google/i.test(txt), '| note affichée :', /\d\.\d\/5/.test(txt));
// Studio : bannière « Rattacher ma fiche Google »
await p.goto(`https://app.joow.fr/sites/${slug}/editeur`, { waitUntil: 'load', timeout: 60000 }); await p.waitForTimeout(2500);
await p.click('nav.fixed button:has-text("Contenu")'); await p.waitForTimeout(800);
console.log('bannière rattacher Google :', !!(await p.$('text=Rattacher ma fiche Google'))); await shot('9-studio');
console.log('slug', slug);
await b.close();
