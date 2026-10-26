import { chromium } from 'playwright';
const SLUG = 'bouillon-chartier-grands-boulevards-ym0i';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true })).newPage();
p.on('pageerror', (e) => console.log('[pageerror] ' + String(e).slice(0, 300)));
await p.goto('https://app.joow.fr/login', { waitUntil: 'load', timeout: 60000 }); await p.click('text=Mot de passe'); await p.fill('#email', process.env.JOOW_EMAIL); await p.fill('#password', process.env.JOOW_PASS);
await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('button[type=submit]')]);
await p.goto(`https://app.joow.fr/sites/${SLUG}/editeur?v=${Date.now()}`, { waitUntil: 'load', timeout: 60000 });
await p.waitForSelector('iframe'); await p.waitForTimeout(5000);
await p.click('text=⚙️ Modules'); await p.waitForTimeout(600);
// activer l'assistant si besoin, puis renseigner une info pratique et une consigne
const botOn = await p.evaluate(() => { const l = [...document.querySelectorAll('label')].find((x) => x.textContent.includes('Assistant IA')); return l?.querySelector('button')?.className.includes('bg-brand-500'); });
console.log('bot actif avant :', botOn);
if (!botOn) { await p.click('label:has-text("Assistant IA") button'); await p.waitForTimeout(2500); }
await p.fill('textarea[placeholder^="Ex : parking"]', 'Parking public Drouot à 3 minutes à pied. Pas de réservation : service continu, on vient directement. Chiens acceptés.');
await p.keyboard.press('Tab'); await p.waitForTimeout(1500);
await p.fill('textarea[placeholder^="Ex : toujours"]', 'Toujours préciser qu\'il n\'y a pas de réservation possible et que l\'attente est rapide.');
await p.keyboard.press('Tab'); await p.waitForTimeout(1500);
await p.screenshot({ path: process.env.OUT + '/studio-modules.png' });
console.log('statut :', await p.textContent('header'));
await b.close();
