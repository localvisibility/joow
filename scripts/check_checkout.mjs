import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await (await b.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true })).newPage();
await p.goto('https://app.joow.fr/site/bouillon-chartier-grands-boulevards-ym0i?v=' + Date.now(), { waitUntil: 'load', timeout: 60000 });
try { await p.waitForLoadState('networkidle', { timeout: 15000 }); } catch {}
await p.fill('#checkout-email', '');
await p.click('text=Essai gratuit 7 jours');
await p.waitForTimeout(900);
const el = await p.$('#checkout-email');
await el.scrollIntoViewIfNeeded();
const box = await el.boundingBox();
await p.screenshot({ path: process.env.OUT + '/checkout-error.png', clip: { x: 0, y: Math.max(0, box.y - 260), width: 1280, height: 640 } });
console.log('focused:', await p.evaluate(() => document.activeElement?.id), '| error:', await p.textContent('#checkout-email-error'));
await b.close();
