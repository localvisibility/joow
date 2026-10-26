/* Mesure publicitaire Meta : pixel chargé uniquement après consentement, événements dédoublonnés
   avec le serveur via event_id, bannière de consentement légère. */
const COOKIE = 'joow_consent';
export const consent = () => (document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)')) || [])[1] || '';
export const setConsent = (v) => { document.cookie = `${COOKIE}=${v}; Max-Age=${180 * 86400}; Path=/; SameSite=Lax; Secure`; };
export const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'e-' + Date.now() + '-' + Math.random().toString(16).slice(2));

let pixelId = null;
let loaded = false;
export const initPixel = (id) => {
    pixelId = id || pixelId;
    if (!pixelId || loaded || consent() !== 'all') return false;
    /* Snippet officiel du pixel Meta */
    !(function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', pixelId);
    window.fbq('track', 'PageView');
    loaded = true;
    return true;
};
const STANDARD = new Set(['PageView', 'ViewContent', 'Search', 'Lead', 'CompleteRegistration', 'InitiateCheckout', 'Purchase', 'Contact', 'Subscribe', 'StartTrial', 'AddToCart']);
export const track = (name, params = {}, eventId = null) => {
    if (!loaded || !window.fbq) return;
    const opts = eventId ? { eventID: eventId } : undefined;
    window.fbq(STANDARD.has(name) ? 'track' : 'trackCustom', name, params || {}, opts);
};
export const pageView = () => { if (loaded && window.fbq) window.fbq('track', 'PageView'); };
/* Événements remis par le serveur (prop partagée « track » ou réponse JSON) */
const seen = new Set();
export const fire = (events) => {
    (events || []).forEach((e) => { if (!e || !e.id || seen.has(e.id)) return; seen.add(e.id); track(e.name, e.params || {}, e.id); });
};
