import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const ctx = await b.newContext({ ...devices['iPhone 13'], ignoreHTTPSErrors: true });
const p = await ctx.newPage();
p.on('pageerror', (e) => console.log('[pageerror]', String(e).slice(0, 200)));
const OUT = process.env.OUT;
const gen = async (q) => {
  await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
  await p.click('#search-input'); await p.keyboard.type(q);
  await p.waitForSelector('.bg-ink-800 button', { timeout: 30000 }); await p.waitForTimeout(500);
  const opts = await p.$$('.bg-ink-800 button'); console.log('choix:', (await opts[0].innerText()).replace(/\n/g, ' / '));
  await opts[0].click(); await p.waitForTimeout(400);
  await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('button[type=submit]:has-text("Générer")')]);
  return p.url().split('/site/')[1].split(/[/?]/)[0];
};
const waitReady = async () => { const t0 = Date.now(); for (let i = 0; i < 90; i++) { if (await p.$('text=Votre site est prêt')) break; await p.waitForTimeout(2000); } await p.waitForTimeout(2500); return Math.round((Date.now() - t0) / 1000); };

// ── Parcours A : pas d'email pendant l'attente → Studio verrouillé → email → Studio ──
const slugA = await gen(process.env.QA);
await p.waitForTimeout(6000); await p.screenshot({ path: `${OUT}/f-a1-wait.png` });
console.log('A capture visible pendant attente :', !!(await p.$('text=Recevez le lien de votre site par email')));
console.log('A prêt après', await waitReady(), 's');
await p.screenshot({ path: `${OUT}/f-a2-ready.png` });
console.log('A verrou Studio affiché :', !!(await p.$('text=Votre email pour ouvrir le Studio')), '| bouton direct absent :', !(await p.$('a:has-text("Personnaliser mon site dans le Studio")')));
// Accès direct au Studio sans email → renvoyé sur l'aperçu avec ?studio=1
await p.goto(`https://app.joow.fr/sites/${slugA}/editeur`, { waitUntil: 'load', timeout: 60000 });
console.log('A accès direct Studio →', p.url().replace('https://app.joow.fr', ''));
await p.waitForTimeout(800); await p.screenshot({ path: `${OUT}/f-a3-redirect.png` });
await p.fill('input[type=email]', process.env.MAIL_A);
await Promise.all([p.waitForURL(/\/editeur/, { timeout: 60000 }), p.click('button:has-text("Ouvrir le Studio")')]);
await p.waitForTimeout(2500); console.log('A Studio ouvert :', p.url().replace('https://app.joow.fr', ''), '| nom visible :', !!(await p.$('header')));
await p.screenshot({ path: `${OUT}/f-a4-studio.png` });

// ── Parcours B : email saisi pendant l'attente → Studio accessible directement ──
const slugB = await gen(process.env.QB);
await p.waitForSelector('text=Recevez le lien de votre site par email', { timeout: 20000 });
await p.fill('input[type=email]', process.env.MAIL_B); await p.click('button:has-text("envoyer le lien")');
await p.waitForSelector('text=nous envoyons votre lien privé', { timeout: 15000 }); await p.waitForTimeout(600);
await p.screenshot({ path: `${OUT}/f-b1-captured.png` }); console.log('B email enregistré pendant l\'attente ✓');
console.log('B prêt après', await waitReady(), 's');
await p.screenshot({ path: `${OUT}/f-b2-ready.png` });
console.log('B bouton Studio direct :', !!(await p.$('a:has-text("Personnaliser mon site dans le Studio")')), '| verrou absent :', !(await p.$('text=Votre email pour ouvrir le Studio')));
console.log('slugs', slugA, slugB);
await b.close();
