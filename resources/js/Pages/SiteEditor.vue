<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({ site: Object });
const slug = props.site.slug;

/* ───────────── état ───────────── */
const st = ref(null);            // état complet du site (content, business, images, booking, accent, font, modules)
const loading = ref(true);
const version = ref(Date.now());
const status = ref('idle');      // idle | saving | saved | queued | error
const tab = ref('contenu');      // contenu | style | ia
const panel = ref('hero');       // section ouverte dans le panneau
const device = ref('desktop');
const frame = ref(null);
const undoStack = ref([]);
const redoStack = ref([]);
const picker = ref({ open: false, path: '', current: '', tab: 'upload', url: '', uploading: false });

/* ───────────── utilitaires ───────────── */
const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
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
const post = (m) => frame.value?.contentWindow?.postMessage({ joow: 1, ...m }, '*');
const reload = (v) => { version.value = v || Date.now(); };
const previewUrl = computed(() => `${route('sites.editor.preview', slug)}?v=${version.value}`);

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
    try { const d = await postJson(route('sites.editor.bulk', slug), { patch }); status.value = d.queued ? 'queued' : 'saved'; if (rl) reload(d.version); }
    catch (e) { status.value = 'error'; console.error(e); }
};

/** Modifie un champ (chemin pointé) : état local, undo, aperçu, sauvegarde. */
const edit = (path, value, { reload: rl = false, fromFrame = false, record = true } = {}) => {
    if (!st.value) return;
    const prev = getPath(st.value, path);
    if (JSON.stringify(prev) === JSON.stringify(value)) return;
    setPath(st.value, path, value);
    if (record) { undoStack.value.push({ path, prev, next: value, rl }); redoStack.value = []; }
    if (!fromFrame && !rl && typeof value === 'string') post({ type: 'setText', path, value });
    queueSave({ [path]: value }, { reload: rl });
};
const undo = () => { const u = undoStack.value.pop(); if (!u) return; redoStack.value.push(u); edit(u.path, u.prev, { reload: u.rl || typeof u.prev !== 'string', record: false }); };
const redo = () => { const u = redoStack.value.pop(); if (!u) return; undoStack.value.push(u); edit(u.path, u.next, { reload: u.rl || typeof u.next !== 'string', record: false }); };

/* ───────────── pont iframe ───────────── */
const onMessage = (e) => {
    const m = e.data || {}; if (!m.joow) return;
    if (m.type === 'ready') post({ type: 'setAccent', value: st.value?.accent });
    if (m.type === 'edit') edit(m.path, m.value, { fromFrame: true });
    if (m.type === 'image') openPicker(m.path, m.current);
    if (m.type === 'section') { panel.value = m.id; tab.value = 'contenu'; }
    if (m.type === 'focus') { const sec = m.path.startsWith('booking') ? 'booking' : (m.path.startsWith('business') ? 'contact' : sectionOfPath(m.path)); if (sec) panel.value = sec; }
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
    try { const r = await fetch(route('sites.editor.state', slug), { headers: headers() }); st.value = await r.json(); }
    catch (e) { console.error(e); }
    finally { loading.value = false; }
};
onMounted(() => { load(); window.addEventListener('message', onMessage); window.addEventListener('keydown', onKey); });
onUnmounted(() => { window.removeEventListener('message', onMessage); window.removeEventListener('keydown', onKey); });

