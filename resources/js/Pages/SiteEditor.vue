<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({ site: Object, plans: Object });
const slug = props.site.slug;
const pageProps = usePage().props;
const authUser = computed(() => pageProps.auth?.user || null);

/* ───────────── état ───────────── */
const st = ref(null);            // état complet du site (content, business, images, booking, pages, accent, font, theme, modules…)
const loading = ref(true);
const version = ref(Date.now());
const status = ref('idle');      // idle | saving | saved | queued | error
const tab = ref('ia');           // ia | contenu | pages | style
const panel = ref('hero');       // section (accueil) ou bloc ouvert dans le panneau
const device = ref('desktop');
// Mobile : l'aperçu occupe tout l'écran, le panneau s'ouvre en feuille depuis la barre du bas
const sheet = ref(false);
const openTab = (t) => { if (tab.value === t && sheet.value) { sheet.value = false; return; } tab.value = t; sheet.value = true; };
const frame = ref(null);
const undoStack = ref([]);
const redoStack = ref([]);
const picker = ref({ open: false, path: '', current: '', tab: 'upload', url: '', uploading: false });
const curPage = ref('');         // '' = accueil, sinon slug de page
const openBlock = ref(-1);
const modal = ref(null);         // 'publish' | 'claim' | 'newpage' | 'blocktype' | null
const paid = computed(() => st.value?.paid ?? props.site.paid);

