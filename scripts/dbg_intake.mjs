import { chromium, devices } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ ...devices['iPhone 13'], ignoreHTTPSErrors: true })).newPage();
p.on('pageerror', (e) => console.log('[pageerror]', String(e).slice(0, 300)));
p.on('console', (m) => { if (m.type() === 'error') console.log('[console]', m.text().slice(0, 200)); });
await p.goto('https://app.joow.fr/creer', { waitUntil: 'load', timeout: 60000 }); await p.waitForTimeout(1500);
console.log('fixed bars:', await p.$$eval('.fixed', (els) => els.map((e) => e.className.slice(0, 60) + ' | ' + e.innerText.replace(/\s+/g, ' ').slice(0, 60))));
console.log('inputs:', await p.$$eval('input', (els) => els.map((e) => e.placeholder)));
await p.screenshot({ path: process.env.OUT + '/dbg-creer.png' });
// Test d'upload direct → URL servie ?
await p.fill('input[placeholder="Ex : Plomberie Martin"]', 'Fleurs de Zorglub'); await p.fill('input[placeholder="Ex : Toulouse"]', 'Rodez'); await p.waitForTimeout(1200);
console.log('fixed bars after fill:', await p.$$eval('.fixed button', (els) => els.map((e) => e.innerText)));
await b.close();
