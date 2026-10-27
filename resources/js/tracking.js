/* Mesure publicitaire Meta : pixel chargé uniquement après consentement, événements dédoublonnés
   avec le serveur via event_id, bannière de consentement légère. */
const COOKIE = 'joow_consent';
export const consent = () => (document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)')) || [])[1] || '';
export const setConsent = (v) => { document.cookie = `${COOKIE}=${v}; Max-Age=${180 * 86400}; Path=/; SameSite=Lax; Secure`; };
/* Consentement granulaire : 'all', 'essential', ou liste 'ads,analytics' */
export const allows = (kind) => { const v = consent(); return v === 'all' || v.split(',').includes(kind); };
export const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'e-' + Date.now() + '-' + Math.random().toString(16).slice(2));

let pixelId = null;
let loaded = false;
/* Google Tag Manager (GA4, Google Ads) : chargé après consentement, événements poussés dans dataLayer avec les noms GA4 */
let gtmId = null;
const GA4 = { ViewContent: 'view_item', Search: 'search', Lead: 'generate_lead', CompleteRegistration: 'sign_up', InitiateCheckout: 'begin_checkout', Purchase: 'purchase', StartGeneration: 'start_generation', SiteGenerated: 'site_generated', Contact: 'contact' };
export const initGtm = (id) => {
    gtmId = id || gtmId;
    if (!gtmId || !(allows('analytics') || allows('ads'))) return false;
    window.dataLayer = window.dataLayer || [];
    if (window.__gtmLoaded) return true;
    const g = (k) => (allows(k) ? 'granted' : 'denied');
    if (typeof window.gtag === 'function') window.gtag('consent', 'update', { ad_storage: g('ads'), ad_user_data: g('ads'), ad_personalization: g('ads'), analytics_storage: g('analytics') });
    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    const s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtm.js?id=' + gtmId; document.head.appendChild(s);
    window.__gtmLoaded = true;
    return true;
};
const toDataLayer = (name, params = {}, eventId = null) => {
    if (!window.__gtmLoaded || !GA4[name]) return;
    const p = { ...(params || {}) };
    const out = { event: GA4[name], event_id: eventId || undefined };
    if (p.search_string) out.search_term = p.search_string;
    if (p.value !== undefined) { out.value = Number(p.value); out.currency = p.currency || 'EUR'; }
    if (name === 'Purchase') out.transaction_id = p.order_id || p.transaction_id || eventId || undefined;
    if (p.content_name) out.item_name = p.content_name;
    if (p.content_category) out.item_category = p.content_category;
    if (p.source) out.source = p.source;
    if (p.sector) out.sector = p.sector;
    if (p.plan) out.plan = p.plan;
    window.dataLayer.push(out);
};
export const initPixel = (id) => {
    pixelId = id || pixelId;
    if (!pixelId || loaded || !allows('ads')) return false;
    /* Snippet officiel du pixel Meta */
    !(function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', pixelId);
    window.fbq('track', 'PageView');
    loaded = true;
    return true;
};
const STANDARD = new Set(['PageView', 'ViewContent', 'Search', 'Lead', 'CompleteRegistration', 'InitiateCheckout', 'Purchase', 'Contact', 'Subscribe', 'StartTrial', 'AddToCart']);
export const track = (name, params = {}, eventId = null) => {
    toDataLayer(name, params, eventId);
    if (!loaded || !window.fbq) return;
    const opts = eventId ? { eventID: eventId } : undefined;
    window.fbq(STANDARD.has(name) ? 'track' : 'trackCustom', name, params || {}, opts);
};
export const pageView = () => {
    if (loaded && window.fbq) window.fbq('track', 'PageView');
    if (window.__gtmLoaded) window.dataLayer.push({ event: 'virtual_page_view', page_location: location.href, page_title: document.title });
};
/* Événements remis par le serveur (prop partagée « track » ou réponse JSON) */
const seen = new Set();
export const fire = (events) => {
    (events || []).forEach((e) => { if (!e || !e.id || seen.has(e.id)) return; seen.add(e.id); track(e.name, e.params || {}, e.id); });
};
/* Enregistre un choix (tout, rien, ou une sélection) puis charge ce qui est autorisé */
export const choose = (kinds) => { const k = Array.isArray(kinds) ? kinds.filter(Boolean) : kinds === true ? ['ads', 'analytics'] : []; setConsent(k.length === 2 ? 'all' : k.length ? k.join(',') : 'essential'); initPixel(); initGtm(); };