/* ───────────── utilitaires ───────────── */
const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
const headers = () => ({ Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' });
const postJson = async (url, body) => {
    const isForm = body instanceof FormData;
    const r = await fetch(url, { method: 'POST', headers: isForm ? headers() : { ...headers(), 'Content-Type': 'application/json' }, body: isForm ? body : JSON.stringify(body) });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw d;
    return d;
};
const getPath = (o, p) => p.split('.').reduce((a, k) => (a == null ? undefined : a[k]), o);
const setPath = (o, p, v) => {
    const ks = p.split('.'); let cur = o;
    ks.slice(0, -1).forEach((k, i) => { const nk = ks[i + 1]; if (cur[k] == null || typeof cur[k] !== 'object') cur[k] = /^\d+$/.test(nk) ? [] : {}; cur = cur[k]; });
    cur[ks.at(-1)] = v;
};
const clone = (o) => JSON.parse(JSON.stringify(o));
const post = (m) => frame.value?.contentWindow?.postMessage({ joow: 1, ...m }, '*');
const reload = (v) => { version.value = v || Date.now(); };
const previewUrl = computed(() => `${route('sites.editor.preview', slug)}?v=${version.value}${curPage.value ? '&page=' + encodeURIComponent(curPage.value) : ''}`);
const liveUrl = computed(() => `${props.site.live_url}${curPage.value ? '/' + curPage.value + '/' : ''}?v=${version.value}`);

/* ───────────── sauvegarde (debounce + file d'attente) ───────────── */
let timer = null; let pending = {}; let needReload = false;
const queueSave = (patch, opts = {}) => {
    Object.assign(pending, patch); needReload = needReload || !!opts.reload;
    clearTimeout(timer); timer = setTimeout(flush, 450);
};
const flush = async () => {
    if (!Object.keys(pending).length) return;
    const patch = pending; pending = {}; const rl = needReload; needReload = false;
    status.value = 'saving';
    try {
        const d = await postJson(route('sites.editor.bulk', slug), { patch });
        status.value = d.queued ? 'queued' : 'saved';
        if (d.pages && st.value) { st.value.pages = d.pages; pagesSnap = clone(d.pages); if (curPage.value && !d.pages.some((p) => p.slug === curPage.value)) curPage.value = ''; }
        if (rl) reload(d.version);
    } catch (e) { status.value = 'error'; console.error(e); }
};

/** Modifie un champ (chemin pointé) : état local, undo, aperçu, sauvegarde. */
const edit = (path, value, { reload: rl = false, fromFrame = false, record = true } = {}) => {
    if (!st.value) return;
    const prev = getPath(st.value, path);
    if (JSON.stringify(prev) === JSON.stringify(value)) return;
    setPath(st.value, path, typeof value === 'object' && value !== null ? clone(value) : value);
    if (path === 'pages') pagesSnap = clone(value);
    if (record) { undoStack.value.push({ path, prev: clone(prev ?? null), next: clone(value ?? null), rl }); redoStack.value = []; }
    if (!fromFrame && !rl && typeof value === 'string') post({ type: 'setText', path, value });
    queueSave({ [path]: value }, { reload: rl });
};
const undo = () => {
    const u = undoStack.value.pop(); if (!u) return;
    // Modification faite par l'IA : on restaure l'instantané serveur (pas de « rétablir » possible)
    if (u.ai) { revert(u.ai); return; }
    redoStack.value.push(u); edit(u.path, u.prev, { reload: u.rl || typeof u.prev !== 'string', record: false });
};
const redo = () => { const u = redoStack.value.pop(); if (!u) return; undoStack.value.push(u); edit(u.path, u.next, { reload: u.rl || typeof u.next !== 'string', record: false }); };

/* ───────────── pont iframe ───────────── */
const onMessage = (e) => {
    const m = e.data || {}; if (!m.joow) return;
    if (m.type === 'ready') post({ type: 'setAccent', value: st.value?.accent });
    if (m.type === 'edit') edit(m.path, m.value, { fromFrame: true });
    if (m.type === 'image') openPicker(m.path, m.current);
    if (m.type === 'page') { curPage.value = m.slug || ''; }
    if (m.type === 'section') {
        if (m.id.startsWith('block-')) { openBlock.value = Number(m.id.slice(6)); tab.value = 'contenu'; }
        else if (m.id === 'phero') { openBlock.value = -2; tab.value = 'contenu'; }
        else { panel.value = m.id; tab.value = 'contenu'; }
    }
    if (m.type === 'focus') {
        if (m.path.startsWith('pages.')) { const parts = m.path.split('.'); openBlock.value = parts[2] === 'blocks' ? Number(parts[3]) : -2; tab.value = 'contenu'; return; }
        const sec = m.path.startsWith('booking') ? 'booking' : (m.path.startsWith('business') ? 'contact' : sectionOfPath(m.path)); if (sec) panel.value = sec;
    }
};
const sectionOfPath = (p) => {
    if (/hero_|tagline|cta_label|badges|stats/.test(p)) return 'hero';
    if (/process/.test(p)) return 'process'; if (/cta_band/.test(p)) return 'cta';
    if (/services/.test(p)) return 'services'; if (/about/.test(p)) return 'about';
    if (/faq/.test(p)) return 'faq'; if (/reviews/.test(p)) return 'reviews';
    if (/gallery/.test(p)) return 'gallery'; if (/contact|cta_text/.test(p)) return 'contact';
    return null;
};
const onKey = (e) => {
    if (!(e.ctrlKey || e.metaKey)) return;
    if (e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
    else if (e.key === 'y' || (e.key === 'z' && e.shiftKey)) { e.preventDefault(); redo(); }
    else if (e.key === 's') { e.preventDefault(); flush(); }
};

/* ───────────── chargement ───────────── */
const load = async () => {
    try {
        const r = await fetch(route('sites.editor.state', slug), { headers: headers() }); st.value = await r.json();
        messages.value = [welcome(), ...(st.value.chat || []).map((m) => ({ role: m.role, text: m.text, applied: m.applied || [] }))];
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};
const freeDomain = ref(null);
const loadDomain = async () => { try { const r = await fetch(`/api/site/${props.site.slug}/domain`, { headers: { Accept: 'application/json' } }); const d = await r.json(); freeDomain.value = d.available ? d.domain : null; } catch {} };
onMounted(() => { load(); loadDomain(); window.addEventListener('message', onMessage); window.addEventListener('keydown', onKey); });
onUnmounted(() => { window.removeEventListener('message', onMessage); window.removeEventListener('keydown', onKey); });
watch(curPage, () => { openBlock.value = -1; reload(); });

/* ───────────── sections (accueil) ───────────── */
const SECTIONS = [
    { id: 'hero', label: 'Accueil (haut de page)', fixed: true },
    { id: 'services', label: 'Services' },
    { id: 'process', label: 'Parcours (comment ça se passe)' },
    { id: 'gallery', label: 'Galerie' },
    { id: 'about', label: 'À propos' },
    { id: 'reviews', label: 'Avis Google' },
    { id: 'faq', label: 'FAQ' },
    { id: 'booking', label: 'Réservation / RDV / Devis' },
    { id: 'cta', label: 'Appel à l\'action' },
    { id: 'contact', label: 'Contact' },
];
const order = computed(() => {
    const o = st.value?.content?.sections?.order || [];
    const rest = SECTIONS.filter((s) => !s.fixed).map((s) => s.id).filter((id) => !o.includes(id));
    return [...o.filter((id) => SECTIONS.some((s) => s.id === id)), ...rest];
});
const orderedSections = computed(() => [SECTIONS[0], ...order.value.map((id) => SECTIONS.find((s) => s.id === id))]);
const isHidden = (id) => !!st.value?.content?.sections?.hidden?.[id];
const saveSections = async (newOrder, hidden) => {
    status.value = 'saving';
    try { const d = await postJson(route('sites.editor.sections', slug), { order: newOrder, hidden }); setPath(st.value, 'content.sections', { order: newOrder, hidden }); status.value = d.queued ? 'queued' : 'saved'; reload(d.version); }
    catch { status.value = 'error'; }
};
const toggleSection = (id) => { const hidden = { ...(st.value.content.sections?.hidden || {}) }; hidden[id] = !hidden[id]; saveSections(order.value, hidden); };
const move = (id, dir) => { const o = [...order.value]; const i = o.indexOf(id); const j = i + dir; if (i < 0 || j < 0 || j >= o.length) return; [o[i], o[j]] = [o[j], o[i]]; saveSections(o, st.value.content.sections?.hidden || {}); };
const goto = (id) => { panel.value = id; post({ type: 'scrollTo', id }); };

/* ───────────── listes (services, FAQ, badges) ───────────── */
const listSet = (path, arr) => edit(path, clone(arr), { reload: true });
const addService = () => listSet('content.services', [...(st.value.content.services || []), { name: 'Nouveau service', desc: 'Description du service.', price: 'Sur devis' }]);
const removeAt = (path, i) => { const arr = [...(getPath(st.value, path) || [])]; arr.splice(i, 1); listSet(path, arr); };
const addFaq = () => listSet('content.faq', [...(st.value.content.faq || []), { q: 'Nouvelle question ?', a: 'Votre réponse.' }]);
const addBadge = () => listSet('content.badges', [...(st.value.content.badges || []), 'Nouveau point fort'].slice(0, 4));

/* ───────────── pages & blocs ───────────── */
const pages = computed(() => st.value?.pages || []);
const pageIndex = computed(() => pages.value.findIndex((p) => p.slug === curPage.value));
const page = computed(() => pages.value[pageIndex.value] || null);
const blockDefs = computed(() => st.value?.blocks || []);
const blockLabel = (type) => blockDefs.value.find((b) => b.type === type)?.label || type;
let pagesTimer = null; let pagesSnap = null; // dernière version enregistrée (les blocs sont mutés en place)
const commitPages = (immediate = false) => {
    clearTimeout(pagesTimer);
    const run = () => {
        const next = clone(st.value.pages); const prev = pagesSnap ?? [];
        if (JSON.stringify(prev) === JSON.stringify(next)) return;
        undoStack.value.push({ path: 'pages', prev, next, rl: true }); redoStack.value = [];
        pagesSnap = next; queueSave({ pages: next }, { reload: true });
    };
    immediate ? run() : (pagesTimer = setTimeout(run, 700));
};
watch(() => st.value?.pages, (v) => { if (v && pagesSnap === null) pagesSnap = clone(v); });
const newPage = ref({ title: '', brief: '', mode: 'ia' });
const createPage = async () => {
    const title = newPage.value.title.trim(); if (!title) return;
    modal.value = null;
    if (newPage.value.mode === 'ia') {
        tab.value = 'ia';
        await send(`Crée une page « ${title} »${newPage.value.brief.trim() ? ' : ' + newPage.value.brief.trim() : ''}. Rédige un contenu complet et pertinent pour cet établissement, avec 4 à 6 blocs variés et un appel à l'action à la fin.`);
        const p = pages.value.find((x) => x.title.toLowerCase() === title.toLowerCase()) || pages.value.at(-1);
        if (p) curPage.value = p.slug;
    } else {
        const slugified = title.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        st.value.pages.push({ slug: slugified || 'page', title, nav: true, seo: '', hero: { tag: '', title, subtitle: '', image: null }, blocks: [{ type: 'text', title: 'Un titre clair', body: 'Décrivez ici votre activité, votre approche ou votre histoire.\n\nUn second paragraphe pour rassurer et donner envie.', image: null, image_side: 'right' }] });
        commitPages(true);
        await flush();
        curPage.value = st.value.pages.at(-1)?.slug || '';
        tab.value = 'contenu';
    }
    newPage.value = { title: '', brief: '', mode: 'ia' };
};
const removePage = (i) => { if (!confirm(`Supprimer la page « ${st.value.pages[i].title} » ?`)) return; const wasCur = st.value.pages[i].slug === curPage.value; st.value.pages.splice(i, 1); if (wasCur) curPage.value = ''; commitPages(true); };
const movePage = (i, d) => { const p = st.value.pages; const j = i + d; if (j < 0 || j >= p.length) return; [p[i], p[j]] = [p[j], p[i]]; commitPages(true); };
const MODULE_BLOCKS = [['menu', 'Carte / menu'], ['booking', 'Réservation / demande'], ['rooms', 'Chambres & séjour'], ['reviews', 'Avis Google'], ['gallery', 'Galerie photos']];
const BLOCK_DEFAULTS = {
    text: { title: 'Un titre clair', body: 'Votre texte ici.\n\nUn second paragraphe.', image: null, image_side: 'right' },
    features: { title: 'Nos points forts', intro: '', items: [{ icon: 'fa-circle-check', title: 'Point fort 1', desc: 'Une phrase concrète.' }, { icon: 'fa-circle-check', title: 'Point fort 2', desc: 'Une phrase concrète.' }, { icon: 'fa-circle-check', title: 'Point fort 3', desc: 'Une phrase concrète.' }] },
    steps: { title: 'Comment ça se passe', items: [{ title: 'Étape 1', desc: '' }, { title: 'Étape 2', desc: '' }, { title: 'Étape 3', desc: '' }] },
    gallery: { title: 'En images', images: [] },
    faq: { title: 'Questions fréquentes', items: [{ q: 'Une question fréquente ?', a: 'Votre réponse.' }] },
    pricing: { title: 'Nos tarifs', intro: '', items: [{ name: 'Offre', price: 'Sur devis', desc: '', features: ['Inclus 1', 'Inclus 2'] }] },
    team: { title: 'Notre équipe', items: [{ name: 'Prénom Nom', role: 'Rôle', bio: '', image: null }] },
    testimonials: { title: 'Ils nous font confiance', items: [{ text: 'Un témoignage client.', author: 'Prénom N.', role: 'Client' }] },
    stats: { title: '', items: [{ v: '+10 ans', l: 'd\'expérience' }, { v: '4.8/5', l: 'Note Google' }, { v: '100%', l: 'Clients satisfaits' }] },
    cta: { title: 'Parlons de votre projet', text: 'Un premier échange gratuit, sans engagement.', button: '' },
    contact: { title: 'Nous trouver', text: '' },
    video: { title: 'En vidéo', url: null },
    module: { title: '', key: 'booking' },
};
const addBlock = (type) => { if (!page.value) return; page.value.blocks.push({ type, ...clone(BLOCK_DEFAULTS[type] || {}) }); openBlock.value = page.value.blocks.length - 1; modal.value = null; commitPages(true); };
const removeBlock = (j) => { page.value.blocks.splice(j, 1); openBlock.value = -1; commitPages(true); };
const moveBlock = (j, d) => { const a = page.value.blocks; const k = j + d; if (k < 0 || k >= a.length) return; [a[j], a[k]] = [a[k], a[j]]; openBlock.value = k; commitPages(true); };
const addItem = (blk) => { const t = blk.type; const tpl = { features: { icon: 'fa-circle-check', title: 'Nouveau', desc: '' }, steps: { title: 'Étape', desc: '' }, faq: { q: 'Question ?', a: 'Réponse.' }, pricing: { name: 'Offre', price: '', desc: '', features: [] }, team: { name: 'Prénom Nom', role: '', bio: '', image: null }, testimonials: { text: '', author: '', role: '' }, stats: { v: '', l: '' } }[t]; if (tpl) { (blk.items ||= []).push(clone(tpl)); commitPages(true); } };
const removeItem = (blk, k) => { blk.items.splice(k, 1); commitPages(true); };
const blockPath = (j) => `pages.${pageIndex.value}.blocks.${j}`;
const scrollBlock = (j) => { openBlock.value = j; post({ type: 'scrollTo', id: j === -2 ? 'phero' : 'block-' + j }); };

/* ───────────── images ───────────── */
const openPicker = (path, current = '') => { picker.value = { open: true, path, current, tab: 'upload', url: '', uploading: false }; openBank(); };
/* Banque d'images : recherche intelligente (français → mots-clés, métier, emplacement) */
const bank = ref({ q: '', results: [], suggestions: [], loading: false, searched: false, using: null });
const searchBank = async (initial = false) => {
    bank.value.loading = true; bank.value.searched = true;
    try {
        const qs = new URLSearchParams({ q: initial ? '' : bank.value.q, path: picker.value.path || '' });
        const r = await fetch(route('sites.editor.bank', slug) + '?' + qs, { headers: headers() });
        const d = await r.json();
        bank.value.results = d.results || []; bank.value.suggestions = d.suggestions || [];
        if (initial && d.query_fr && !bank.value.q) bank.value.q = d.query_fr;
    } catch { bank.value.results = []; } finally { bank.value.loading = false; }
};
const openBank = () => { picker.value.tab = 'bank'; if (!bank.value.searched || bank.value.path !== picker.value.path) { bank.value = { q: '', results: [], suggestions: [], loading: false, searched: false, using: null, path: picker.value.path }; searchBank(true); } };
const useBankPhoto = async (r, i) => {
    bank.value.using = i;
    try { const d = await postJson(route('sites.editor.bank.use', slug), { url: r.url, download: r.download || null, credit: r.credit || null }); applyImage(d.url); }
    catch (e) { alert(e?.message || 'Impossible d\'ajouter cette photo.'); }
    finally { bank.value.using = null; }
};
const applyImage = (url) => {
    const p = picker.value.path;
    if (p.startsWith('images.gallery.')) {
        const i = Number(p.split('.').pop()); const g = [...(st.value.images?.gallery || galleryDefault())]; g[i] = url;
        edit('images.gallery', g, { reload: true });
    } else if (p.startsWith('pages.')) {
        setPath(st.value, p, url); commitPages(true);
    } else { post({ type: 'setImage', path: p, value: url }); edit(p, url, { reload: true }); }
    picker.value.open = false;
};
const galleryDefault = () => (st.value.photos || []).slice(2, 8);
const galleryList = computed(() => st.value?.images?.gallery || galleryDefault());
const removeGallery = (i) => { const g = [...galleryList.value]; g.splice(i, 1); edit('images.gallery', g, { reload: true }); };
const upload = async (ev) => {
    const f = ev.target.files?.[0]; if (!f) return;
    picker.value.uploading = true;
    try { const fd = new FormData(); fd.append('file', f); const d = await postJson(route('sites.editor.image', slug), fd); applyImage(d.url); }
    catch (e) { alert(e?.message || 'Upload impossible (8 Mo max, jpg/png/webp).'); }
    finally { picker.value.uploading = false; ev.target.value = ''; }
};

/* ───────────── en-tête & logo ───────────── */
const brand = computed(() => st.value?.brand || {});
const logoBusy = ref(false);
const logoPanel = ref(false);
const logoStyle = ref('épuré');
const logoHint = ref('');
const logoProps = ref([]);
const LOGO_STYLES = ['épuré', 'élégant', 'moderne', 'artisanal', 'chaleureux', 'premium', 'ludique'];
const saveBrand = async (payload) => {
    status.value = 'saving';
    try {
        let body = payload;
        if (payload instanceof File) { body = new FormData(); body.append('file', payload); }
        const d = await postJson(route('sites.editor.brand', slug), body);
        if (st.value) st.value.brand = { ...(st.value.brand || {}), ...d.brand };
        status.value = d.queued ? 'queued' : 'saved'; reload(d.version);
    } catch (e) { status.value = 'error'; alert(e?.message || 'Modification impossible.'); }
};
const uploadLogo = (ev) => { const f = ev.target.files?.[0]; if (f) saveBrand(f); ev.target.value = ''; };
const useLogo = (url) => { saveBrand({ logo_url: url }); logoPanel.value = false; };
const genLogos = async () => {
    if (logoBusy.value) return; logoBusy.value = true; logoProps.value = [];
    try { const d = await postJson(route('sites.editor.logo', slug), { style: logoStyle.value, hint: logoHint.value }); logoProps.value = d.logos || []; if (d.credits && st.value) st.value.credits = d.credits; }
    catch (e) { alert(e?.message || 'Génération impossible pour le moment.'); }
    finally { logoBusy.value = false; }
};

/* ───────────── style ───────────── */
const palette = ['#4f46e5', '#e11d48', '#0d9488', '#db2777', '#ea580c', '#2563eb', '#7c3aed', '#059669', '#0f172a', '#b45309'];
const fonts = ['Space Grotesk', 'Sora', 'Manrope', 'Outfit', 'Bricolage Grotesque', 'Poppins', 'Montserrat', 'Playfair Display', 'DM Serif Display', 'Cormorant Garamond', 'Fraunces', 'Lora', 'Libre Baskerville'];
const saveStyle = async (payload, rl = true) => {
    status.value = 'saving';
    try { const d = await postJson(route('sites.editor.style', slug), payload); status.value = d.queued ? 'queued' : 'saved'; if (rl) reload(d.version); } catch { status.value = 'error'; }
};
const setAccent = (c) => { if (!/^#[0-9a-fA-F]{6}$/.test(c)) return; st.value.accent = c; post({ type: 'setAccent', value: c }); saveStyle({ accent: c }, false); };
const setFont = (f) => { st.value.font = f; saveStyle({ font: f }); };
const setTheme = (t) => { st.value.theme = t; saveStyle({ theme: t }); };
const setHeroStyle = (h) => { st.value.hero_style = h; saveStyle({ hero_style: h }); };
const QUICK_MODULES = [['booking', 'Formulaire de demande'], ['whatsapp', 'Bulle WhatsApp'], ['bot', 'Assistant IA 24h/24'], ['reviews', 'Avis Google'], ['legal', 'Mentions légales RGPD']];
// Un module peut être un booléen (historique) ou un objet { enabled, …réglages }
const moduleOn = (key) => { const v = st.value?.modules?.[key]; if (key === 'reviews') return v !== false && !(v && typeof v === 'object' && v.enabled === false); return typeof v === 'object' && v !== null ? !!v.enabled : !!v; };
const toggleModule = async (key = 'booking') => {
    const was = moduleOn(key); const enabled = !was; const prev = st.value.modules[key];
    st.value.modules[key] = (prev && typeof prev === 'object') ? { ...prev, enabled } : enabled; status.value = 'saving';
    try { const d = await postJson(route('sites.editor.module', slug), { key, enabled }); st.value.modules = d.modules; if (d.module_config) st.value.module_config = d.module_config; status.value = d.queued ? 'queued' : 'saved'; reload(d.version); }
    catch { st.value.modules[key] = prev; status.value = 'error'; }
};
// Réglages de l'assistant IA (fusionnés avec les défauts côté serveur)
const bot = computed(() => st.value?.module_config?.bot || {});
const setBot = async (field, value) => {
    status.value = 'saving';
    try { const d = await postJson(route('sites.editor.module', slug), { key: 'bot', enabled: true, config: { [field]: value } }); st.value.modules = d.modules; st.value.module_config = d.module_config; status.value = d.queued ? 'queued' : 'saved'; if (['name', 'welcome'].includes(field)) reload(d.version); }
    catch { status.value = 'error'; }
};
const setHours = (txt) => edit('business.opening_hours', txt.split('\n').map((l) => l.trim()).filter(Boolean), { reload: true });

/* ───────────── constructeur de formulaire ───────────── */
const FIELD_TYPES = [['cards', 'Choix illustré (cartes)'], ['chips', 'Multi-choix (pastilles)'], ['toggle', 'Bascule (un seul choix)'], ['select', 'Liste déroulante'], ['text', 'Texte court'], ['textarea', 'Texte long'], ['date', 'Date'], ['time', 'Heure'], ['number', 'Nombre'], ['phone', 'Téléphone'], ['email', 'Email']];
const slugify = (s) => (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'champ';
const openStep = ref(0);
let formTimer = null;
const commitForm = () => { clearTimeout(formTimer); formTimer = setTimeout(() => edit('form', clone(st.value.form), { reload: true }), 700); };
const fSteps = computed(() => st.value?.form?.steps || []);
const addStep = () => { st.value.form.steps.splice(Math.max(0, fSteps.value.length - 1), 0, { title: 'Nouvelle étape', icon: 'fa-list-check', fields: [] }); openStep.value = Math.max(0, fSteps.value.length - 2); commitForm(); };
const removeStep = (i) => { if (fSteps.value[i]?.contact) return; st.value.form.steps.splice(i, 1); commitForm(); };
const moveStep = (i, d) => { const s = st.value.form.steps; const j = i + d; if (j < 0 || j >= s.length || s[i].contact || s[j].contact) return; [s[i], s[j]] = [s[j], s[i]]; openStep.value = j; commitForm(); };
const addField = (i, type = 'text') => { const f = { type, key: 'champ_' + Date.now().toString(36), label: 'Nouveau champ', required: false }; if (type === 'cards') f.options = [{ label: 'Option 1', desc: '', icon: 'fa-circle-check' }, { label: 'Option 2', desc: '', icon: 'fa-circle-check' }]; else if (['chips', 'toggle', 'select'].includes(type)) f.options = ['Option 1', 'Option 2']; st.value.form.steps[i].fields.push(f); commitForm(); };
const removeField = (i, j) => { st.value.form.steps[i].fields.splice(j, 1); commitForm(); };
const moveField = (i, j, d) => { const a = st.value.form.steps[i].fields; const k = j + d; if (k < 0 || k >= a.length) return; [a[j], a[k]] = [a[k], a[j]]; commitForm(); };
const setFieldLabel = (f, v) => { f.label = v; if (!['name', 'phone', 'email', 'message'].includes(f.key)) f.key = slugify(v); commitForm(); };
const setFieldType = (f, t) => { f.type = t; if (t === 'cards') f.options = (f.options || []).map((o) => typeof o === 'string' ? { label: o, desc: '', icon: 'fa-circle-check' } : o); else if (['chips', 'toggle', 'select'].includes(t)) f.options = (f.options || []).map((o) => typeof o === 'string' ? o : o.label); else delete f.options; if (!f.options?.length && ['cards', 'chips', 'toggle', 'select'].includes(t)) f.options = t === 'cards' ? [{ label: 'Option 1', desc: '', icon: 'fa-circle-check' }] : ['Option 1']; commitForm(); };
const optionsText = (f) => (f.options || []).map((o) => typeof o === 'string' ? o : [o.label, o.desc || '', o.icon || ''].join(' | ')).join('\n');
const setOptions = (f, txt) => { const lines = txt.split('\n').map((l) => l.trim()).filter(Boolean); f.options = f.type === 'cards' ? lines.map((l) => { const [label, desc, icon] = l.split('|').map((x) => x.trim()); return { label, desc: desc || '', icon: icon || 'fa-circle-check' }; }) : lines; commitForm(); };
const restoreForm = () => { if (!confirm('Restaurer le modèle du métier ? Vos modifications du formulaire seront perdues.')) return; st.value.form = clone(st.value.form_default); openStep.value = 0; commitForm(); };

/* ───────────── publication / mise en ligne ───────────── */
const publishing = ref(false);
const publish = async () => {
    publishing.value = true; await flush();
    try { const d = await postJson(route('sites.editor.publish', slug), {}); status.value = d.queued ? 'queued' : 'saved'; }
    catch { status.value = 'error'; } finally { publishing.value = false; }
};
const checkout = ref({ email: props.site.owner_email || authUser.value?.email || '', plan: 'pro' });
const startCheckout = (plan) => {
    if (!checkout.value.email) return alert('Indiquez votre email pour recevoir vos accès.');
    const f = document.createElement('form'); f.method = 'POST'; f.action = route('public.checkout', slug);
    const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
    add('_token', csrf()); add('email', checkout.value.email); add('plan', plan);
    document.body.appendChild(f); f.submit();
};
// Essai Pro en cours : souscrire sans attendre (1er mois prélevé, crédits Pro débloqués)
const trial = computed(() => st.value?.subscription?.trialing ? st.value.subscription : null);
const subscribing = ref(false);
const subscribeNow = async () => {
    if (!trial.value || subscribing.value) return;
    if (!confirm(`Souscrire maintenant : le premier mois (${trial.value.price} € HT) est prélevé aujourd'hui sur la carte enregistrée, et vos ${trial.value.quota} crédits IA mensuels sont débloqués immédiatement. Continuer ?`)) return;
    subscribing.value = true;
    try {
        const d = await postJson(route('sites.editor.subscribe', slug), {});
        if (d.state) { const chat = st.value?.chat; st.value = d.state; if (!d.state.chat && chat) st.value.chat = chat; }
        if (d.invoice_paid) { messages.value.push({ role: 'ai', text: `Abonnement Pro activé ✅ Vous disposez maintenant de ${st.value?.credits?.balance ?? ''} crédits IA, renouvelés chaque mois.`, applied: [] }); scrollChat(); }
        else if (d.pay_url) { alert('Votre banque demande une confirmation : finalisez le paiement dans l\'onglet qui s\'ouvre, vos crédits seront crédités juste après.'); window.open(d.pay_url, '_blank'); }
    } catch (e) { alert(e?.error || e?.message || 'Impossible de démarrer l\'abonnement maintenant.'); }
    finally { subscribing.value = false; }
};
const buyCredits = (pk) => {
    const f = document.createElement('form'); f.method = 'POST'; f.action = route('credits.checkout', slug);
    const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
    add('_token', csrf()); add('pack', pk.key);
    document.body.appendChild(f); f.submit();
};
const claim = ref({ email: props.site.owner_email || '', done: false, link: props.site.edit_link, sending: false });

/* ── Rattacher une fiche Google (sites créés sans : questionnaire, refonte) ── */
const hasPlace = ref(!!props.site.has_place);
const gsearch = ref({ q: props.site.name || '', results: [], searching: false, attaching: false, done: null, error: '' });
let gTimer = null;
const searchGoogle = () => {
    clearTimeout(gTimer);
    const q = gsearch.value.q.trim(); if (q.length < 3) { gsearch.value.results = []; return; }
    gsearch.value.searching = true;
    gTimer = setTimeout(async () => {
        try { const r = await fetch(route('public.search', { q }), { headers: { Accept: 'application/json' } }); gsearch.value.results = (await r.json()).results || []; }
        catch (e) { gsearch.value.results = []; } finally { gsearch.value.searching = false; }
    }, 350);
};
const attachGoogle = async (r) => {
    gsearch.value.attaching = true; gsearch.value.error = '';
    try {
        const d = await postJson(route('sites.editor.google', slug), { place_id: r.place_id });
        if (d.state) st.value = d.state;
        hasPlace.value = true; gsearch.value.done = d.google; reload(d.version);
    } catch (e) { gsearch.value.error = e?.message || 'Rattachement impossible.'; }
    finally { gsearch.value.attaching = false; }
};
const saveClaim = async () => {
    if (!claim.value.email) return;
    claim.value.sending = true;
    try { const d = await postJson(route('sites.editor.claim', slug), { email: claim.value.email }); claim.value.done = true; claim.value.link = d.edit_link; checkout.value.email ||= claim.value.email; }
    catch (e) { alert(e?.message || 'Email invalide.'); } finally { claim.value.sending = false; }
};
const copy = async (txt) => { try { await navigator.clipboard.writeText(txt); alert('Lien copié !'); } catch { prompt('Copiez ce lien :', txt); } };

/* ───────────── agent IA ───────────── */
const welcome = () => ({ role: 'ai', text: `Bonjour 👋 Je suis votre assistant Studio. Dites-moi ce que vous voulez : changer un texte, ajouter une page « Nos réalisations », passer en thème sombre, activer WhatsApp… J'applique directement et vous pouvez annuler d'un clic.`, applied: [] });
const messages = ref([welcome()]);
const input = ref(''); const sending = ref(false); const chatBox = ref(null); const workLabel = ref('');
const WORK = ['Je lis votre site…', 'Je réfléchis à la meilleure façon de faire…', 'Je rédige…', 'J\'applique les modifications…', 'Je mets l\'aperçu à jour…'];
const suggestions = computed(() => curPage.value
    ? [`Enrichis la page « ${page.value?.title} » avec une FAQ`, 'Ajoute une galerie photos à cette page', 'Rends le texte de cette page plus court et percutant', 'Ajoute un appel à l\'action en bas de page']
    : ['Crée une page « Nos réalisations » avec une galerie et des témoignages', 'Ajoute une page « Tarifs »', 'Rends le ton plus chaleureux et rassurant', 'Passe le site en thème sombre', 'Active la bulle WhatsApp et l\'assistant IA']);
const scrollChat = () => nextTick(() => { if (chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight; });
const send = async (text) => {
    const msg = (text ?? input.value).trim(); if (!msg || sending.value) return;
    input.value = ''; messages.value.push({ role: 'user', text: msg }); messages.value.push({ role: 'ai', text: '', pending: true });
    scrollChat(); sending.value = true; let wi = 0; workLabel.value = WORK[0];
    const wt = setInterval(() => { wi = Math.min(wi + 1, WORK.length - 1); workLabel.value = WORK[wi]; }, 3500);
    try {
        await flush();
        const d = await postJson(route('sites.editor.chat', slug), { message: msg });
        messages.value.pop(); const aiMsg = { role: 'ai', text: d.reply || 'C\'est fait ✅', applied: d.applied || [], undo_id: d.undo_id, cost: d.cost || 0, logos: d.logos || [] }; messages.value.push(aiMsg);
        // Les changements de l'IA deviennent annulables depuis les flèches du haut (Ctrl+Z)
        if (d.undo_id && (d.applied || []).length) { undoStack.value.push({ ai: aiMsg }); redoStack.value = []; }
        if (d.state) { const chat = st.value?.chat; st.value = d.state; if (!d.state.chat && chat) st.value.chat = chat; pagesSnap = clone(d.state.pages || []); }
        if (curPage.value && !pages.value.some((p) => p.slug === curPage.value)) curPage.value = '';
        status.value = d.queued ? 'queued' : 'saved'; reload(d.version);
    } catch (e) {
        messages.value.pop(); messages.value.push({ role: 'ai', text: e?.reply || e?.message || 'Je n\'ai pas pu appliquer cette demande. Reformulez ?', applied: [] });
        if (e?.error === 'no_credits' && st.value) { st.value.credits = e.credits || { ...st.value.credits, balance: 0 }; }
    }
    finally { clearInterval(wt); sending.value = false; scrollChat(); }
};
const revert = async (m) => {
    if (!m.undo_id || m.reverted) return;
    try {
        const d = await postJson(route('sites.editor.revert', slug), { id: m.undo_id }); m.reverted = true;
        if (d.state) { st.value = d.state; pagesSnap = clone(d.state.pages || []); }
        // L'instantané restauré rend caduques les étapes d'annulation plus récentes
        undoStack.value = undoStack.value.filter((u) => u.ai !== m); redoStack.value = [];
        messages.value.push({ role: 'ai', text: 'Modifications annulées, retour à l\'état précédent.', applied: [] }); reload(d.version); scrollChat();
    }
    catch (e) { alert(e?.message || 'Impossible d\'annuler (instantané expiré).'); }
};

const regenerate = () => router.post(route('sites.editor.regenerate', slug), {}, { preserveScroll: true });
const statusLabel = computed(() => ({ idle: '', saving: 'Enregistrement…', saved: 'Enregistré ✓', queued: 'Enregistré · publication en cours…', error: 'Erreur d\'enregistrement' }[status.value]));
</script>

<template>
    <Head :title="`Studio — ${site.name}`" />
    <div class="flex h-[100dvh] flex-col overflow-hidden bg-ink-950 text-slate-200">
        <!-- ═══ BARRE SUPÉRIEURE ═══ -->
        <header class="flex h-14 shrink-0 items-center gap-3 border-b border-white/[0.06] bg-ink-900/80 px-4 backdrop-blur">
            <Link :href="authUser ? route('dashboard') : route('public.site', site.slug)" class="group flex shrink-0 items-center gap-2 rounded-xl py-1 pr-2 transition hover:bg-white/[0.06]" :title="authUser ? 'Retour à mes sites' : 'Retour à votre site'">
                <BrandLogo variant="mark" class="h-7 w-7" />
                <span class="hidden text-xs font-semibold text-slate-400 group-hover:text-white sm:inline">← {{ authUser ? 'Mes sites' : 'Mon site' }}</span>
            </Link>
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-display text-sm font-bold text-white max-w-[9rem] sm:max-w-none">{{ site.name }}</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider" :class="paid ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'">{{ paid ? (trial ? 'En ligne · essai' : 'En ligne') : 'Essai gratuit' }}</span>
            </div>

            <!-- Onglets de pages -->
            <nav class="ml-2 hidden min-w-0 flex-1 items-center gap-1 overflow-x-auto md:flex">
                <button @click="curPage=''" class="ptab" :class="curPage==='' && 'on'">Accueil</button>
                <button v-for="p in pages" :key="p.slug" @click="curPage=p.slug" class="ptab" :class="curPage===p.slug && 'on'"><span v-if="!p.nav" class="mr-1 opacity-50" title="Masquée du menu">◌</span>{{ p.title }}</button>
                <button @click="modal='newpage'" class="ptab text-brand-300" title="Ajouter une page">+ Page</button>
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                <span class="hidden text-xs lg:inline" :class="status==='error' ? 'text-rose-400' : 'text-slate-500'">{{ statusLabel }}</span>
                <div class="glass hidden rounded-xl p-0.5 sm:flex">
                    <button @click="undo" :disabled="!undoStack.length" class="rounded-lg px-2 py-1.5 text-sm text-slate-300 disabled:opacity-30" title="Annuler (Ctrl+Z)">↶</button>
                    <button @click="redo" :disabled="!redoStack.length" class="rounded-lg px-2 py-1.5 text-sm text-slate-300 disabled:opacity-30" title="Rétablir (Ctrl+Y)">↷</button>
                </div>
                <div class="glass hidden rounded-xl p-0.5 sm:flex">
                    <button @click="device='desktop'" :class="device==='desktop' ? 'bg-white/10 text-white' : 'text-slate-400'" class="rounded-lg px-2.5 py-1.5 text-sm" title="Ordinateur">🖥️</button>
                    <button @click="device='mobile'" :class="device==='mobile' ? 'bg-white/10 text-white' : 'text-slate-400'" class="rounded-lg px-2.5 py-1.5 text-sm" title="Mobile">📱</button>
                </div>
                <button v-if="st?.credits" @click="modal='credits'" class="hidden items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold transition md:inline-flex" :class="st.credits.balance > 0 ? 'border-white/10 text-slate-200 hover:border-white/25' : 'border-amber-400/40 bg-amber-500/10 text-amber-200'" title="Crédits IA disponibles">✦ {{ st.credits.balance }} <span class="hidden xl:inline">crédits IA</span></button>
                <a :href="liveUrl" target="_blank" rel="noopener" class="hidden rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25 md:inline">Voir ↗</a>
                <button v-if="!authUser" @click="modal='claim'" class="hidden rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25 lg:inline">Retrouver mon site plus tard</button>
                <button v-if="paid" @click="publish" :disabled="publishing" class="btn-brand !px-3 !py-2 text-sm sm:!px-4">{{ publishing ? 'Publication…' : 'Publier' }}</button>
                <button v-else @click="modal='publish'" class="btn-brand !px-3 !py-2 text-sm sm:!px-4"><span class="sm:hidden">🚀 En ligne</span><span class="hidden sm:inline">🚀 Mettre en ligne</span></button>
            </div>
        </header>

        <!-- Bandeau invité -->
        <div v-if="!authUser && !paid" class="hidden flex-wrap items-center justify-center gap-x-4 gap-y-1 border-b border-brand-500/20 bg-brand-500/10 px-4 py-1.5 text-center text-xs text-brand-100 lg:flex">
            <span>Votre site est modifiable gratuitement, aussi longtemps que vous voulez.</span>
            <button @click="modal='claim'" class="font-semibold underline decoration-brand-400/60 underline-offset-2 hover:text-white">Recevoir mon lien d'édition</button>
            <span class="hidden sm:inline">·</span>
            <span v-if="freeDomain" class="mr-2 text-emerald-300">✓ {{ freeDomain }} est disponible ·</span><button @click="modal='publish'" class="font-semibold underline decoration-brand-400/60 underline-offset-2 hover:text-white">Mettre en ligne quand il est parfait →</button>
        </div>

        <div v-if="!site.editable" class="m-4 rounded-2xl border border-amber-400/20 bg-amber-500/[0.06] p-5">
            <p class="font-semibold text-white">Édition à activer</p>
            <p class="mt-1 text-sm text-slate-400">Ce site a été importé sans contenu structuré. Régénérez-le depuis sa fiche Google pour débloquer le Studio.</p>
            <button v-if="hasPlace" @click="regenerate" class="btn-brand mt-4 text-sm">Régénérer avec l'IA</button>
        </div>

        <!-- ═══ CORPS ═══ -->
        <div class="relative grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[420px,1fr]">
            <!-- ─── PANNEAU (colonne sur desktop, feuille coulissante sur mobile) ─── -->
            <div v-if="sheet" class="absolute inset-0 z-30 bg-black/50 backdrop-blur-[2px] lg:hidden" @click="sheet=false"></div>
            <aside class="studio-aside flex min-h-0 flex-col border-r border-white/[0.06] bg-ink-900/95 lg:bg-ink-900/40" :class="sheet ? 'is-open' : ''">
                <div class="flex items-center justify-between px-4 pt-2 lg:hidden" @click="sheet=false">
                    <span class="mx-auto block h-1.5 w-12 rounded-full bg-white/20"></span>
                </div>
                <div class="flex items-center justify-between px-4 pb-1 pt-2 lg:hidden">
                    <p class="flex items-center gap-2 text-sm font-bold text-white"><Icon :name="({ia:'sparkles', contenu:'pencil', pages:'file', style:'palette', modules:'blocks'})[tab]" :size="16" />{{ ({ia:'Assistant IA', contenu:'Contenu', pages:'Pages', style:'Style', modules:'Modules'})[tab] }}</p>
                    <div class="flex items-center gap-2">
                        <button @click="undo" :disabled="!undoStack.length" class="rounded-lg bg-white/5 px-2 py-1 text-sm text-slate-300 disabled:opacity-30">↶</button>
                        <button @click="redo" :disabled="!redoStack.length" class="rounded-lg bg-white/5 px-2 py-1 text-sm text-slate-300 disabled:opacity-30">↷</button>
                        <button @click="sheet=false" class="rounded-lg bg-white/5 px-2.5 py-1 text-sm text-slate-300">✕</button>
                    </div>
                </div>
                <div class="hidden gap-1 border-b border-white/[0.06] p-2 text-sm font-semibold lg:flex">
                    <button v-for="t in [['ia','sparkles','IA'],['contenu','pencil','Contenu'],['pages','file','Pages'],['style','palette','Style'],['modules','blocks','Modules']]" :key="t[0]" @click="tab=t[0]" class="flex flex-1 flex-col items-center gap-1 rounded-lg px-1.5 py-2 text-[11px] font-semibold transition" :class="tab===t[0] ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'"><Icon :name="t[1]" :size="18" />{{ t[2] }}</button>
                </div>

                <div v-if="loading" class="p-6 text-sm text-slate-400">Chargement…</div>

                <!-- ══ IA ══ -->
                <div v-else-if="st && tab==='ia'" class="flex min-h-0 flex-1 flex-col">
                    <div ref="chatBox" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                        <div v-for="(m,i) in messages" :key="i" class="flex" :class="m.role==='user' ? 'justify-end' : 'justify-start'">
                            <div :class="m.role==='user' ? 'bg-brand-gradient text-white' : 'bg-white/[0.05] text-slate-200'" class="max-w-[92%] rounded-2xl px-4 py-2.5 text-sm">
                                <template v-if="m.pending">
                                    <span class="inline-flex items-center gap-2 text-slate-400"><span class="inline-flex gap-1"><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-400"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-400" style="animation-delay:.15s"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-400" style="animation-delay:.3s"></span></span>{{ workLabel }}</span>
                                </template>
                                <template v-else>
                                    <span class="whitespace-pre-line">{{ m.text }}</span>
                                    <div v-if="m.applied?.length" class="mt-2.5 flex flex-wrap gap-1.5">
                                        <span v-for="(a,k) in m.applied" :key="k" class="rounded-md bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">✓ {{ a }}</span>
                                    </div>
                                    <div v-if="m.logos?.length" class="mt-3 grid grid-cols-2 gap-2">
                                        <button v-for="l in m.logos" :key="l.url" type="button" @click="useLogo(l.url)" class="group rounded-xl border border-white/10 p-2 text-left transition hover:border-brand-400" :class="st?.theme==='dark' ? 'bg-slate-900 text-white' : 'bg-white text-slate-900'" :title="'Utiliser : ' + l.name"><div class="logo-inline h-10 w-full" v-html="l.svg"></div><span class="mt-1 block truncate text-[10px] font-semibold opacity-70 group-hover:opacity-100">{{ l.name }} · Utiliser</span></button>
                                    </div>
                                    <div v-if="m.undo_id && !m.reverted" class="mt-2 flex items-center gap-3 text-[11px]">
                                        <button @click="revert(m)" class="font-semibold text-slate-400 underline-offset-2 hover:text-white hover:underline">↶ Annuler ces modifications</button>
                                        <span v-if="m.cost" class="text-slate-500">−{{ m.cost }} crédit{{ m.cost > 1 ? 's' : '' }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <template v-if="st.credits && st.credits.balance <= 0">
                        <div class="m-3 rounded-2xl border border-amber-400/30 bg-amber-500/10 p-4 text-sm">
                            <p class="font-semibold text-amber-200">Vous avez utilisé vos {{ st.credits.quota }} crédits IA{{ paid ? '' : ' d\'essai' }}.</p>
                            <p class="mt-1 text-slate-300">Les modifications manuelles restent illimitées (onglet Contenu). Pour continuer avec l'IA :</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button v-if="!paid" @click="modal='publish'" class="btn-brand !px-4 !py-2 text-xs">{{ plans.pro.trial > 0 ? '🚀 Mettre en ligne · 100 crédits inclus' : '✦ Souscrire · 100 crédits IA inclus' }}</button>
                                <button v-else-if="trial" @click="subscribeNow" :disabled="subscribing" class="btn-brand !px-4 !py-2 text-xs disabled:opacity-50">{{ subscribing ? '…' : `✦ Souscrire maintenant · ${trial.quota} crédits inclus` }}</button>
                                <button @click="modal='credits'" class="rounded-xl border border-white/15 px-4 py-2 text-xs font-semibold text-white hover:bg-white/5">{{ paid ? 'Recharger mes crédits' : 'Voir les options' }}</button>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div v-if="trial" class="mx-3 mb-2 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-400/25 bg-emerald-500/[0.08] px-3 py-2 text-xs">
                            <span class="text-slate-200"><span class="font-semibold text-emerald-300">Essai gratuit</span> · {{ trial.trial_days_left }} jour{{ trial.trial_days_left > 1 ? 's' : '' }} restant{{ trial.trial_days_left > 1 ? 's' : '' }} · {{ trial.quota }} crédits IA / mois dès votre souscription</span>
                            <button @click="subscribeNow" :disabled="subscribing" class="rounded-lg bg-brand-gradient px-3 py-1.5 font-semibold text-white disabled:opacity-50">{{ subscribing ? '…' : 'Souscrire maintenant' }}</button>
                        </div>
                        <div class="flex gap-1.5 overflow-x-auto px-4 pb-2 [scrollbar-width:none] lg:flex-wrap lg:overflow-visible"><button v-for="s in suggestions" :key="s" @click="send(s)" :disabled="sending" class="shrink-0 whitespace-nowrap rounded-full border border-white/10 px-2.5 py-1 text-[11px] text-slate-300 transition hover:border-brand-400/60 hover:text-white disabled:opacity-40 lg:whitespace-normal">{{ s }}</button></div>
                        <form @submit.prevent="send()" class="border-t border-white/[0.06] p-3">
                            <div class="flex items-end gap-2">
                                <textarea v-model="input" :disabled="sending || !site.editable" rows="2" @keydown.enter.exact.prevent="send()" :placeholder="curPage ? `Que changer sur la page « ${page?.title} » ?` : 'Ex : crée une page Nos réalisations avec une galerie…'" class="field flex-1 resize-none text-sm"></textarea>
                                <button type="submit" :disabled="sending || !site.editable || !input.trim()" class="btn-brand shrink-0 !px-4 !py-3 text-sm">{{ sending ? '…' : '↑' }}</button>
                            </div>
                            <p v-if="st.credits" class="mt-2 flex items-center justify-between text-[11px] text-slate-500"><span>1 crédit par action · 2 pour une page · questions gratuites</span><button type="button" @click="modal='credits'" class="font-semibold text-slate-400 hover:text-white">✦ {{ st.credits.balance }} restant{{ st.credits.balance > 1 ? 's' : '' }}</button></p>
                        </form>
                    </template>
                </div>

                <!-- ══ CONTENU : ACCUEIL ══ -->
                <div v-else-if="st && tab==='contenu' && !curPage" class="min-h-0 flex-1 overflow-y-auto">
                    <!-- Site créé sans fiche Google : proposer de la rattacher (avis, note, photos) -->
                    <div v-if="!hasPlace" class="mx-3 mt-3 rounded-2xl border border-sky-400/25 bg-sky-500/[0.08] p-3.5 text-xs">
                        <p class="font-semibold text-sky-100">Pas encore reliée à Google</p>
                        <p class="mt-1 text-slate-300">Rattachez votre fiche Google pour afficher votre note, vos avis et vos photos, et activer la carte.</p>
                        <button @click="modal='google'; searchGoogle()" class="mt-2.5 rounded-lg bg-sky-500/20 px-3 py-1.5 font-semibold text-sky-100 ring-1 ring-sky-400/40 hover:bg-sky-500/30">Rattacher ma fiche Google</button>
                    </div>
                    <p class="px-5 pt-4 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Sections de l'accueil</p>
                    <p class="px-5 pb-2 text-xs text-slate-500">Cliquez un texte ou une image <em>directement dans l'aperçu</em> pour le modifier.</p>

                    <div v-for="s in orderedSections" :key="s.id" class="border-t border-white/[0.05]">
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <button @click="goto(s.id)" class="flex-1 text-left text-sm font-semibold" :class="isHidden(s.id) ? 'text-slate-500 line-through' : 'text-white'">{{ s.label }}</button>
                            <template v-if="!s.fixed">
                                <button @click="move(s.id,-1)" class="rounded p-1 text-xs text-slate-500 hover:text-white" title="Monter">▲</button>
                                <button @click="move(s.id,1)" class="rounded p-1 text-xs text-slate-500 hover:text-white" title="Descendre">▼</button>
                                <button @click="toggleSection(s.id)" class="rounded p-1 text-xs" :class="isHidden(s.id) ? 'text-slate-500' : 'text-emerald-300'" :title="isHidden(s.id) ? 'Afficher' : 'Masquer'">{{ isHidden(s.id) ? '○' : '●' }}</button>
                            </template>
                            <button @click="panel = panel===s.id ? '' : s.id" class="rounded p-1 text-xs text-slate-500 hover:text-white">{{ panel===s.id ? '−' : '+' }}</button>
                        </div>

                        <div v-if="panel===s.id" class="space-y-3 bg-white/[0.02] px-4 pb-4 pt-1">
                            <template v-if="s.id==='hero'">
                                <label class="lbl">Surtitre<input class="fld" :value="st.content.tagline || ''" @change="edit('content.tagline',$event.target.value)" /></label>
                                <label class="lbl">Titre<textarea class="fld" rows="2" :value="st.content.hero_title || ''" @change="edit('content.hero_title',$event.target.value)"></textarea></label>
                                <label class="lbl">Sous-titre<textarea class="fld" rows="2" :value="st.content.hero_subtitle || ''" @change="edit('content.hero_subtitle',$event.target.value)"></textarea></label>
                                <label class="lbl">Texte du bouton<input class="fld" :value="st.content.cta_label || st.defaults.cta" @change="edit('content.cta_label',$event.target.value)" /></label>
                                <div class="rounded-xl border border-white/[0.06] p-3">
                                    <p class="mb-2 text-xs font-semibold text-slate-400">Photo de fond</p>
                                    <button @click="openPicker('images.hero')" class="flex w-full items-center gap-3 rounded-lg bg-white/[0.04] p-2 text-left text-sm text-slate-200 hover:bg-white/[0.07]"><img :src="st.images?.hero || st.photos?.[0]" class="h-12 w-16 rounded object-cover" /> Changer l'image</button>
                                </div>
                                <div>
                                    <p class="mb-1.5 text-xs font-semibold text-slate-400">Points forts (4 max)</p>
                                    <div v-for="(b,i) in (st.content.badges||[])" :key="i" class="mb-1.5 flex gap-2"><input class="fld flex-1" :value="b" @change="edit(`content.badges.${i}`,$event.target.value)" /><button @click="removeAt('content.badges',i)" class="text-slate-500 hover:text-rose-300">✕</button></div>
                                    <button v-if="(st.content.badges||[]).length<4" @click="addBadge" class="text-xs font-semibold text-brand-400">+ Ajouter</button>
                                </div>
                                <div>
                                    <p class="mb-1.5 text-xs font-semibold text-slate-400">Chiffres clés</p>
                                    <div v-for="(sv,i) in (st.content.stats||[])" :key="i" class="mb-1.5 grid grid-cols-[1fr,2fr] gap-2"><input class="fld" :value="sv.v" @change="edit(`content.stats.${i}.v`,$event.target.value)" /><input class="fld" :value="sv.l" @change="edit(`content.stats.${i}.l`,$event.target.value)" /></div>
                                </div>
                            </template>

                            <template v-else-if="s.id==='services'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.services_title || 'Un savoir-faire complet'" @change="edit('content.services_title',$event.target.value)" /></label>
                                <label class="lbl">Introduction<textarea class="fld" rows="2" :value="st.content.services_intro || st.content.about_p1 || ''" @change="edit('content.services_intro',$event.target.value)"></textarea></label>
                                <div v-for="(sv,i) in (st.content.services||[])" :key="i" class="rounded-xl border border-white/[0.06] p-3">
                                    <div class="flex gap-2"><input class="fld flex-1 font-semibold" :value="sv.name" @change="edit(`content.services.${i}.name`,$event.target.value)" /><button @click="removeAt('content.services',i)" class="text-slate-500 hover:text-rose-300">✕</button></div>
                                    <textarea class="fld mt-1.5" rows="2" :value="sv.desc" @change="edit(`content.services.${i}.desc`,$event.target.value)"></textarea>
                                    <input class="fld mt-1.5" :value="sv.price" placeholder="Prix" @change="edit(`content.services.${i}.price`,$event.target.value)" />
                                </div>
                                <button @click="addService" class="text-xs font-semibold text-brand-400">+ Ajouter un service</button>
                            </template>

                            <template v-else-if="s.id==='gallery'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.gallery_title || 'En images'" @change="edit('content.gallery_title',$event.target.value)" /></label>
                                <div class="grid grid-cols-3 gap-2">
                                    <div v-for="(g,i) in galleryList" :key="i" class="group relative"><img :src="g" class="h-16 w-full rounded-lg object-cover" @click="openPicker(`images.gallery.${i}`, g)" /><button @click="removeGallery(i)" class="absolute right-1 top-1 hidden rounded bg-black/70 px-1 text-xs text-white group-hover:block">✕</button></div>
                                    <button @click="openPicker(`images.gallery.${galleryList.length}`)" class="grid h-16 place-items-center rounded-lg border border-dashed border-white/20 text-xs text-slate-400 hover:border-brand-400 hover:text-white">+ Photo</button>
                                </div>
                            </template>

                            <template v-else-if="s.id==='about'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.about_title || 'Votre partenaire de confiance'" @change="edit('content.about_title',$event.target.value)" /></label>
                                <label class="lbl">Paragraphe 1<textarea class="fld" rows="3" :value="st.content.about_p1 || ''" @change="edit('content.about_p1',$event.target.value)"></textarea></label>
                                <label class="lbl">Paragraphe 2<textarea class="fld" rows="3" :value="st.content.about_p2 || ''" @change="edit('content.about_p2',$event.target.value)"></textarea></label>
                                <button @click="openPicker('images.about')" class="flex w-full items-center gap-3 rounded-lg bg-white/[0.04] p-2 text-left text-sm text-slate-200 hover:bg-white/[0.07]"><img :src="st.images?.about || st.photos?.[1] || st.photos?.[0]" class="h-12 w-16 rounded object-cover" /> Changer l'image</button>
                            </template>

                            <template v-else-if="s.id==='reviews'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.reviews_title || 'Ils nous recommandent'" @change="edit('content.reviews_title',$event.target.value)" /></label>
                                <p class="text-xs text-slate-500">Les avis proviennent de votre fiche Google ({{ st.business.reviews_count || 0 }} avis, note {{ st.business.rating || '—' }}).</p>
                            </template>

                            <template v-else-if="s.id==='faq'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.faq_title || 'Questions fréquentes'" @change="edit('content.faq_title',$event.target.value)" /></label>
                                <div v-for="(f,i) in (st.content.faq||[])" :key="i" class="rounded-xl border border-white/[0.06] p-3">
                                    <div class="flex gap-2"><input class="fld flex-1 font-semibold" :value="f.q" @change="edit(`content.faq.${i}.q`,$event.target.value)" /><button @click="removeAt('content.faq',i)" class="text-slate-500 hover:text-rose-300">✕</button></div>
                                    <textarea class="fld mt-1.5" rows="2" :value="f.a" @change="edit(`content.faq.${i}.a`,$event.target.value)"></textarea>
                                </div>
                                <button @click="addFaq" class="text-xs font-semibold text-brand-400">+ Ajouter une question</button>
                            </template>

                            <template v-else-if="s.id==='booking'">
                                <label class="flex items-center justify-between text-sm text-slate-300">Module activé
                                    <button type="button" @click="toggleModule('booking')" :class="moduleOn('booking') ? 'bg-brand-500' : 'bg-white/10'" class="relative h-6 w-11 rounded-full transition"><span :class="moduleOn('booking') ? 'translate-x-5' : 'translate-x-0.5'" class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition"></span></button>
                                </label>
                                <label class="lbl">Titre<input class="fld" :value="st.booking?.title || ''" placeholder="Automatique" @change="edit('booking.title',$event.target.value)" /></label>
                                <label class="lbl">Sous-titre<input class="fld" :value="st.booking?.sub || ''" placeholder="Automatique" @change="edit('booking.sub',$event.target.value)" /></label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="lbl">Argument 1<input class="fld" :value="st.booking?.point1 || ''" placeholder="Réponse rapide" @change="edit('booking.point1',$event.target.value)" /></label>
                                    <label class="lbl">Argument 2<input class="fld" :value="st.booking?.point2 || ''" placeholder="Confidentiel" @change="edit('booking.point2',$event.target.value)" /></label>
                                </div>
                                <div v-if="st.form" class="mt-2 rounded-xl border border-brand-500/30 bg-brand-500/[0.05] p-3">
                                    <div class="mb-2 flex items-center justify-between">
                                        <p class="text-sm font-bold text-white">🧩 Formulaire multi-étapes</p>
                                        <button @click="restoreForm" class="text-[11px] text-slate-400 hover:text-white">Restaurer le modèle du métier</button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="lbl">Type de demande
                                            <select class="fld" :value="st.form.type" @change="st.form.type=$event.target.value; edit('booking.type',$event.target.value); commitForm()">
                                                <option value="reservation">Réservation</option><option value="rdv">Rendez-vous</option><option value="devis">Devis</option><option value="contact">Contact</option>
                                            </select>
                                        </label>
                                        <label class="lbl">Bouton d'envoi<input class="fld" :value="st.form.cta" @change="st.form.cta=$event.target.value; commitForm()" /></label>
                                        <label class="lbl">Délai annoncé<input class="fld" :value="st.form.delay" placeholder="Réponse sous 24h" @change="st.form.delay=$event.target.value; commitForm()" /></label>
                                        <label class="lbl col-span-2">Message de succès<input class="fld" :value="st.form.success" @change="st.form.success=$event.target.value; commitForm()" /></label>
                                    </div>
                                    <div v-for="(stp,i) in fSteps" :key="i" class="mt-3 rounded-lg border border-white/[0.08] bg-ink-900/60">
                                        <div class="flex items-center gap-2 px-3 py-2">
                                            <button @click="openStep = openStep===i ? -1 : i" class="flex-1 text-left text-sm font-semibold text-white"><span class="mr-1.5 rounded bg-white/10 px-1.5 text-[11px]">{{ i+1 }}</span>{{ stp.title }}<span v-if="stp.contact" class="ml-2 text-[10px] font-normal text-slate-500">(coordonnées · fixe)</span></button>
                                            <template v-if="!stp.contact">
                                                <button @click="moveStep(i,-1)" class="text-xs text-slate-500 hover:text-white">▲</button>
                                                <button @click="moveStep(i,1)" class="text-xs text-slate-500 hover:text-white">▼</button>
                                                <button @click="removeStep(i)" class="text-xs text-slate-500 hover:text-rose-300">✕</button>
                                            </template>
                                        </div>
                                        <div v-if="openStep===i" class="space-y-2 border-t border-white/[0.06] p-3">
                                            <div class="grid grid-cols-[1fr,110px] gap-2">
                                                <input class="fld" :value="stp.title" placeholder="Titre de l'étape" @change="stp.title=$event.target.value; commitForm()" />
                                                <input class="fld font-mono text-xs" :value="stp.icon" placeholder="fa-icon" @change="stp.icon=$event.target.value; commitForm()" />
                                            </div>
                                            <div v-for="(f,j) in stp.fields" :key="j" class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-2.5">
                                                <div class="flex items-center gap-1.5">
                                                    <input class="fld flex-1" :value="f.label" placeholder="Intitulé du champ" @change="setFieldLabel(f,$event.target.value)" />
                                                    <button @click="moveField(i,j,-1)" class="text-xs text-slate-500 hover:text-white">▲</button>
                                                    <button @click="moveField(i,j,1)" class="text-xs text-slate-500 hover:text-white">▼</button>
                                                    <button v-if="!['name','phone'].includes(f.key)" @click="removeField(i,j)" class="text-xs text-slate-500 hover:text-rose-300">✕</button>
                                                </div>
                                                <div class="mt-1.5 grid grid-cols-[1fr,auto] items-center gap-2">
                                                    <select class="fld" :value="f.type" @change="setFieldType(f,$event.target.value)"><option v-for="t in FIELD_TYPES" :key="t[0]" :value="t[0]">{{ t[1] }}</option></select>
                                                    <label class="flex items-center gap-1.5 text-xs text-slate-300"><input type="checkbox" :checked="!!f.required" @change="f.required=$event.target.checked; commitForm()" class="h-3.5 w-3.5 rounded border-white/20 bg-white/5" /> Obligatoire</label>
                                                </div>
                                                <input v-if="['text','textarea','number','phone','email'].includes(f.type)" class="fld mt-1.5" :value="f.placeholder || ''" placeholder="Texte d'aide (placeholder)" @change="f.placeholder=$event.target.value; commitForm()" />
                                                <div v-if="['cards','chips','toggle','select'].includes(f.type)" class="mt-1.5">
                                                    <textarea class="fld" rows="3" :value="optionsText(f)" @change="setOptions(f,$event.target.value)" :placeholder="f.type==='cards' ? 'Une option par ligne : Libellé | description | fa-icone' : 'Une option par ligne'"></textarea>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                <button v-for="t in [['cards','+ Cartes'],['chips','+ Pastilles'],['toggle','+ Bascule'],['text','+ Texte'],['date','+ Date'],['number','+ Nombre']]" :key="t[0]" @click="addField(i,t[0])" class="rounded-md border border-white/10 px-2 py-1 text-[11px] text-slate-300 hover:border-brand-400 hover:text-white">{{ t[1] }}</button>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="addStep" class="mt-2 w-full rounded-lg border border-dashed border-white/15 py-2 text-xs font-semibold text-slate-300 hover:border-brand-400 hover:text-white">+ Ajouter une étape</button>
                                </div>
                                <p class="text-xs text-slate-500">Les demandes arrivent dans votre espace Joow et par email, avec toutes les réponses.</p>
                            </template>

                            <template v-else-if="s.id==='process'">
                                <label class="lbl">Surtitre<input class="fld" :value="st.content.process_tag || 'Comment ça se passe'" @change="edit('content.process_tag',$event.target.value)" /></label>
                                <label class="lbl">Titre<input class="fld" :value="st.content.process_title || 'Simple, rapide, sans surprise'" @change="edit('content.process_title',$event.target.value)" /></label>
                                <div v-for="i in 3" :key="i" class="rounded-xl border border-white/[0.06] p-3">
                                    <input class="fld font-semibold" :value="st.content.process?.[i-1]?.title || ''" :placeholder="`Étape ${i}`" @change="edit(`content.process.${i-1}.title`,$event.target.value,{reload:true})" />
                                    <input class="fld mt-1.5" :value="st.content.process?.[i-1]?.desc || ''" placeholder="Une phrase" @change="edit(`content.process.${i-1}.desc`,$event.target.value,{reload:true})" />
                                </div>
                            </template>

                            <template v-else-if="s.id==='cta'">
                                <label class="lbl">Surtitre<input class="fld" :value="st.content.cta_band_tag || 'On vous attend'" @change="edit('content.cta_band_tag',$event.target.value)" /></label>
                                <label class="lbl">Titre<textarea class="fld" rows="2" :value="st.content.cta_band_title || st.content.cta_text || ''" @change="edit('content.cta_band_title',$event.target.value)"></textarea></label>
                            </template>

                            <template v-else-if="s.id==='contact'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.contact_title || st.content.cta_label || st.defaults.cta" @change="edit('content.contact_title',$event.target.value)" /></label>
                                <label class="lbl">Texte d'appel<textarea class="fld" rows="2" :value="st.content.cta_text || ''" @change="edit('content.cta_text',$event.target.value)"></textarea></label>
                                <label class="lbl">Nom de l'établissement<input class="fld" :value="st.business.name || ''" @change="edit('business.name',$event.target.value,{reload:true})" /></label>
                                <label class="lbl">Téléphone<input class="fld" :value="st.business.phone || ''" @change="edit('business.phone',$event.target.value,{reload:true})" /></label>
                                <label class="lbl">Adresse<input class="fld" :value="st.business.address || ''" @change="edit('business.address',$event.target.value,{reload:true})" /></label>
                                <label class="lbl">Email de contact<input class="fld" :value="st.business.email || ''" @change="edit('business.email',$event.target.value)" /></label>
                                <label class="lbl">Horaires (une ligne par jour)<textarea class="fld" rows="4" :value="(st.business.opening_hours||[]).join('\n')" @change="setHours($event.target.value)"></textarea></label>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- ══ CONTENU : PAGE ADDITIONNELLE (blocs) ══ -->
                <div v-else-if="st && tab==='contenu' && page" class="min-h-0 flex-1 overflow-y-auto">
                    <div class="border-b border-white/[0.05] px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button @click="scrollBlock(-2)" class="flex-1 text-left text-sm font-semibold text-white">En-tête de la page</button>
                            <button @click="openBlock = openBlock===-2 ? -1 : -2" class="rounded p-1 text-xs text-slate-500 hover:text-white">{{ openBlock===-2 ? '−' : '+' }}</button>
                        </div>
                        <div v-if="openBlock===-2" class="mt-2 space-y-2">
                            <label class="lbl">Nom de la page (menu)<input class="fld" :value="page.title" @change="page.title=$event.target.value; commitPages()" /></label>
                            <label class="lbl">Surtitre<input class="fld" :value="page.hero.tag" @change="page.hero.tag=$event.target.value; commitPages()" /></label>
                            <label class="lbl">Titre<input class="fld" :value="page.hero.title" @change="page.hero.title=$event.target.value; commitPages()" /></label>
                            <label class="lbl">Sous-titre<textarea class="fld" rows="2" :value="page.hero.subtitle" @change="page.hero.subtitle=$event.target.value; commitPages()"></textarea></label>
                            <button @click="openPicker(`pages.${pageIndex}.hero.image`)" class="flex w-full items-center gap-3 rounded-lg bg-white/[0.04] p-2 text-left text-sm text-slate-200 hover:bg-white/[0.07]"><img :src="page.hero.image || st.photos?.[1] || st.photos?.[0]" class="h-12 w-16 rounded object-cover" /> Changer la photo d'en-tête</button>
                            <label class="lbl">Description Google (SEO)<input class="fld" :value="page.seo" placeholder="Automatique" @change="page.seo=$event.target.value; commitPages()" /></label>
                            <label class="flex items-center justify-between text-sm text-slate-300">Afficher dans le menu
                                <button type="button" @click="page.nav=!page.nav; commitPages(true)" :class="page.nav ? 'bg-brand-500' : 'bg-white/10'" class="relative h-6 w-11 rounded-full transition"><span :class="page.nav ? 'translate-x-5' : 'translate-x-0.5'" class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition"></span></button>
                            </label>
                        </div>
                    </div>

                    <p class="px-4 pt-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Blocs de la page</p>
                    <div v-for="(blk,j) in page.blocks" :key="j" class="border-t border-white/[0.05]">
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <button @click="scrollBlock(j)" class="min-w-0 flex-1 truncate text-left text-sm"><span class="mr-1.5 rounded bg-white/10 px-1.5 text-[10px] text-slate-400">{{ blockLabel(blk.type) }}</span><span class="font-semibold text-white">{{ blk.title || '—' }}</span></button>
                            <button @click="moveBlock(j,-1)" class="rounded p-1 text-xs text-slate-500 hover:text-white">▲</button>
                            <button @click="moveBlock(j,1)" class="rounded p-1 text-xs text-slate-500 hover:text-white">▼</button>
                            <button @click="removeBlock(j)" class="rounded p-1 text-xs text-slate-500 hover:text-rose-300">✕</button>
                            <button @click="openBlock = openBlock===j ? -1 : j" class="rounded p-1 text-xs text-slate-500 hover:text-white">{{ openBlock===j ? '−' : '+' }}</button>
                        </div>
                        <div v-if="openBlock===j" class="space-y-2 bg-white/[0.02] px-4 pb-4 pt-1">
                            <label v-if="blk.type!=='cta'" class="lbl">Titre<input class="fld" :value="blk.title" @change="blk.title=$event.target.value; commitPages()" /></label>
                            <label v-else class="lbl">Titre<input class="fld" :value="blk.title" @change="blk.title=$event.target.value; commitPages()" /></label>
                            <!-- text -->
                            <template v-if="blk.type==='text'">
                                <label class="lbl">Texte (une ligne vide = nouveau paragraphe)<textarea class="fld" rows="6" :value="blk.body" @change="blk.body=$event.target.value; commitPages()"></textarea></label>
                                <div class="flex items-center gap-2">
                                    <button @click="openPicker(blockPath(j)+'.image')" class="flex flex-1 items-center gap-3 rounded-lg bg-white/[0.04] p-2 text-left text-sm text-slate-200 hover:bg-white/[0.07]"><img v-if="blk.image" :src="blk.image" class="h-10 w-14 rounded object-cover" /><span v-else class="grid h-10 w-14 place-items-center rounded bg-white/5 text-xs">—</span> {{ blk.image ? 'Changer l\'image' : 'Ajouter une image' }}</button>
                                    <button v-if="blk.image" @click="blk.image=null; commitPages(true)" class="text-xs text-slate-500 hover:text-rose-300">Retirer</button>
                                </div>
                                <label class="lbl">Position de l'image<select class="fld" :value="blk.image_side" @change="blk.image_side=$event.target.value; commitPages(true)"><option value="right">À droite</option><option value="left">À gauche</option></select></label>
                            </template>
                            <!-- features / pricing intro -->
                            <label v-if="['features','pricing'].includes(blk.type)" class="lbl">Introduction<textarea class="fld" rows="2" :value="blk.intro" @change="blk.intro=$event.target.value; commitPages()"></textarea></label>
                            <!-- items -->
                            <template v-if="Array.isArray(blk.items)">
                                <div v-for="(it,k) in blk.items" :key="k" class="rounded-xl border border-white/[0.06] p-2.5">
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1 space-y-1.5">
                                            <template v-if="blk.type==='features'"><div class="grid grid-cols-[1fr,120px] gap-1.5"><input class="fld font-semibold" :value="it.title" placeholder="Titre" @change="it.title=$event.target.value; commitPages()" /><input class="fld font-mono text-xs" :value="it.icon" placeholder="fa-icone" @change="it.icon=$event.target.value; commitPages()" /></div><textarea class="fld" rows="2" :value="it.desc" placeholder="Description" @change="it.desc=$event.target.value; commitPages()"></textarea></template>
                                            <template v-else-if="blk.type==='steps'"><input class="fld font-semibold" :value="it.title" placeholder="Titre" @change="it.title=$event.target.value; commitPages()" /><input class="fld" :value="it.desc" placeholder="Une phrase" @change="it.desc=$event.target.value; commitPages()" /></template>
                                            <template v-else-if="blk.type==='faq'"><input class="fld font-semibold" :value="it.q" placeholder="Question" @change="it.q=$event.target.value; commitPages()" /><textarea class="fld" rows="2" :value="it.a" placeholder="Réponse" @change="it.a=$event.target.value; commitPages()"></textarea></template>
                                            <template v-else-if="blk.type==='pricing'"><div class="grid grid-cols-[1fr,110px] gap-1.5"><input class="fld font-semibold" :value="it.name" placeholder="Nom de l'offre" @change="it.name=$event.target.value; commitPages()" /><input class="fld" :value="it.price" placeholder="Prix" @change="it.price=$event.target.value; commitPages()" /></div><input class="fld" :value="it.desc" placeholder="Description courte" @change="it.desc=$event.target.value; commitPages()" /><textarea class="fld" rows="2" :value="(it.features||[]).join('\n')" placeholder="Inclus (une ligne par élément)" @change="it.features=$event.target.value.split('\n').map(s=>s.trim()).filter(Boolean); commitPages()"></textarea></template>
                                            <template v-else-if="blk.type==='team'"><div class="grid grid-cols-2 gap-1.5"><input class="fld font-semibold" :value="it.name" placeholder="Nom" @change="it.name=$event.target.value; commitPages()" /><input class="fld" :value="it.role" placeholder="Rôle" @change="it.role=$event.target.value; commitPages()" /></div><textarea class="fld" rows="2" :value="it.bio" placeholder="Bio courte" @change="it.bio=$event.target.value; commitPages()"></textarea><button @click="openPicker(`${blockPath(j)}.items.${k}.image`)" class="text-xs font-semibold text-brand-400">{{ it.image ? 'Changer la photo' : '+ Photo' }}</button></template>
                                            <template v-else-if="blk.type==='testimonials'"><textarea class="fld" rows="2" :value="it.text" placeholder="Témoignage" @change="it.text=$event.target.value; commitPages()"></textarea><div class="grid grid-cols-2 gap-1.5"><input class="fld" :value="it.author" placeholder="Auteur" @change="it.author=$event.target.value; commitPages()" /><input class="fld" :value="it.role" placeholder="Rôle / ville" @change="it.role=$event.target.value; commitPages()" /></div></template>
                                            <template v-else-if="blk.type==='stats'"><div class="grid grid-cols-[1fr,2fr] gap-1.5"><input class="fld font-semibold" :value="it.v" placeholder="+10 ans" @change="it.v=$event.target.value; commitPages()" /><input class="fld" :value="it.l" placeholder="libellé" @change="it.l=$event.target.value; commitPages()" /></div></template>
                                        </div>
                                        <button @click="removeItem(blk,k)" class="text-slate-500 hover:text-rose-300">✕</button>
                                    </div>
                                </div>
                                <button @click="addItem(blk)" class="text-xs font-semibold text-brand-400">+ Ajouter un élément</button>
                            </template>
                            <!-- gallery -->
                            <template v-if="blk.type==='gallery'">
                                <div class="grid grid-cols-3 gap-2">
                                    <div v-for="(g,k) in blk.images" :key="k" class="group relative"><img :src="g" class="h-16 w-full rounded-lg object-cover" @click="openPicker(`${blockPath(j)}.images.${k}`, g)" /><button @click="blk.images.splice(k,1); commitPages(true)" class="absolute right-1 top-1 hidden rounded bg-black/70 px-1 text-xs text-white group-hover:block">✕</button></div>
                                    <button @click="openPicker(`${blockPath(j)}.images.${blk.images.length}`)" class="grid h-16 place-items-center rounded-lg border border-dashed border-white/20 text-xs text-slate-400 hover:border-brand-400 hover:text-white">+ Photo</button>
                                </div>
                                <p v-if="!blk.images.length" class="text-xs text-slate-500">Sans photo choisie, la galerie affiche les photos de votre fiche Google.</p>
                            </template>
                            <!-- cta / contact / video -->
                            <label v-if="['cta','contact'].includes(blk.type)" class="lbl">Texte<textarea class="fld" rows="2" :value="blk.text" @change="blk.text=$event.target.value; commitPages()"></textarea></label>
                            <label v-if="blk.type==='cta'" class="lbl">Texte du bouton<input class="fld" :value="blk.button" placeholder="Automatique" @change="blk.button=$event.target.value; commitPages()" /></label>
                            <template v-if="blk.type==='module'">
                                <label class="lbl">Module à afficher dans cette page
                                    <select class="fld" :value="blk.key || 'booking'" @change="blk.key=$event.target.value; commitPages(true)">
                                        <option v-for="o in MODULE_BLOCKS" :key="o[0]" :value="o[0]">{{ o[1] }}</option>
                                    </select>
                                </label>
                                <p class="text-[11px] text-slate-500">Le module doit être actif (onglet Modules) et, pour la carte ou les chambres, contenir des éléments. Son contenu est le même que sur l'accueil.</p>
                            </template>
                            <label v-if="blk.type==='video'" class="lbl">Lien YouTube ou Vimeo<input class="fld" :value="blk.url || ''" placeholder="https://www.youtube.com/watch?v=…" @change="blk.url=$event.target.value; commitPages()" /></label>
                        </div>
                    </div>
                    <div class="p-4">
                        <button @click="modal='blocktype'" class="w-full rounded-xl border border-dashed border-white/15 py-3 text-sm font-semibold text-slate-300 hover:border-brand-400 hover:text-white">+ Ajouter un bloc</button>
                        <button @click="tab='ia'; input=`Enrichis la page « ${page.title} » : `" class="mt-2 w-full rounded-xl bg-brand-500/10 py-2.5 text-xs font-semibold text-brand-300 hover:bg-brand-500/20"><span class="inline-flex items-center gap-1.5"><Icon name="sparkles" :size="14" />Demander à l'IA de compléter cette page</span></button>
                    </div>
                </div>

                <!-- ══ PAGES ══ -->
                <div v-else-if="st && tab==='pages'" class="min-h-0 flex-1 overflow-y-auto p-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Pages du site</p>
                    <p class="mt-1 text-xs text-slate-500">L'accueil regroupe vos sections. Ajoutez des pages dédiées : réalisations, tarifs, équipe, prestations détaillées…</p>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center gap-3 rounded-xl border border-white/[0.08] bg-white/[0.03] px-3 py-2.5">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-gradient text-sm">🏠</span>
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-white">Accueil</p><p class="truncate text-[11px] text-slate-500">{{ site.slug }}.joow.fr</p></div>
                            <button @click="curPage=''; tab='contenu'" class="text-xs font-semibold text-brand-400">Ouvrir</button>
                        </div>
                        <div v-for="(p,i) in pages" :key="p.slug" class="flex items-center gap-3 rounded-xl border px-3 py-2.5" :class="curPage===p.slug ? 'border-brand-500/50 bg-brand-500/[0.08]' : 'border-white/[0.08] bg-white/[0.03]'">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-slate-300"><Icon name="file" :size="16" /></span>
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-white">{{ p.title }}</p><p class="truncate text-[11px] text-slate-500">/{{ p.slug }}/ · {{ p.blocks.length }} bloc{{ p.blocks.length>1?'s':'' }}{{ p.nav ? '' : ' · hors menu' }}</p></div>
                            <button @click="movePage(i,-1)" class="text-xs text-slate-500 hover:text-white">▲</button>
                            <button @click="movePage(i,1)" class="text-xs text-slate-500 hover:text-white">▼</button>
                            <button @click="p.nav=!p.nav; commitPages(true)" class="text-xs" :class="p.nav ? 'text-emerald-300' : 'text-slate-500'" :title="p.nav ? 'Dans le menu' : 'Hors menu'">{{ p.nav ? '●' : '○' }}</button>
                            <button @click="removePage(i)" class="text-xs text-slate-500 hover:text-rose-300">✕</button>
                            <button @click="curPage=p.slug; tab='contenu'" class="text-xs font-semibold text-brand-400">Ouvrir</button>
                        </div>
                    </div>
                    <button @click="modal='newpage'" class="btn-brand mt-4 w-full text-sm">+ Nouvelle page</button>
                    <p class="mt-3 text-xs text-slate-500">Astuce : dites simplement à l'IA « crée une page Nos réalisations » — elle rédige et met en page.</p>
                </div>

                <!-- ══ STYLE ══ -->
                <div v-else-if="st && tab==='style'" class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                    <div>
                        <p class="font-display font-bold text-white">En-tête &amp; logo</p>
                        <p class="mt-1 text-xs text-slate-500">Votre logo tel qu'il apparaît sur le visuel d'accueil et sur la barre blanche.</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="flex h-16 items-center justify-center rounded-xl bg-slate-900 px-3 text-white"><div v-if="brand.inline" class="logo-inline h-10 max-w-full" :class="brand.invert && 'invert hue-rotate-180'" v-html="brand.inline"></div><img v-else-if="brand.logo" :src="brand.logo" alt="" class="max-h-10 max-w-full object-contain" :class="brand.invert && 'invert hue-rotate-180'" /><span v-else class="text-xs text-slate-500">Icône du métier</span></div>
                            <div class="flex h-16 items-center justify-center rounded-xl bg-white px-3 text-slate-900"><div v-if="brand.inline" class="logo-inline h-10 max-w-full" :class="brand.invert && 'invert hue-rotate-180'" v-html="brand.inline"></div><img v-else-if="brand.logo" :src="brand.logo" alt="" class="max-h-10 max-w-full object-contain" :class="brand.invert && 'invert hue-rotate-180'" /><span v-else class="text-xs text-slate-400">Icône du métier</span></div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label class="cursor-pointer rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-white/25">Importer<input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" @change="uploadLogo" /></label>
                            <button type="button" @click="logoPanel = !logoPanel" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-gradient px-3 py-2 text-xs font-semibold text-white"><Icon name="sparkles" :size="14" />Logo IA</button>
                            <button v-if="brand.logo" type="button" @click="saveBrand({ remove: true })" class="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white">Retirer</button>
                            <button v-else-if="brand.source_logo" type="button" @click="saveBrand({ logo_url: brand.source_logo })" class="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white">Remettre le logo d'origine</button>
                        </div>
                        <div v-if="logoPanel" class="mt-3 space-y-2 rounded-xl border border-brand-400/30 bg-brand-500/[0.06] p-3">
                            <p class="text-xs text-slate-300">L'IA dessine 4 logos vectoriels à partir de votre nom, de votre métier et de votre couleur. <span class="text-slate-500">2 crédits par série.</span></p>
                            <div class="flex flex-wrap gap-1.5"><button v-for="sty in LOGO_STYLES" :key="sty" type="button" @click="logoStyle = sty" class="rounded-full border px-2.5 py-1 text-[11px] capitalize" :class="logoStyle===sty ? 'border-brand-400 text-white' : 'border-white/10 text-slate-400 hover:text-white'">{{ sty }}</button></div>
                            <input v-model="logoHint" class="fld" placeholder="Précisions : un burger stylisé, lettres fines, tons dorés…" />
                            <button type="button" @click="genLogos" :disabled="logoBusy" class="btn-brand w-full !py-2 text-xs">{{ logoBusy ? 'Création en cours (20 à 40 s)…' : 'Générer 4 propositions' }}</button>
                            <div v-if="logoProps.length" class="grid grid-cols-2 gap-2">
                                <button v-for="l in logoProps" :key="l.url" type="button" @click="useLogo(l.url)" class="group rounded-xl border border-white/10 p-2 text-left transition hover:border-brand-400" :class="st.theme==='dark' ? 'bg-slate-900 text-white' : 'bg-white text-slate-900'"><div class="logo-inline h-10 w-full" v-html="l.svg"></div><span class="mt-1 block truncate text-[10px] font-semibold opacity-70 group-hover:opacity-100">{{ l.name }} · Utiliser</span></button>
                            </div>
                        </div>
                        <div v-if="brand.logo" class="mt-3 space-y-2">
                            <div class="flex items-center justify-between gap-2 text-xs text-slate-300"><span>Fond derrière le logo</span><div class="flex gap-1"><button v-for="o in [['auto','Auto'],['none','Aucun'],['light','Clair'],['dark','Sombre'],['accent','Couleur']]" :key="o[0]" type="button" @click="saveBrand({ bg: o[0] })" class="rounded-md border px-2 py-1 text-[11px]" :class="brand.bg===o[0] ? 'border-brand-400 text-white' : 'border-white/10 text-slate-400 hover:text-white'">{{ o[1] }}</button></div></div>
                            <div class="flex items-center justify-between gap-2 text-xs text-slate-300"><span>Taille</span><div class="flex gap-1"><button v-for="o in [['sm','S'],['md','M'],['lg','L']]" :key="o[0]" type="button" @click="saveBrand({ size: o[0] })" class="rounded-md border px-2.5 py-1 text-[11px]" :class="brand.size===o[0] ? 'border-brand-400 text-white' : 'border-white/10 text-slate-400 hover:text-white'">{{ o[1] }}</button></div></div>
                            <label class="flex items-center justify-between text-xs text-slate-300">Inverser les couleurs du logo<button type="button" @click="saveBrand({ invert: !brand.invert })" :class="brand.invert ? 'bg-brand-500' : 'bg-white/10'" class="relative h-5 w-9 rounded-full transition"><span :class="brand.invert ? 'translate-x-4' : 'translate-x-0.5'" class="absolute top-0.5 h-4 w-4 rounded-full bg-white transition"></span></button></label>
                            <label class="flex items-center justify-between text-xs text-slate-300">Afficher le nom à côté du logo<button type="button" @click="saveBrand({ show_name: !brand.show_name })" :class="brand.show_name ? 'bg-brand-500' : 'bg-white/10'" class="relative h-5 w-9 rounded-full transition"><span :class="brand.show_name ? 'translate-x-4' : 'translate-x-0.5'" class="absolute top-0.5 h-4 w-4 rounded-full bg-white transition"></span></button></label>
                        </div>
                    </div>
                    <div>
                        <p class="font-display font-bold text-white">Couleur principale</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button v-for="c in palette" :key="c" @click="setAccent(c)" :style="{ background: c }" class="h-8 w-8 rounded-full ring-2 transition" :class="st.accent?.toLowerCase()===c ? 'ring-white' : 'ring-transparent hover:ring-white/40'"></button>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <input type="color" :value="st.accent" @input="setAccent($event.target.value)" class="h-9 w-12 cursor-pointer rounded-lg border border-white/10 bg-transparent" />
                            <input class="fld flex-1 font-mono" :value="st.accent" @change="setAccent($event.target.value)" placeholder="#4f46e5" />
                        </div>
                    </div>
                    <div>
                        <p class="font-display font-bold text-white">Ambiance</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button @click="setTheme('light')" class="rounded-xl border px-3 py-3 text-left text-sm transition" :class="st.theme==='light' ? 'border-brand-400 bg-brand-500/10 text-white' : 'border-white/10 text-slate-300 hover:border-white/25'">☀️ Clair<span class="block text-[11px] text-slate-500">Lumineux, aéré</span></button>
                            <button @click="setTheme('dark')" class="rounded-xl border px-3 py-3 text-left text-sm transition" :class="st.theme==='dark' ? 'border-brand-400 bg-brand-500/10 text-white' : 'border-white/10 text-slate-300 hover:border-white/25'">🌙 Sombre<span class="block text-[11px] text-slate-500">Premium, contrasté</span></button>
                        </div>
                    </div>
                    <div>
                        <p class="font-display font-bold text-white">Mise en page du haut de page</p>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <button v-for="h in [['editorial','Éditorial'],['split','Deux colonnes'],['center','Centré'],['bright','Lumineux'],['poster','Affiche']]" :key="h[0]" @click="setHeroStyle(h[0])" class="rounded-xl border px-2 py-2.5 text-xs transition" :class="st.hero_style===h[0] ? 'border-brand-400 bg-brand-500/10 text-white' : 'border-white/10 text-slate-300 hover:border-white/25'">{{ h[1] }}</button>
                        </div>
                    </div>
                    <div>
                        <p class="font-display font-bold text-white">Police des titres</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button v-for="f in fonts" :key="f" @click="setFont(f)" class="rounded-xl border px-3 py-2.5 text-left text-sm transition" :class="(st.font||st.defaults?.font||'Space Grotesk')===f ? 'border-brand-400 bg-brand-500/10 text-white' : 'border-white/10 text-slate-300 hover:border-white/25'" :style="{ fontFamily: `'${f}', sans-serif` }">{{ f }}</button>
                        </div>
                    </div>
                </div>

                <!-- ══ MODULES ══ -->
                <div v-else-if="st && tab==='modules'" class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                    <div>
                        <p class="font-display font-bold text-white">Modules du site</p>
                        <p class="mt-1 text-xs text-slate-500">Activez ce qui vous est utile : tout est inclus, sans supplément.</p>
                        <div class="mt-3 space-y-2">
                            <div v-for="m in QUICK_MODULES" :key="m[0]" class="rounded-xl border border-white/[0.08]">
                                <label class="flex items-center justify-between px-3 py-2 text-sm text-slate-300">{{ m[1] }}
                                    <button type="button" @click="toggleModule(m[0])" :class="moduleOn(m[0]) ? 'bg-brand-500' : 'bg-white/10'" class="relative h-6 w-11 rounded-full transition"><span :class="moduleOn(m[0]) ? 'translate-x-5' : 'translate-x-0.5'" class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition"></span></button>
                                </label>
                                <!-- Réglages de l'assistant IA -->
                                <div v-if="m[0]==='bot' && moduleOn('bot')" class="space-y-3 border-t border-white/[0.06] bg-white/[0.02] px-3 pb-3 pt-2">
                                    <p class="text-[11px] text-slate-500">L'assistant connaît déjà tout votre site : textes, services, horaires, FAQ, carte, chambres, avis, pages. Ajoutez ici ce qu'il ne peut pas deviner.</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="lbl">Nom<input class="fld" :value="bot.name" @change="setBot('name',$event.target.value)" /></label>
                                        <label class="lbl">Ton<select class="fld" :value="bot.tone" @change="setBot('tone',$event.target.value)"><option value="chaleureux">Chaleureux</option><option value="professionnel">Professionnel</option><option value="décontracté">Décontracté</option><option value="premium">Premium</option></select></label>
                                    </div>
                                    <label class="lbl">Message d'accueil<input class="fld" :value="bot.welcome" @change="setBot('welcome',$event.target.value)" /></label>
                                    <label class="lbl">Infos pratiques (parking, accès, animaux, moyens de paiement, allergènes…)<textarea class="fld" rows="3" :value="bot.practical" placeholder="Ex : parking gratuit derrière le bâtiment · chiens acceptés en terrasse · CB et tickets resto" @change="setBot('practical',$event.target.value)"></textarea></label>
                                    <label class="lbl">Questions / réponses à connaître<textarea class="fld" rows="3" :value="bot.faq" placeholder="Q : Faites-vous des menus de groupe ? R : Oui, à partir de 10 personnes, sur demande." @change="setBot('faq',$event.target.value)"></textarea></label>
                                    <label class="lbl">Consignes (ce qu'il doit faire ou éviter)<textarea class="fld" rows="2" :value="bot.instructions" placeholder="Ex : toujours proposer la réservation en ligne · ne jamais annoncer de promotion · tutoyer" @change="setBot('instructions',$event.target.value)"></textarea></label>
                                    <label class="flex items-center gap-2 text-xs text-slate-300"><input type="checkbox" :checked="bot.lead_capture!==false" class="h-4 w-4 rounded border-white/20 bg-white/5" @change="setBot('lead_capture',$event.target.checked)" /> Proposer de laisser ses coordonnées aux visiteurs intéressés</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/[0.08] p-3 text-xs text-slate-400">
                        <p class="font-semibold text-white">Réglages avancés</p>
                        <p class="mt-1">Réservation de table et créneaux, chambres et tarifs, carte, paiement en ligne, WhatsApp, mentions légales, statistiques.</p>
                        <Link v-if="authUser" :href="route('modules.index')" class="btn-brand mt-3 !px-4 !py-2 text-xs">Ouvrir l'espace Modules →</Link>
                        <p v-else class="mt-2 text-amber-200/90">Disponibles dans votre espace après la mise en ligne.</p>
                    </div>
                </div>
            </aside>

            <!-- ─── APERÇU ─── -->
            <section class="flex min-h-0 flex-col bg-ink-950 pb-16 lg:pb-0">
                <div class="flex items-center gap-2 border-b border-white/[0.06] px-3 py-2 lg:px-4">
                    <div class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto md:hidden">
                        <button @click="curPage=''" class="ptab !py-1 text-xs" :class="curPage==='' && 'on'">Accueil</button>
                        <button v-for="p in pages" :key="p.slug" @click="curPage=p.slug" class="ptab !py-1 text-xs" :class="curPage===p.slug && 'on'">{{ p.title }}</button>
                        <button @click="modal='newpage'" class="ptab !py-1 text-xs text-brand-300">+ Page</button>
                    </div>
                    <span class="hidden h-2.5 w-2.5 rounded-full bg-rose-400/70 md:inline"></span><span class="hidden h-2.5 w-2.5 rounded-full bg-amber-400/70 md:inline"></span><span class="hidden h-2.5 w-2.5 rounded-full bg-emerald-400/70 md:inline"></span>
                    <span class="ml-3 hidden truncate rounded-md bg-white/5 px-3 py-1 text-xs text-slate-400 md:inline">{{ site.slug }}.joow.fr{{ curPage ? '/' + curPage + '/' : '' }}</span>
                    <span class="ml-auto hidden text-xs text-slate-500 xl:inline">Cliquez un texte ou une image pour le modifier · Entrée pour valider · Échap pour annuler</span>
                </div>
                <div class="min-h-0 flex-1 p-0 lg:p-3" :class="device==='mobile' && 'lg:flex lg:justify-center'">
                    <iframe ref="frame" :key="version + curPage" :src="previewUrl" :class="device==='mobile' ? 'lg:w-[390px] lg:rounded-[2rem]' : 'w-full'" class="h-full w-full bg-white lg:rounded-xl lg:border lg:border-white/5"></iframe>
                </div>
            </section>

            <!-- ─── BARRE D'OUTILS MOBILE ─── -->
            <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-white/[0.08] bg-ink-900/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
                <button v-for="t in [['ia','sparkles','IA'],['contenu','pencil','Contenu'],['pages','file','Pages'],['style','palette','Style'],['modules','blocks','Modules']]" :key="t[0]" @click="openTab(t[0])" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-semibold transition" :class="sheet && tab===t[0] ? 'text-white' : 'text-slate-400'">
                    <Icon :name="t[1]" :size="20" />{{ t[2] }}
                    <span v-if="t[0]==='ia' && st?.credits" class="absolute -mt-1 ml-8 rounded-full bg-brand-500 px-1.5 text-[9px] text-white">{{ st.credits.balance }}</span>
                </button>
            </nav>
        </div>

        <!-- ═══ MODALES ═══ -->
        <Transition name="fade">
            <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur" @click="modal=null">
                <div class="glass max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-ink-800 p-5 sm:p-6" @click.stop>
                    <!-- Nouvelle page -->
                    <template v-if="modal==='newpage'">
                        <div class="mb-4 flex items-center justify-between"><h3 class="font-display text-lg font-bold text-white">Nouvelle page</h3><button @click="modal=null" class="text-slate-400 hover:text-white">✕</button></div>
                        <label class="lbl">Nom de la page<input v-model="newPage.title" class="fld" placeholder="Ex : Nos réalisations, Tarifs, L'équipe, Nos prestations…" @keydown.enter="createPage" /></label>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button @click="newPage.mode='ia'" class="rounded-xl border p-3 text-left text-sm transition" :class="newPage.mode==='ia' ? 'border-brand-400 bg-brand-500/10 text-white' : 'border-white/10 text-slate-300'"><span class="flex items-center gap-1.5"><Icon name="sparkles" :size="15" />Rédigée par l'IA</span><span class="block text-[11px] text-slate-500">Contenu complet, mis en page, adapté à votre établissement</span></button>
                            <button @click="newPage.mode='blank'" class="rounded-xl border p-3 text-left text-sm transition" :class="newPage.mode==='blank' ? 'border-brand-400 bg-brand-500/10 text-white' : 'border-white/10 text-slate-300'"><span class="flex items-center gap-1.5"><Icon name="file" :size="15" />Page vide</span><span class="block text-[11px] text-slate-500">Vous ajoutez vos blocs vous-même</span></button>
                        </div>
                        <label v-if="newPage.mode==='ia'" class="lbl mt-3">Précisions pour l'IA (optionnel)<textarea v-model="newPage.brief" class="fld" rows="2" placeholder="Ex : mettre en avant nos 3 dernières rénovations, avec avant/après et témoignages"></textarea></label>
                        <button @click="createPage" :disabled="!newPage.title.trim()" class="btn-brand mt-4 w-full disabled:opacity-50">{{ newPage.mode==='ia' ? 'Créer avec l\'IA →' : 'Créer la page' }}</button>
                    </template>

                    <!-- Type de bloc -->
                    <template v-else-if="modal==='blocktype'">
                        <div class="mb-4 flex items-center justify-between"><h3 class="font-display text-lg font-bold text-white">Ajouter un bloc</h3><button @click="modal=null" class="text-slate-400 hover:text-white">✕</button></div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button v-for="b in blockDefs" :key="b.type" @click="addBlock(b.type)" class="rounded-xl border border-white/10 p-3 text-left transition hover:border-brand-400 hover:bg-brand-500/10"><p class="text-sm font-semibold text-white">{{ b.label }}</p><p class="mt-0.5 text-[11px] text-slate-500">{{ b.desc }}</p></button>
                        </div>
                    </template>

                    <!-- Retrouver mon site (invité) -->
                    <template v-else-if="modal==='google'">
                        <h3 class="font-display text-xl font-bold text-white">Rattacher ma fiche Google</h3>
                        <template v-if="!gsearch.done">
                            <p class="mt-2 text-sm text-slate-400">Cherchez votre établissement tel qu'il apparaît sur Google Maps. Votre note, vos avis, vos photos et vos horaires seront ajoutés au site ; vos textes sont conservés.</p>
                            <input v-model="gsearch.q" @input="searchGoogle" type="text" class="field mt-4" placeholder="Nom de l'établissement + ville" />
                            <p v-if="gsearch.searching" class="mt-2 text-xs text-slate-500">Recherche…</p>
                            <div v-else-if="gsearch.results.length" class="mt-3 space-y-2">
                                <button v-for="r in gsearch.results" :key="r.place_id" :disabled="gsearch.attaching" @click="attachGoogle(r)" class="flex w-full items-center gap-3 rounded-xl border border-white/10 px-4 py-3 text-left transition hover:border-sky-400/50 disabled:opacity-50">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/5 text-sky-300"><Icon name="pin" :size="16" /></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-white">{{ r.main }}</span><span class="block truncate text-xs text-slate-400">{{ r.secondary }}</span></span>
                                    <span class="shrink-0 text-xs font-semibold text-sky-300">{{ gsearch.attaching ? '…' : 'Rattacher →' }}</span>
                                </button>
                            </div>
                            <p v-else-if="gsearch.q.trim().length >= 3" class="mt-3 text-xs text-slate-500">Aucune fiche trouvée. Essayez « nom + ville ».</p>
                            <p v-if="gsearch.error" class="mt-3 text-xs text-rose-300">{{ gsearch.error }}</p>
                        </template>
                        <div v-else class="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-500/[0.06] p-4 text-sm">
                            <p class="font-semibold text-emerald-200">✓ Fiche rattachée : {{ gsearch.done.name }}</p>
                            <p class="mt-1 text-slate-300">{{ gsearch.done.rating ? `Note ${gsearch.done.rating}/5 · ${gsearch.done.reviews_count} avis` : 'Pas encore d\'avis' }} · {{ gsearch.done.photos }} photo{{ gsearch.done.photos > 1 ? 's' : '' }} ajoutée{{ gsearch.done.photos > 1 ? 's' : '' }}. Le site a été mis à jour.</p>
                            <button @click="modal=null" class="btn-brand mt-3 !px-4 !py-2 text-xs">Fermer</button>
                        </div>
                    </template>
                    <template v-else-if="modal==='claim'">
                        <div class="mb-4 flex items-center justify-between"><h3 class="font-display text-lg font-bold text-white">Retrouver mon site plus tard</h3><button @click="modal=null" class="text-slate-400 hover:text-white">✕</button></div>
                        <p class="text-sm text-slate-400">Indiquez votre email : on l'associe à ce site et vous obtenez un lien d'édition privé. Vous pourrez aussi créer un compte gratuit pour tout retrouver dans votre espace.</p>
                        <div class="mt-4 flex gap-2"><input v-model="claim.email" type="email" class="field flex-1" placeholder="vous@exemple.fr" @keydown.enter="saveClaim" /><button @click="saveClaim" :disabled="claim.sending" class="btn-brand">{{ claim.sending ? '…' : 'Valider' }}</button></div>
                        <div v-if="claim.done" class="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-500/[0.06] p-4 text-sm">
                            <p class="font-semibold text-emerald-300">✓ Email enregistré</p>
                            <p class="mt-1 text-slate-300">Votre lien d'édition privé (gardez-le) :</p>
                            <div class="mt-2 flex gap-2"><input readonly :value="claim.link" class="field flex-1 text-xs" /><button @click="copy(claim.link)" class="rounded-xl border border-white/15 px-3 text-xs font-semibold text-white">Copier</button></div>
                            <Link :href="route('register')" class="mt-3 inline-block text-xs font-semibold text-brand-400">Créer mon compte gratuit →</Link>
                        </div>
                    </template>

                    <!-- Crédits IA -->
                    <template v-else-if="modal==='credits'">
                        <div class="mb-2 flex items-center justify-between"><h3 class="font-display text-xl font-bold text-white">Crédits IA</h3><button @click="modal=null" class="text-slate-400 hover:text-white">✕</button></div>
                        <div class="flex flex-wrap items-end justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                            <div><p class="text-xs uppercase tracking-wider text-slate-500">Solde</p><p class="font-display text-4xl font-bold text-white">{{ st.credits.balance }}<span class="ml-2 text-base font-medium text-slate-400">crédit{{ st.credits.balance > 1 ? 's' : '' }}</span></p></div>
                            <div class="text-right text-xs text-slate-400">
                                <p v-if="st.credits.plan==='pro'">Formule Pro : {{ st.credits.quota }} crédits / mois<span v-if="st.credits.reset_at"> · renouvelés le {{ st.credits.reset_at }}</span></p>
                                <p v-else-if="st.credits.plan==='liberte'">Formule Liberté : {{ st.credits.quota }} crédits offerts</p>
                                <p v-else-if="trial">Essai gratuit : vos crédits d'aperçu, puis {{ trial.quota }} / mois dès la souscription</p>
                                <p v-else>Essai gratuit : {{ st.credits.quota }} crédits offerts</p>
                                <p v-if="st.credits.wallet">dont {{ st.credits.wallet }} achetés (sans expiration)</p>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-slate-400">1 crédit par action de l'assistant, 2 pour la création d'une page. Les questions sans modification et toutes les modifications manuelles sont gratuites. Annuler une action rend le crédit.</p>
                        <template v-if="trial">
                            <div class="mt-4 rounded-2xl border border-emerald-400/40 bg-emerald-500/[0.08] p-5">
                                <p class="font-semibold text-white">Essai gratuit en cours · encore {{ trial.trial_days_left }} jour{{ trial.trial_days_left > 1 ? 's' : '' }}</p>
                                <p class="mt-1 text-sm text-slate-300">À la fin de l'essai (le {{ trial.trial_ends_at }}), votre abonnement Pro démarre et vous recevez {{ trial.quota }} crédits IA par mois. Besoin de l'IA tout de suite ? Souscrivez dès maintenant : le premier mois est prélevé aujourd'hui et les {{ trial.quota }} crédits sont débloqués immédiatement.</p>
                                <button @click="subscribeNow" :disabled="subscribing" class="btn-brand mt-3 text-sm disabled:opacity-50">{{ subscribing ? '…' : `✦ Souscrire maintenant · ${trial.price} € HT` }}</button>
                            </div>
                        </template>
                        <template v-else-if="!paid">
                            <div class="mt-4 rounded-2xl border border-brand-500/40 bg-brand-500/[0.08] p-5">
                                <p class="font-semibold text-white">{{ plans.pro.trial > 0 ? 'Mettez votre site en ligne : 100 crédits inclus' : 'Souscrivez pour continuer avec l\'IA : 100 crédits inclus' }}</p>
                                <p class="mt-1 text-sm text-slate-300">Formule Pro : 100 crédits renouvelés chaque mois, site en ligne immédiatement. Formule Liberté : 100 crédits offerts, puis recharge à volonté.</p>
                                <button @click="modal='publish'" class="btn-brand mt-3 text-sm">{{ plans.pro.trial > 0 ? '🚀 Mettre en ligne' : '✦ Souscrire et mettre en ligne' }}</button>
                            </div>
                        </template>
                        <template v-else>
                            <p class="mt-5 text-xs font-bold uppercase tracking-wider text-slate-500">Recharger</p>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <div v-for="pk in st.credits.packs" :key="pk.key" class="relative rounded-2xl border p-4" :class="pk.best ? 'border-brand-500/50 bg-brand-500/[0.06]' : 'border-white/10 bg-white/[0.03]'">
                                    <span v-if="pk.best" class="absolute right-3 top-3 rounded-full bg-brand-gradient px-2 py-0.5 text-[10px] font-bold text-white">Meilleur prix</span>
                                    <p class="font-display text-2xl font-bold text-white">{{ pk.credits }} crédits</p>
                                    <p class="text-sm text-slate-400">{{ pk.label }}</p>
                                    <button @click="buyCredits(pk)" :disabled="!pk.ready || !authUser" class="mt-3 w-full rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-50" :class="pk.best ? 'btn-brand !py-2' : 'border border-white/15 text-white hover:bg-white/5'">{{ !authUser ? 'Connexion requise' : pk.ready ? 'Acheter →' : 'Bientôt disponible' }}</button>
                                </div>
                            </div>
                            <p class="mt-3 text-center text-[11px] text-slate-500">🔒 Paiement sécurisé Stripe · crédits ajoutés immédiatement</p>
                        </template>
                    </template>

                    <!-- Mise en ligne -->
                    <template v-else-if="modal==='publish'">
                        <div class="mb-2 flex items-center justify-between"><h3 class="font-display text-xl font-bold text-white">Mettre votre site en ligne</h3><button @click="modal=null" class="text-slate-400 hover:text-white">✕</button></div>
                        <p class="text-sm text-slate-400">Votre site reste modifiable à volonté après la mise en ligne. Tous les modules sont inclus.</p>
                        <div v-if="freeDomain" class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-sm"><span class="text-emerald-300">✓</span><span class="font-bold text-white">{{ freeDomain }}</span><span class="text-emerald-200">est disponible · inclus, réservé à votre nom dès la mise en ligne</span></div>
                        <label class="lbl mt-4">Votre email (accès, factures, notifications)<input v-model="checkout.email" type="email" class="fld !py-2.5" placeholder="vous@exemple.fr" /></label>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="relative rounded-2xl border border-brand-500/50 bg-brand-500/[0.06] p-5">
                                <span class="absolute right-3 top-3 rounded-full bg-brand-gradient px-2.5 py-0.5 text-[10px] font-bold text-white">Recommandé</span>
                                <p class="font-display text-lg font-bold text-white">Pro</p>
                                <p class="mt-1 font-display text-3xl font-bold text-white">{{ plans.pro.price }} €<span class="text-sm font-medium text-slate-400"> HT/mois</span></p>
                                <p class="mt-1 text-xs font-semibold text-emerald-300">{{ plans.pro.trial > 0 ? plans.pro.trial + ' jours d\'essai gratuit · sans engagement' : 'Sans engagement · résiliable à tout moment' }}</p>
                                <ul class="mt-3 space-y-1 text-xs text-slate-300"><li>✓ Domaine .fr / .com + hébergement + SSL</li><li>✓ Tous les modules · Studio + IA illimités</li><li>✓ Emails & SMS · statistiques · SEO</li></ul>
                                <button @click="startCheckout('pro')" :disabled="!plans.pro.ready" class="btn-brand mt-4 w-full text-sm disabled:opacity-50">{{ !plans.pro.ready ? 'Bientôt disponible' : (plans.pro.trial > 0 ? 'Commencer l\'essai gratuit →' : 'Souscrire et mettre en ligne →') }}</button>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                                <p class="font-display text-lg font-bold text-white">Liberté</p>
                                <p class="mt-1 font-display text-3xl font-bold text-white">{{ plans.liberte.price }} €<span class="text-sm font-medium text-slate-400"> HT, une fois</span></p>
                                <p class="mt-1 text-xs font-semibold text-brand-300">Votre site à vie · hébergement 1 an inclus</p>
                                <ul class="mt-3 space-y-1 text-xs text-slate-300"><li>✓ Domaine + hébergement 1re année</li><li>✓ Tous les modules · Studio + IA illimités</li><li>✓ Téléchargement du site</li></ul>
                                <button @click="startCheckout('liberte')" :disabled="!plans.liberte.ready" class="mt-4 w-full rounded-xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5 disabled:opacity-50">{{ plans.liberte.ready ? 'Paiement unique →' : 'Bientôt disponible' }}</button>
                            </div>
                        </div>
                        <p class="mt-3 text-center text-[11px] text-slate-500">🔒 Paiement sécurisé Stripe · {{ plans.pro.trial > 0 ? 'vous ne payez rien avant la fin de l\'essai' : 'facture et accès envoyés par email' }} · résiliable en un clic</p>
                    </template>
                </div>
            </div>
        </Transition>

        <!-- ═══ SÉLECTEUR D'IMAGE ═══ -->
        <Transition name="fade">
            <div v-if="picker.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur" @click="picker.open=false">
                <div class="glass max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-ink-800 p-5 sm:p-6" @click.stop>
                    <div class="mb-4 flex items-center justify-between"><h3 class="font-display text-lg font-bold text-white">Choisir une image</h3><button @click="picker.open=false" class="text-slate-400 hover:text-white">✕</button></div>
                    <div class="mb-4 flex gap-1 rounded-xl bg-white/[0.04] p-1 text-sm font-semibold">
                        <button v-for="t in [['bank','image','Banque d\'images'],['upload','upload','Importer'],['google','pin','Photos Google'],['url','link','Lien']]" :key="t[0]" @click="t[0]==='bank' ? openBank() : (picker.tab=t[0])" class="flex flex-auto items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-2 py-2" :class="picker.tab===t[0] ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'"><Icon :name="t[1]" :size="15" /><span class="hidden sm:inline">{{ t[2] }}</span></button>
                    </div>
                    <div v-if="picker.tab==='bank'">
                        <form @submit.prevent="searchBank()" class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><Icon name="search" :size="16" /></span>
                            <input v-model="bank.q" class="field w-full !pl-9" placeholder="Décrivez la photo : terrasse ensoleillée, mains qui pétrissent…" />
                        </form>
                        <div v-if="bank.suggestions.length" class="mt-2 flex flex-wrap gap-1.5">
                            <button v-for="sg in bank.suggestions" :key="sg" type="button" @click="bank.q = sg; searchBank()" class="rounded-full border border-white/10 px-2.5 py-1 text-[11px] text-slate-300 hover:border-brand-400/60 hover:text-white">{{ sg }}</button>
                        </div>
                        <div class="mt-3 grid max-h-[52vh] grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3">
                            <template v-if="bank.loading"><div v-for="i in 9" :key="i" class="h-28 animate-pulse rounded-lg bg-white/[0.06]"></div></template>
                            <button v-for="(r,i) in bank.results" v-else :key="r.url" type="button" :disabled="bank.using !== null" @click="useBankPhoto(r, i)" class="group relative h-28 overflow-hidden rounded-lg ring-2 ring-transparent transition hover:ring-brand-400">
                                <img :src="r.thumb" :alt="r.alt" class="h-full w-full object-cover" loading="lazy" />
                                <span class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/80 to-transparent px-2 pb-1 pt-4 text-left text-[10px] text-white/80 opacity-0 transition group-hover:opacity-100">{{ (r.credit || '').split(' — ')[0] }}</span>
                                <span v-if="bank.using === i" class="absolute inset-0 grid place-items-center bg-black/60 text-xs font-semibold text-white">Ajout…</span>
                            </button>
                        </div>
                        <p v-if="!bank.loading && bank.searched && !bank.results.length" class="mt-3 text-sm text-slate-500">Aucune photo trouvée. Essayez d'autres mots, par exemple ce que l'on voit sur la photo.</p>
                        <p class="mt-3 text-[11px] text-slate-500">Photos libres de droits (Unsplash, Pexels). La recherche comprend le français et tient compte de votre métier et de l'emplacement.</p>
                    </div>
                    <div v-else-if="picker.tab==='upload'">
                        <label class="flex h-40 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-white/15 text-slate-400 transition hover:border-brand-400 hover:text-white">
                            <Icon name="upload" :size="28" /><span class="mt-2 text-sm">{{ picker.uploading ? 'Import en cours…' : 'Cliquez pour importer (jpg, png, webp · 8 Mo max)' }}</span>
                            <input type="file" accept="image/*" class="hidden" :disabled="picker.uploading" @change="upload" />
                        </label>
                    </div>
                    <div v-else-if="picker.tab==='google'" class="grid max-h-[50vh] grid-cols-3 gap-2 overflow-y-auto">
                        <img v-for="(p,i) in (st?.photos||[])" :key="i" :src="p" class="h-28 w-full cursor-pointer rounded-lg object-cover ring-2 ring-transparent transition hover:ring-brand-400" @click="applyImage(p)" />
                        <p v-if="!(st?.photos||[]).length" class="col-span-3 text-sm text-slate-500">Aucune photo Google disponible.</p>
                    </div>
                    <div v-else class="flex gap-2">
                        <input v-model="picker.url" class="field flex-1" placeholder="https://…/image.jpg" />
                        <button @click="picker.url && applyImage(picker.url)" class="btn-brand">Utiliser</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.lbl { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; }
.lbl .fld { margin-top: 4px; }
.fld { width: 100%; border-radius: 10px; border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04); padding: 8px 10px; font-size: 13px; color: #e2e8f0; outline: none; }
.fld:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.3); }
select.fld option { background: #14141f; }
.ptab { white-space: nowrap; border-radius: 10px; padding: 6px 12px; font-size: 13px; font-weight: 600; color: #94a3b8; transition: all .15s; }
.ptab:hover { color: #fff; background: rgba(255,255,255,.05); }
.ptab.on { color: #fff; background: rgba(255,255,255,.1); }
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>

<style>
.logo-inline svg { height: 100%; width: auto; max-width: 100%; display: block; margin: 0 auto; }
</style>