/* ───────────── sections ───────────── */
const SECTIONS = [
    { id: 'hero', label: 'Accueil', fixed: true },
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

/* ───────────── listes (services, FAQ, badges, stats) ───────────── */
const listSet = (path, arr) => edit(path, JSON.parse(JSON.stringify(arr)), { reload: true });
const addService = () => listSet('content.services', [...(st.value.content.services || []), { name: 'Nouveau service', desc: 'Description du service.', price: 'Sur devis' }]);
const removeAt = (path, i) => { const arr = [...(getPath(st.value, path) || [])]; arr.splice(i, 1); listSet(path, arr); };
const addFaq = () => listSet('content.faq', [...(st.value.content.faq || []), { q: 'Nouvelle question ?', a: 'Votre réponse.' }]);
const addBadge = () => listSet('content.badges', [...(st.value.content.badges || []), 'Nouveau point fort'].slice(0, 4));

/* ───────────── images ───────────── */
const openPicker = (path, current = '') => { picker.value = { open: true, path, current, tab: 'upload', url: '', uploading: false }; };
const applyImage = (url) => {
    const p = picker.value.path;
    if (p.startsWith('images.gallery.')) {
        const i = Number(p.split('.').pop()); const g = [...(st.value.images?.gallery || galleryDefault())]; g[i] = url;
        edit('images.gallery', g, { reload: true });
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

/* ───────────── style ───────────── */
const palette = ['#4f46e5', '#e11d48', '#0d9488', '#db2777', '#ea580c', '#2563eb', '#7c3aed', '#059669', '#0f172a', '#b45309'];
const fonts = ['Space Grotesk', 'Playfair Display', 'DM Serif Display', 'Sora', 'Poppins', 'Montserrat', 'Cormorant Garamond'];
const setAccent = async (c) => {
    if (!/^#[0-9a-fA-F]{6}$/.test(c)) return;
    st.value.accent = c; post({ type: 'setAccent', value: c }); status.value = 'saving';
    try { const d = await postJson(route('sites.editor.style', slug), { accent: c }); status.value = d.queued ? 'queued' : 'saved'; } catch { status.value = 'error'; }
};
const setFont = async (f) => {
    st.value.font = f; status.value = 'saving';
    try { const d = await postJson(route('sites.editor.style', slug), { font: f }); status.value = d.queued ? 'queued' : 'saved'; reload(d.version); } catch { status.value = 'error'; }
};
const toggleModule = async () => {
    const enabled = !st.value.modules.booking; st.value.modules.booking = enabled; status.value = 'saving';
    try { const d = await postJson(route('sites.editor.module', slug), { key: 'booking', enabled }); status.value = d.queued ? 'queued' : 'saved'; reload(d.version); }
    catch { st.value.modules.booking = !enabled; status.value = 'error'; }
};
const setBookingType = (t) => edit('booking.type', t || null, { reload: true });
const setHours = (txt) => edit('business.opening_hours', txt.split('\n').map((l) => l.trim()).filter(Boolean), { reload: true });

/* ───────────── constructeur de formulaire ───────────── */
const FIELD_TYPES = [['cards', 'Choix illustré (cartes)'], ['chips', 'Multi-choix (pastilles)'], ['toggle', 'Bascule (un seul choix)'], ['select', 'Liste déroulante'], ['text', 'Texte court'], ['textarea', 'Texte long'], ['date', 'Date'], ['time', 'Heure'], ['number', 'Nombre'], ['phone', 'Téléphone'], ['email', 'Email']];
const slugify = (s) => (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'champ';
const openStep = ref(0);
let formTimer = null;
const commitForm = () => { clearTimeout(formTimer); formTimer = setTimeout(() => edit('form', JSON.parse(JSON.stringify(st.value.form)), { reload: true }), 700); };
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
const restoreForm = () => { if (!confirm('Restaurer le modèle du métier ? Vos modifications du formulaire seront perdues.')) return; st.value.form = JSON.parse(JSON.stringify(st.value.form_default)); openStep.value = 0; commitForm(); };

/* ───────────── publication ───────────── */
const publishing = ref(false);
const publish = async () => {
    publishing.value = true; await flush();
    try { const d = await postJson(route('sites.editor.publish', slug), {}); status.value = d.queued ? 'queued' : 'saved'; }
    catch { status.value = 'error'; } finally { publishing.value = false; }
};

/* ───────────── assistant IA ───────────── */
const messages = ref([{ role: 'ai', text: `Bonjour 👋 Dites-moi ce que vous voulez changer sur ${props.site.name} : textes, ton, services, couleur… j'applique et l'aperçu se met à jour.` }]);
const input = ref(''); const sending = ref(false); const chatBox = ref(null);
const suggestions = ['Rends le ton plus chaleureux', 'Ajoute un service de livraison', 'Réécris l\'accroche pour attirer plus de clients', 'Ajoute 2 questions à la FAQ'];
const send = async (text) => {
    const msg = (text ?? input.value).trim(); if (!msg || sending.value) return;
    input.value = ''; messages.value.push({ role: 'user', text: msg }); messages.value.push({ role: 'ai', text: '…', pending: true });
    nextTick(() => { if (chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight; });
    sending.value = true;
    try {
        const d = await postJson(route('sites.editor.chat', slug), { message: msg });
        messages.value.pop(); messages.value.push({ role: 'ai', text: d.reply || 'C\'est fait ✅' });
        if (d.state) st.value = d.state; status.value = d.queued ? 'queued' : 'saved'; reload(d.version);
    } catch (e) { messages.value.pop(); messages.value.push({ role: 'ai', text: e?.reply || e?.message || 'Je n\'ai pas pu appliquer cette demande. Reformulez ?' }); }
    finally { sending.value = false; nextTick(() => { if (chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight; }); }
};

const regenerate = () => router.post(route('sites.editor.regenerate', slug), {}, { preserveScroll: true });
const statusLabel = computed(() => ({ idle: '', saving: 'Enregistrement…', saved: 'Enregistré ✓', queued: 'Enregistré · publication en cours…', error: 'Erreur d\'enregistrement' }[status.value]));
</script>

<template>
    <Head :title="`Studio — ${site.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="route('dashboard')" class="text-sm text-slate-400 hover:text-white">← Mes sites</Link>
                    <h1 class="truncate font-display text-2xl font-bold text-white">{{ site.name }} <span class="ml-2 rounded-full bg-brand-gradient px-2.5 py-0.5 align-middle text-xs font-bold text-white">Studio</span></h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs" :class="status==='error' ? 'text-rose-400' : 'text-slate-400'">{{ statusLabel }}</span>
                    <div class="glass flex rounded-xl p-1">
                        <button @click="undo" :disabled="!undoStack.length" class="rounded-lg px-2.5 py-1.5 text-sm text-slate-300 disabled:opacity-30" title="Annuler (Ctrl+Z)">↶</button>
                        <button @click="redo" :disabled="!redoStack.length" class="rounded-lg px-2.5 py-1.5 text-sm text-slate-300 disabled:opacity-30" title="Rétablir (Ctrl+Y)">↷</button>
                    </div>
                    <div class="glass flex rounded-xl p-1">
                        <button @click="device='desktop'" :class="device==='desktop' ? 'bg-white/10 text-white' : 'text-slate-400'" class="rounded-lg px-3 py-1.5 text-sm">🖥️</button>
                        <button @click="device='mobile'" :class="device==='mobile' ? 'bg-white/10 text-white' : 'text-slate-400'" class="rounded-lg px-3 py-1.5 text-sm">📱</button>
                    </div>
                    <a :href="`${site.live_url}?v=${version}`" target="_blank" rel="noopener" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25">Voir en ligne ↗</a>
                    <button @click="publish" :disabled="publishing" class="btn-brand text-sm">{{ publishing ? 'Publication…' : 'Publier' }}</button>
                </div>
            </div>
        </template>

        <div v-if="!site.editable" class="glass mb-6 rounded-2xl border-amber-400/20 bg-amber-500/[0.06] p-5">
            <p class="font-semibold text-white">Édition à activer</p>
            <p class="mt-1 text-sm text-slate-400">Ce site a été importé sans contenu structuré. Régénérez-le depuis sa fiche Google pour débloquer le Studio (édition en place, images, IA).</p>
            <button v-if="site.has_place" @click="regenerate" class="btn-brand mt-4 text-sm">Régénérer avec l'IA</button>
        </div>

        <div class="grid gap-5 xl:grid-cols-[380px,1fr]">
            <!-- ═══ PANNEAU ═══ -->
            <div class="flex flex-col gap-4">
                <div class="glass flex rounded-xl p-1 text-sm font-semibold">
                    <button v-for="t in [['contenu','✏️ Contenu'],['style','🎨 Style'],['ia','✨ IA']]" :key="t[0]" @click="tab=t[0]" class="flex-1 rounded-lg px-3 py-2 transition" :class="tab===t[0] ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'">{{ t[1] }}</button>
                </div>

                <div v-if="loading" class="glass rounded-2xl p-6 text-sm text-slate-400">Chargement…</div>

                <!-- CONTENU -->
                <div v-else-if="st && tab==='contenu'" class="glass max-h-[74vh] overflow-y-auto rounded-2xl">
                    <p class="px-5 pt-4 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Sections</p>
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
                            <!-- HERO -->
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

                            <!-- SERVICES -->
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

                            <!-- GALERIE -->
                            <template v-else-if="s.id==='gallery'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.gallery_title || 'En images'" @change="edit('content.gallery_title',$event.target.value)" /></label>
                                <div class="grid grid-cols-3 gap-2">
                                    <div v-for="(g,i) in galleryList" :key="i" class="group relative"><img :src="g" class="h-16 w-full rounded-lg object-cover" @click="openPicker(`images.gallery.${i}`, g)" /><button @click="removeGallery(i)" class="absolute right-1 top-1 hidden rounded bg-black/70 px-1 text-xs text-white group-hover:block">✕</button></div>
                                    <button @click="openPicker(`images.gallery.${galleryList.length}`)" class="grid h-16 place-items-center rounded-lg border border-dashed border-white/20 text-xs text-slate-400 hover:border-brand-400 hover:text-white">+ Photo</button>
                                </div>
                            </template>

                            <!-- À PROPOS -->
                            <template v-else-if="s.id==='about'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.about_title || 'Votre partenaire de confiance'" @change="edit('content.about_title',$event.target.value)" /></label>
                                <label class="lbl">Paragraphe 1<textarea class="fld" rows="3" :value="st.content.about_p1 || ''" @change="edit('content.about_p1',$event.target.value)"></textarea></label>
                                <label class="lbl">Paragraphe 2<textarea class="fld" rows="3" :value="st.content.about_p2 || ''" @change="edit('content.about_p2',$event.target.value)"></textarea></label>
                                <button @click="openPicker('images.about')" class="flex w-full items-center gap-3 rounded-lg bg-white/[0.04] p-2 text-left text-sm text-slate-200 hover:bg-white/[0.07]"><img :src="st.images?.about || st.photos?.[1] || st.photos?.[0]" class="h-12 w-16 rounded object-cover" /> Changer l'image</button>
                            </template>

                            <!-- AVIS -->
                            <template v-else-if="s.id==='reviews'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.reviews_title || 'Ils nous recommandent'" @change="edit('content.reviews_title',$event.target.value)" /></label>
                                <p class="text-xs text-slate-500">Les avis proviennent de votre fiche Google ({{ st.business.reviews_count || 0 }} avis, note {{ st.business.rating || '—' }}).</p>
                            </template>

                            <!-- FAQ -->
                            <template v-else-if="s.id==='faq'">
                                <label class="lbl">Titre<input class="fld" :value="st.content.faq_title || 'Questions fréquentes'" @change="edit('content.faq_title',$event.target.value)" /></label>
                                <div v-for="(f,i) in (st.content.faq||[])" :key="i" class="rounded-xl border border-white/[0.06] p-3">
                                    <div class="flex gap-2"><input class="fld flex-1 font-semibold" :value="f.q" @change="edit(`content.faq.${i}.q`,$event.target.value)" /><button @click="removeAt('content.faq',i)" class="text-slate-500 hover:text-rose-300">✕</button></div>
                                    <textarea class="fld mt-1.5" rows="2" :value="f.a" @change="edit(`content.faq.${i}.a`,$event.target.value)"></textarea>
                                </div>
                                <button @click="addFaq" class="text-xs font-semibold text-brand-400">+ Ajouter une question</button>
                            </template>

                            <!-- RÉSERVATION -->
                            <template v-else-if="s.id==='booking'">
                                <label class="flex items-center justify-between text-sm text-slate-300">Module activé
                                    <button type="button" @click="toggleModule" :class="st.modules.booking ? 'bg-brand-500' : 'bg-white/10'" class="relative h-6 w-11 rounded-full transition"><span :class="st.modules.booking ? 'translate-x-5' : 'translate-x-0.5'" class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition"></span></button>
                                </label>
                                <label class="lbl">Titre<input class="fld" :value="st.booking?.title || ''" placeholder="Automatique" @change="edit('booking.title',$event.target.value)" /></label>
                                <label class="lbl">Sous-titre<input class="fld" :value="st.booking?.sub || ''" placeholder="Automatique" @change="edit('booking.sub',$event.target.value)" /></label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="lbl">Argument 1<input class="fld" :value="st.booking?.point1 || ''" placeholder="Réponse rapide" @change="edit('booking.point1',$event.target.value)" /></label>
                                    <label class="lbl">Argument 2<input class="fld" :value="st.booking?.point2 || ''" placeholder="Confidentiel" @change="edit('booking.point2',$event.target.value)" /></label>
                                </div>

                                <!-- ── Constructeur de formulaire ── -->
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
                                                    <p class="mt-1 text-[10px] text-slate-500">{{ f.type==='cards' ? 'Format : Libellé | description courte | icône Font Awesome (ex. fa-utensils)' : 'Une option par ligne' }}</p>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                <button v-for="t in [['cards','+ Cartes'],['chips','+ Pastilles'],['toggle','+ Bascule'],['text','+ Texte'],['date','+ Date'],['number','+ Nombre']]" :key="t[0]" @click="addField(i,t[0])" class="rounded-md border border-white/10 px-2 py-1 text-[11px] text-slate-300 hover:border-brand-400 hover:text-white">{{ t[1] }}</button>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="addStep" class="mt-2 w-full rounded-lg border border-dashed border-white/15 py-2 text-xs font-semibold text-slate-300 hover:border-brand-400 hover:text-white">+ Ajouter une étape</button>
                                </div>
                                <p class="text-xs text-slate-500">Les demandes arrivent dans <Link :href="route('leads.index')" class="text-brand-400">Demandes</Link> et par email, avec toutes les réponses.</p>
                            </template>

                            <!-- PARCOURS -->
                            <template v-else-if="s.id==='process'">
                                <label class="lbl">Surtitre<input class="fld" :value="st.content.process_tag || 'Comment ça se passe'" @change="edit('content.process_tag',$event.target.value)" /></label>
                                <label class="lbl">Titre<input class="fld" :value="st.content.process_title || 'Simple, rapide, sans surprise'" @change="edit('content.process_title',$event.target.value)" /></label>
                                <p class="text-xs text-slate-500">3 étapes rassurantes. Laissez vide pour utiliser les étapes proposées pour votre métier.</p>
                                <div v-for="i in 3" :key="i" class="rounded-xl border border-white/[0.06] p-3">
                                    <input class="fld font-semibold" :value="st.content.process?.[i-1]?.title || ''" :placeholder="`Étape ${i}`" @change="edit(`content.process.${i-1}.title`,$event.target.value,{reload:true})" />
                                    <input class="fld mt-1.5" :value="st.content.process?.[i-1]?.desc || ''" placeholder="Une phrase" @change="edit(`content.process.${i-1}.desc`,$event.target.value,{reload:true})" />
                                </div>
                            </template>

                            <!-- APPEL À L'ACTION -->
                            <template v-else-if="s.id==='cta'">
                                <label class="lbl">Surtitre<input class="fld" :value="st.content.cta_band_tag || 'On vous attend'" @change="edit('content.cta_band_tag',$event.target.value)" /></label>
                                <label class="lbl">Titre<textarea class="fld" rows="2" :value="st.content.cta_band_title || st.content.cta_text || ''" @change="edit('content.cta_band_title',$event.target.value)"></textarea></label>
                                <p class="text-xs text-slate-500">Bande animée avant le contact, avec votre bouton principal et votre téléphone.</p>
                            </template>

                            <!-- CONTACT -->
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

                <!-- STYLE -->
                <div v-else-if="st && tab==='style'" class="glass space-y-5 rounded-2xl p-5">
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
                        <p class="font-display font-bold text-white">Police des titres</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button v-for="f in fonts" :key="f" @click="setFont(f)" class="rounded-xl border px-3 py-2.5 text-left text-sm transition" :class="(st.font||st.defaults?.font||'Space Grotesk')===f ? 'border-brand-400 bg-brand-500/10 text-white' : 'border-white/10 text-slate-300 hover:border-white/25'" :style="{ fontFamily: `'${f}', sans-serif` }">{{ f }}</button>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Les changements de style s'appliquent à tout le site instantanément.</p>
                </div>

                <!-- IA -->
                <div v-else-if="st && tab==='ia'" class="glass flex h-[74vh] flex-col rounded-2xl">
                    <div class="border-b border-white/[0.06] px-5 py-4"><p class="font-display font-bold text-white">✨ Assistant IA</p><p class="text-xs text-slate-500">Décrivez vos changements, ils s'appliquent en direct.</p></div>
                    <div ref="chatBox" class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                        <div v-for="(m,i) in messages" :key="i" class="flex" :class="m.role==='user' ? 'justify-end' : 'justify-start'">
                            <div :class="m.role==='user' ? 'bg-brand-gradient text-white' : 'bg-white/[0.05] text-slate-200'" class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm">
                                <span v-if="m.pending" class="inline-flex gap-1"><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style="animation-delay:.15s"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style="animation-delay:.3s"></span></span>
                                <span v-else>{{ m.text }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="messages.length<=1" class="flex flex-wrap gap-2 px-5 pb-2"><button v-for="s in suggestions" :key="s" @click="send(s)" class="rounded-full border border-white/10 px-3 py-1.5 text-xs text-slate-300 transition hover:border-white/25 hover:text-white">{{ s }}</button></div>
                    <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-white/[0.06] p-3">
                        <input v-model="input" :disabled="sending || !site.editable" type="text" placeholder="Ex : ajoute nos horaires du dimanche…" class="field flex-1 text-sm" />
                        <button type="submit" :disabled="sending || !site.editable" class="btn-brand shrink-0 px-4 py-2.5 text-sm">{{ sending ? '…' : '↑' }}</button>
                    </form>
                </div>
            </div>

            <!-- ═══ APERÇU LIVE (same-origin, édition en place) ═══ -->
            <div class="glass overflow-hidden rounded-2xl">
                <div class="flex items-center gap-2 border-b border-white/[0.06] px-4 py-3">
                    <span class="h-3 w-3 rounded-full bg-rose-400/70"></span><span class="h-3 w-3 rounded-full bg-amber-400/70"></span><span class="h-3 w-3 rounded-full bg-emerald-400/70"></span>
                    <span class="ml-3 truncate rounded-md bg-white/5 px-3 py-1 text-xs text-slate-400">{{ site.slug }}.joow.fr</span>
                    <span class="ml-auto text-xs text-slate-500">Cliquez pour éditer · Entrée pour valider · Échap pour annuler</span>
                </div>
                <div class="bg-white/[0.02] p-3" :class="device==='mobile' && 'flex justify-center'">
                    <iframe ref="frame" :key="version" :src="previewUrl" :class="device==='mobile' ? 'w-[390px] rounded-[2rem]' : 'w-full'" class="h-[78vh] rounded-xl border border-white/5 bg-white"></iframe>
                </div>
            </div>
        </div>

        <!-- ═══ SÉLECTEUR D'IMAGE ═══ -->
        <Transition name="fade">
            <div v-if="picker.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur" @click="picker.open=false">
                <div class="glass w-full max-w-2xl rounded-2xl bg-ink-800 p-6" @click.stop>
                    <div class="mb-4 flex items-center justify-between"><h3 class="font-display text-lg font-bold text-white">Choisir une image</h3><button @click="picker.open=false" class="text-slate-400 hover:text-white">✕</button></div>
                    <div class="mb-4 flex gap-1 rounded-xl bg-white/[0.04] p-1 text-sm font-semibold">
                        <button v-for="t in [['upload','⬆️ Importer'],['google','📍 Photos Google'],['url','🔗 Lien']]" :key="t[0]" @click="picker.tab=t[0]" class="flex-1 rounded-lg px-3 py-2" :class="picker.tab===t[0] ? 'bg-white/10 text-white' : 'text-slate-400'">{{ t[1] }}</button>
                    </div>
                    <div v-if="picker.tab==='upload'">
                        <label class="flex h-40 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-white/15 text-slate-400 transition hover:border-brand-400 hover:text-white">
                            <span class="text-3xl">🖼️</span><span class="mt-2 text-sm">{{ picker.uploading ? 'Import en cours…' : 'Cliquez pour importer (jpg, png, webp · 8 Mo max)' }}</span>
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
    </AuthenticatedLayout>
</template>

<style scoped>
.lbl { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; }
.lbl .fld { margin-top: 4px; }
.fld { width: 100%; border-radius: 10px; border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04); padding: 8px 10px; font-size: 13px; color: #e2e8f0; outline: none; }
.fld:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.3); }
select.fld option { background: #14141f; }
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
