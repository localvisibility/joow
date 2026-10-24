// Refonte : URL collée dans le champ de recherche → analyse → site prêt (avant/après) → pages
import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true })).newPage();
p.on('pageerror', (e) => console.log('[pageerror]', String(e).slice(0, 200)));
const OUT = process.env.OUT; const shot = (n) => p.screenshot({ path: `${OUT}/r-${n}.png` });
await p.goto('https://app.joow.fr/?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
await p.click('#search-input'); await p.keyboard.type(process.env.URL);
await p.waitForSelector('text=Refaire le site', { timeout: 15000 }); await p.waitForTimeout(500); await shot('0-detect');
await Promise.all([p.waitForURL(/\/(site|creer)\//, { timeout: 60000 }).catch(() => p.waitForURL(/\/creer/, { timeout: 60000 })), p.click('button:has-text("Analyser et refaire mon site")')]);
console.log('→', p.url());
if (p.url().includes('/creer')) { console.log('site illisible → questionnaire'); await shot('1-fallback'); await b.close(); process.exit(0); }
await p.waitForTimeout(6000); await shot('1-wait');
const t0 = Date.now(); for (let i = 0; i < 100; i++) { if (await p.$('text=Votre site est prêt') || await p.$('text=La génération a échoué')) break; await p.waitForTimeout(2000); }
console.log('fin après', Math.round((Date.now() - t0) / 1000) + 6, 's', '| échec :', !!(await p.$('text=La génération a échoué')));
await p.waitForTimeout(2500); await shot('2-ready');
console.log('avant/après affiché :', !!(await p.$('text=Avant')));
const slug = p.url().split('/site/')[1].split(/[/?]/)[0];
const st = await (await fetch(`https://app.joow.fr/site/${slug}/status`, { headers: { Accept: 'application/json' } })).json();
console.log('progress.pages :', JSON.stringify(st.progress?.pages || null), '| source :', st.progress?.source, '| secteur :', st.progress?.sector, '| note :', st.progress?.rating, '| photos :', st.progress?.photos?.length);
await p.goto(`https://${slug}.joow.fr/`, { waitUntil: 'networkidle', timeout: 60000 }); await shot('3-site');
const links = await p.$$eval('nav a, header a', (as) => as.map((a) => a.getAttribute('href')).filter((h) => h && h.startsWith('/') && h.length > 1));
console.log('liens de pages :', [...new Set(links)].slice(0, 8).join(' '));
console.log('slug', slug);
await b.close();
