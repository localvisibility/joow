import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/* ── Filet de sécurité : les erreurs JavaScript sont journalisées côté serveur et affichées
      à l'utilisateur (bandeau + rechargement) au lieu de laisser une page noire. ── */
let reported = 0;
const reportError = (err, info = '') => {
    const message = String(err?.message || err || 'Erreur inconnue');
    console.error(err);
    if (reported++ < 5) {
        try {
            const body = JSON.stringify({ message, stack: String(err?.stack || '').slice(0, 4000), info, url: location.href });
            const token = document.querySelector('meta[name=csrf-token]')?.content || '';
            fetch('/js-error', { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' }, body }).catch(() => {});
        } catch {}
    }
    if (!document.getElementById('joow-js-error')) {
        const bar = document.createElement('div');
        bar.id = 'joow-js-error';
        bar.setAttribute('role', 'alert');
        bar.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:2147483647;display:flex;gap:14px;align-items:center;max-width:min(92vw,640px);padding:14px 18px;border-radius:14px;background:#1c1c2b;color:#fff;border:1px solid rgba(255,255,255,.12);box-shadow:0 20px 60px rgba(0,0,0,.6);font:500 14px/1.4 Inter,system-ui,sans-serif';
        bar.innerHTML = '<span>Oups, une erreur d\'affichage s\'est produite. Vos modifications enregistrées sont conservées.</span><button type="button" style="flex:none;border:0;border-radius:10px;padding:8px 14px;font-weight:700;color:#fff;background:linear-gradient(135deg,#6366f1,#a855f7);cursor:pointer">Recharger</button>';
        bar.querySelector('button').onclick = () => location.reload();
        document.body.appendChild(bar);
    }
};
window.addEventListener('error', (e) => reportError(e.error || e.message, 'window.error'));
window.addEventListener('unhandledrejection', (e) => reportError(e.reason, 'unhandledrejection'));

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.errorHandler = (err, instance, info) => reportError(err, 'vue:' + info);
        return app.use(plugin).use(ZiggyVue).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
