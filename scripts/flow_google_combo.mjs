// Parcours Google classique : on vérifie que le site indiqué sur la fiche est lu et combiné
import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], ignoreHTTPSErrors: true })).newPage();
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type(process.env.Q);
await p.waitForSelector('.bg-ink-800 button', { timeout: 30000 }); await p.waitForTimeout(500);
const opts = await p.$$('.bg-ink-800 button'); console.log('choix:', (await opts[0].innerText()).replace(/\n/g, ' / '));
await opts[0].click(); await p.waitForTimeout(400);
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('button[type=submit]:has-text("Générer")')]);
const slug = p.url().split('/site/')[1].split(/[/?]/)[0]; const t0 = Date.now();
for (let i = 0; i < 100; i++) { if (await p.$('text=Votre site est prêt') || await p.$('text=La génération a échoué')) break; await p.waitForTimeout(2000); }
console.log('fin après', Math.round((Date.now() - t0) / 1000), 's'); await p.waitForTimeout(2500);
await p.screenshot({ path: process.env.OUT + '/combo-ready.png' });
console.log('mention combo :', !!(await p.$('text=Fiche Google et site actuel combinés')));
const st = await (await fetch(`https://app.joow.fr/site/${slug}/status`, { headers: { Accept: 'application/json' } })).json();
console.log('status', st.status, '| website', st.progress.website, '| site_pages', st.progress.site_pages, '| images', st.progress.site_images, '| pages', JSON.stringify(st.progress.pages), '| note', st.progress.rating, '| timing', JSON.stringify(st.progress.timing));
console.log('slug', slug);
await b.close();
