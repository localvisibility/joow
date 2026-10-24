// Questionnaire avec téléversement d'une photo : vérifie l'upload, la miniature, et la photo dans le site
import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], ignoreHTTPSErrors: true })).newPage();
const OUT = process.env.OUT; const nextBtn = '.fixed button:has-text("Continuer")';
await p.goto('https://app.joow.fr/creer', { waitUntil: 'load', timeout: 60000 }); await p.waitForTimeout(1200);
await p.fill('input[placeholder="Ex : Plomberie Martin"]', 'Fleurs de Zorglub'); await p.fill('input[placeholder="Ex : Toulouse"]', 'Rodez'); await p.waitForTimeout(1500);
try { await p.click(nextBtn, { timeout: 8000 }); } catch (e) { await p.screenshot({ path: `${OUT}/ip-fail.png` }); console.log('échec clic :', String(e).slice(0, 120), '| boutons :', await p.$$eval('button', (els) => els.map((x) => x.innerText).join(' / '))); process.exit(1); }
await p.fill('input[placeholder^="Tapez votre métier"]', 'fleuriste'); await p.waitForTimeout(300); await (await p.$$('main button.rounded-full'))[0].click(); await p.click(nextBtn);
await p.fill('input[type=tel]', '05 65 00 00 00'); await p.fill('input[type=email]', process.env.MAIL); await p.click(nextBtn);
await p.waitForTimeout(2500); await p.click(nextBtn);
await p.waitForSelector('input[type=file]'); await p.setInputFiles('input[type=file]', process.env.PHOTO);
await p.waitForSelector('img[src*="/media/intake-"]', { timeout: 30000 }); await p.waitForTimeout(500); await p.screenshot({ path: `${OUT}/ip-photos.png` });
const thumb = await p.$eval('img[src*="/media/intake-"]', (i) => i.src); const r = await fetch(thumb); console.log('miniature servie :', r.status, r.headers.get('content-type'));
await Promise.all([p.waitForURL(/\/site\//, { timeout: 60000 }), p.click('.fixed button:has-text("Générer mon site")')]);
const slug = p.url().split('/site/')[1].split(/[/?]/)[0]; console.log('slug', slug);
for (let i = 0; i < 90; i++) { if (await p.$('text=Votre site est prêt')) break; await p.waitForTimeout(2000); }
const st = await (await fetch(`https://app.joow.fr/site/${slug}/status`, { headers: { Accept: 'application/json' } })).json();
console.log('timing', JSON.stringify(st.progress.timing), '| photos', st.progress.photos?.length, '| notes', (st.progress.photo_notes || []).map((n) => n.category + ': ' + (n.description || '').slice(0, 50)).join(' / '));
const html = await (await fetch(`https://${slug}.joow.fr/`)).text();
console.log('photo téléversée utilisée dans le site :', (html.match(new RegExp(`/media/${slug}/`, 'g')) || []).length, 'fois');
await b.close();
