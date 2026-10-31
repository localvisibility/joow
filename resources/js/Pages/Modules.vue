<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DomainPanel from '@/Components/DomainPanel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    sites: Array, current: String, sector: String, catalog: Object, state: Object,
});

/* ───────── utilitaires ───────── */
const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const hdr = () => ({ Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' });
const api = async (method, url, body) => {
    const r = await fetch(url, { method, headers: { ...hdr(), 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
    const d = await r.json().catch(() => ({})); if (!r.ok) throw d; return d;
};
const selectSite = (slug) => router.get(route('modules.index'), { site: slug }, { preserveState: false });

const DAYS = [['mon', 'Lundi'], ['tue', 'Mardi'], ['wed', 'Mercredi'], ['thu', 'Jeudi'], ['fri', 'Vendredi'], ['sat', 'Samedi'], ['sun', 'Dimanche']];
const order = ['ads', 'restaurant', 'rooms', 'menu', 'booking', 'whatsapp', 'reviews', 'bot', 'legal', 'zenchef', 'payment', 'stats'];
const modules = computed(() => order.filter((k) => props.catalog[k]).map((k) => ({ key: k, ...props.catalog[k], cfg: props.state?.modules?.[k] || {} }))
    .sort((a, b) => Number(fits(b)) - Number(fits(a))));
const fits = (m) => !m.sectors?.length || m.sectors.includes(props.sector);

/* ───────── activation / configuration ───────── */
const saving = ref(false);
const save = (module, config, done) => {
    saving.value = true;
    router.post(route('modules.update', props.current), { module, config }, {
        preserveScroll: true, onFinish: () => { saving.value = false; done && done(); },
    });
};
const toggle = (m) => save(m.key, { enabled: !m.cfg.enabled });

const open = ref(null);
const cfg = reactive({});
const openConfig = async (m) => {
    Object.keys(cfg).forEach((k) => delete cfg[k]);
    Object.assign(cfg, JSON.parse(JSON.stringify(m.cfg)));
    if (m.key === 'restaurant' && !cfg.hours) cfg.hours = {};
    open.value = m.key;
    if (m.key === 'rooms') loadRooms();
    if (m.key === 'menu') loadMenu();
};
const saveConfig = () => save(open.value, { ...cfg, enabled: true }, () => { open.value = null; });

/* ───────── chambres ───────── */
const rooms = ref([]); const blocks = ref([]);
const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const blankRoom = () => ({ id: null, name: '', capacity: 2, price_night: '', weekend_price: '', min_nights: 1, max_nights: '', cleaning_fee: '', included_guests: '', extra_guest_fee: '', tourist_tax: '',
    description: '', amenities: '', photo: '', seasons: [], feeds: [], export_url: '' });
const roomForm = reactive(blankRoom());
const showAdvanced = ref(false);
const blockForm = reactive({ room_id: '', start: '', end: '', summary: '' });
const busy = ref(false); const info = ref('');
const loadRooms = async () => { try { const d = await api('GET', route('rooms.index', props.current)); rooms.value = d.rooms; blocks.value = d.blocks; } catch {} };
const v = (x) => (x === null || x === undefined || x === '' ? '' : Number(x));
const splitMd = (md) => { const [m, d] = String(md || '01-01').split('-').map(Number); return { m, d }; };
const editRoom = (r) => {
    Object.assign(roomForm, blankRoom(), {
        id: r.id, name: r.name, capacity: r.capacity, price_night: v(r.price_night), weekend_price: v(r.weekend_price), min_nights: r.min_nights || 1, max_nights: v(r.max_nights),
        cleaning_fee: v(r.cleaning_fee), included_guests: v(r.included_guests), extra_guest_fee: v(r.extra_guest_fee), tourist_tax: v(r.tourist_tax),
        description: r.description || '', amenities: (r.amenities || []).join(', '), photo: r.photos?.[0] || '', export_url: r.export_url || '',
        seasons: (r.seasons || []).map((x) => ({ name: x.name, from: splitMd(x.from), to: splitMd(x.to), price: v(x.price), weekend_price: v(x.weekend_price), min_nights: v(x.min_nights) })),
        feeds: [...(r.ical_url ? [{ label: 'iCal', url: r.ical_url }] : []), ...(r.ical_urls || [])],
    });
    showAdvanced.value = !!(r.cleaning_fee || r.extra_guest_fee || r.tourist_tax || r.max_nights);
};
const resetRoom = () => { Object.assign(roomForm, blankRoom()); showAdvanced.value = false; };
const addSeason = () => roomForm.seasons.push({ name: 'Haute saison', from: { d: 1, m: 7 }, to: { d: 31, m: 8 }, price: '', weekend_price: '', min_nights: '' });
const addFeed = () => roomForm.feeds.push({ label: '', url: '' });
const num = (x) => (x === '' || x === null || x === undefined ? null : Number(x));
const md = (o) => `${String(o.m).padStart(2, '0')}-${String(Math.min(31, Math.max(1, Number(o.d) || 1))).padStart(2, '0')}`;
const submitRoom = async () => {
    busy.value = true;
    const body = {
        name: roomForm.name, capacity: Number(roomForm.capacity) || 1, price_night: num(roomForm.price_night), weekend_price: num(roomForm.weekend_price),
        min_nights: Number(roomForm.min_nights) || 1, max_nights: num(roomForm.max_nights), cleaning_fee: num(roomForm.cleaning_fee),
        included_guests: num(roomForm.included_guests), extra_guest_fee: num(roomForm.extra_guest_fee), tourist_tax: num(roomForm.tourist_tax),
        description: roomForm.description || null, amenities: roomForm.amenities.split(',').map((s) => s.trim()).filter(Boolean), photos: roomForm.photo ? [roomForm.photo] : [],
        seasons: roomForm.seasons.map((x) => ({ name: x.name, from: md(x.from), to: md(x.to), price: num(x.price), weekend_price: num(x.weekend_price), min_nights: num(x.min_nights) })),
        ical_url: null, ical_urls: roomForm.feeds.filter((f) => f.url.trim()).map((f) => ({ label: f.label.trim() || 'iCal', url: f.url.trim() })),
    };
    try { if (roomForm.id) await api('PATCH', route('rooms.update', [props.current, roomForm.id]), body); else await api('POST', route('rooms.store', props.current), body); resetRoom(); await loadRooms(); info.value = 'Chambre enregistrée, le site est à jour.'; }
    catch (e) { alert(e?.errors ? Object.values(e.errors)[0][0] : (e?.message || 'Erreur')); } finally { busy.value = false; }
};
const roomSummary = (r) => {
    const parts = [`${r.capacity} pers.`];
    if (r.price_night) parts.push(`${Number(r.price_night)} €/nuit`);
    if (r.weekend_price) parts.push(`week-end ${Number(r.weekend_price)} €`);
    if ((r.seasons || []).length) parts.push(`${r.seasons.length} saison${r.seasons.length > 1 ? 's' : ''}`);
    if ((r.min_nights || 1) > 1) parts.push(`${r.min_nights} nuits min.`);
    return parts.join(' · ');
};
const feedCount = (r) => (r.ical_url ? 1 : 0) + (r.ical_urls || []).length;
const copied = ref('');
const copy = async (t) => { try { await navigator.clipboard.writeText(t); copied.value = t; setTimeout(() => (copied.value = ''), 2000); } catch {} };
const deleteRoom = async (r) => { if (!confirm(`Supprimer « ${r.name} » ?`)) return; await api('DELETE', route('rooms.destroy', [props.current, r.id])); loadRooms(); };
const addBlock = async () => { if (!blockForm.start || !blockForm.end) return; await api('POST', route('rooms.block', props.current), { ...blockForm, room_id: blockForm.room_id || null }); Object.assign(blockForm, { start: '', end: '', summary: '' }); loadRooms(); };
const removeBlock = async (b) => { await api('DELETE', route('rooms.unblock', [props.current, b.id])); loadRooms(); };
const syncIcal = async () => { busy.value = true; try { const d = await api('POST', route('rooms.sync', props.current)); info.value = `${d.events} indisponibilité(s) synchronisée(s).`; loadRooms(); } catch { info.value = 'Synchronisation impossible.'; } finally { busy.value = false; } };
const roomName = (id) => rooms.value.find((r) => r.id === id)?.name || 'Tous';

/* ───────── menu ───────── */
const items = ref([]); const itemForm = reactive({ id: null, category: 'Plats', name: '', price: '', description: '' }); const importText = ref('');
const loadMenu = async () => { try { const d = await api('GET', route('menu.index', props.current)); items.value = d.items; } catch {} };
const categories = computed(() => [...new Set(items.value.map((i) => i.category))]);
const submitItem = async () => {
    busy.value = true;
    const body = { category: itemForm.category || 'Plats', name: itemForm.name, price: itemForm.price === '' ? null : Number(itemForm.price), description: itemForm.description || null };
    try { if (itemForm.id) await api('PATCH', route('menu.update', [props.current, itemForm.id]), body); else await api('POST', route('menu.store', props.current), body); Object.assign(itemForm, { id: null, name: '', price: '', description: '' }); await loadMenu(); }
    catch (e) { alert(e?.message || 'Erreur'); } finally { busy.value = false; }
};
const editItem = (i) => Object.assign(itemForm, { id: i.id, category: i.category, name: i.name, price: i.price ?? '', description: i.description || '' });
const deleteItem = async (i) => { await api('DELETE', route('menu.destroy', [props.current, i.id])); loadMenu(); };
const toggleItem = async (i) => { await api('PATCH', route('menu.update', [props.current, i.id]), { category: i.category, name: i.name, price: i.price, description: i.description, available: !i.available }); loadMenu(); };
const doImport = async () => { if (!importText.value.trim()) return; busy.value = true; try { await api('POST', route('menu.import', props.current), { text: importText.value }); importText.value = ''; loadMenu(); } finally { busy.value = false; } };

/* ───────── domaine : voir le composant DomainPanel ───────── */

const dayCfg = (d) => { if (!cfg.hours[d]) cfg.hours[d] = { closed: false, lunch: ['12:00', '14:00'], dinner: ['19:00', '22:00'] }; if (!cfg.hours[d].lunch) cfg.hours[d].lunch = ['', '']; if (!cfg.hours[d].dinner) cfg.hours[d].dinner = ['', '']; return cfg.hours[d]; };
</script>

<template>
    <Head title="Modules" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">Modules</h1>
                    <p class="mt-1 text-sm text-slate-400">Activez et configurez les fonctionnalités de votre site. Tout se publie instantanément.</p>
                </div>
                <select v-if="sites.length" :value="current" @change="selectSite($event.target.value)" class="field w-72">
                    <option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}{{ s.city ? ' · ' + s.city : '' }}</option>
                </select>
            </div>
        </template>

        <div v-if="!state" class="glass rounded-2xl p-10 text-center">
            <p class="font-display text-lg font-bold text-white">Aucun site</p>
            <Link :href="route('sites.create')" class="btn-brand mt-6 inline-flex">Créer un site →</Link>
        </div>

        <template v-else>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="m in modules" :key="m.key" class="glass glass-hover flex flex-col rounded-2xl p-5" :class="m.cfg.enabled && 'border-emerald-400/20'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/5 text-2xl">{{ m.icon }}</span>
                            <div>
                                <h3 class="font-display font-bold text-white">{{ m.name }}</h3>
                                <div class="mt-0.5 flex flex-wrap gap-1.5">
                                    <span class="chip" :class="m.free ? 'bg-emerald-500/15 text-emerald-300' : 'bg-brand-500/15 text-brand-400'">{{ m.addon ? '+' + m.price + ' € HT/mois' : (m.free ? 'Gratuit' : 'Inclus Pro') }}</span>
                                    <span v-if="m.soon" class="chip bg-amber-500/15 text-amber-300">Bientôt</span>
                                    <span v-else-if="m.always_on || m.cfg.enabled" class="chip bg-emerald-500/15 text-emerald-300">● Actif</span>
                                    <span v-else class="chip bg-white/10 text-slate-400">○ Inactif</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 flex-1 text-sm text-slate-400">{{ m.desc }}</p>
                    <div class="mt-3 flex flex-wrap gap-1.5"><span v-for="f in m.features" :key="f" class="rounded-full bg-white/[0.05] px-2.5 py-1 text-[11px] text-slate-300">{{ f }}</span></div>
                    <p v-if="!fits(m)" class="mt-2 text-xs text-slate-500">Conçu pour : {{ m.sectors.join(', ') }}</p>
                    <div class="mt-4 flex gap-2">
                        <template v-if="m.key === 'ads'"><Link :href="route('ads.index', { site: current })" class="btn-brand flex-1 text-sm">📣 Ouvrir Joow Ads</Link></template>
                        <template v-else-if="m.key === 'stats'"><Link :href="route('stats.index')" class="btn-brand flex-1 text-sm">Voir les statistiques</Link></template>
                        <template v-else-if="m.soon"><button disabled class="flex-1 rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-500">Disponible prochainement</button></template>
                        <template v-else>
                            <button @click="toggle(m)" :disabled="saving" class="flex-1 rounded-xl px-4 py-2 text-sm font-semibold transition" :class="m.cfg.enabled ? 'border border-white/10 text-slate-300 hover:border-rose-400/40 hover:text-rose-300' : 'btn-brand'">{{ m.cfg.enabled ? 'Désactiver' : 'Activer' }}</button>
                            <button v-if="m.key !== 'booking'" @click="openConfig(m)" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-white/25">Configurer</button>
                            <Link v-else :href="route('sites.editor', current)" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-white/25">Dans le Studio</Link>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Domaine -->
            <div class="mt-8"><DomainPanel :key="current" :slug="current" /></div>
        </template>

        <!-- ═══ MODALE DE CONFIGURATION ═══ -->
        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur" @click="open = null">
                <div class="glass max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-ink-800 p-6" @click.stop>
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="font-display text-xl font-bold text-white">{{ catalog[open].icon }} {{ catalog[open].name }}</h3>
                        <button @click="open = null" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <!-- WhatsApp -->
                    <div v-if="open==='whatsapp'" class="space-y-4">
                        <label class="lbl">Numéro WhatsApp (avec indicatif, ex. 33612345678)<input v-model="cfg.number" class="fld" placeholder="336…" /></label>
                        <label class="lbl">Message pré-rempli<input v-model="cfg.message" class="fld" /></label>
                        <label class="lbl">Position<select v-model="cfg.position" class="fld"><option value="right">En bas à droite</option><option value="left">En bas à gauche</option></select></label>
                    </div>

                    <!-- Avis -->
                    <div v-else-if="open==='reviews'" class="space-y-4">
                        <p class="text-sm text-slate-400">{{ state.business.reviews_count || 0 }} avis Google · note {{ state.business.rating || '—' }}</p>
                        <label class="lbl">Note minimale affichée<select v-model="cfg.min_rating" class="fld"><option :value="3">3 étoiles et +</option><option :value="4">4 étoiles et +</option><option :value="5">5 étoiles uniquement</option></select></label>
                        <label class="lbl">Nombre d'avis<select v-model="cfg.count" class="fld"><option :value="3">3</option><option :value="6">6</option><option :value="9">9</option></select></label>
                    </div>

                    <!-- Mentions légales -->
                    <div v-else-if="open==='legal'" class="grid gap-4 sm:grid-cols-2">
                        <label class="lbl">Dénomination<input v-model="cfg.company_name" class="fld" :placeholder="state.business.name" /></label>
                        <label class="lbl">Forme juridique<select v-model="cfg.company_type" class="fld"><option>Entreprise individuelle</option><option>Micro-entreprise</option><option>SARL</option><option>SAS</option><option>SASU</option><option>EURL</option><option>SCI</option><option>Association</option></select></label>
                        <label class="lbl">Capital social<input v-model="cfg.capital" class="fld" placeholder="1 000 €" /></label>
                        <label class="lbl">SIRET<input v-model="cfg.siret" class="fld" /></label>
                        <label class="lbl">RCS<input v-model="cfg.rcs" class="fld" placeholder="Lyon B 123 456 789" /></label>
                        <label class="lbl">N° TVA<input v-model="cfg.tva" class="fld" placeholder="FR12345678901" /></label>
                        <label class="lbl">Directeur de la publication<input v-model="cfg.director" class="fld" /></label>
                        <label class="lbl">Email<input v-model="cfg.email" class="fld" :placeholder="state.business.email" /></label>
                        <label class="lbl sm:col-span-2">Adresse<input v-model="cfg.address" class="fld" :placeholder="state.business.address" /></label>
                        <label class="lbl">Hébergeur<input v-model="cfg.host_name" class="fld" /></label>
                        <label class="lbl">Adresse hébergeur<input v-model="cfg.host_address" class="fld" /></label>
                        <label class="lbl sm:col-span-2">Mentions complémentaires<textarea v-model="cfg.custom_mentions" rows="2" class="fld"></textarea></label>
                        <label class="lbl sm:col-span-2">Compléments politique de confidentialité<textarea v-model="cfg.custom_privacy" rows="2" class="fld"></textarea></label>
                    </div>

                    <!-- Assistant IA -->
                    <div v-else-if="open==='bot'" class="grid gap-4 sm:grid-cols-2">
                        <label class="lbl">Nom de l'assistant<input v-model="cfg.name" class="fld" /></label>
                        <label class="lbl">Ton<select v-model="cfg.tone" class="fld"><option value="chaleureux">Chaleureux</option><option value="professionnel">Professionnel</option><option value="décontracté">Décontracté</option><option value="premium">Premium</option></select></label>
                        <label class="lbl sm:col-span-2">Message d'accueil<input v-model="cfg.welcome" class="fld" /></label>
                        <label class="lbl">Position<select v-model="cfg.position" class="fld"><option value="right">Droite</option><option value="left">Gauche</option></select></label>
                        <label class="flex items-center gap-2 self-end text-sm text-slate-300"><input type="checkbox" v-model="cfg.lead_capture" class="h-4 w-4 rounded border-white/20 bg-white/5" /> Proposer de laisser ses coordonnées</label>
                        <label class="lbl sm:col-span-2">Infos pratiques (parking, accès, arrivée, animaux…)<textarea v-model="cfg.practical" rows="3" class="fld"></textarea></label>
                        <label class="lbl sm:col-span-2">Questions / réponses supplémentaires<textarea v-model="cfg.faq" rows="3" class="fld" placeholder="Q: … R: …"></textarea></label>
                        <p class="text-xs text-slate-500 sm:col-span-2">L'assistant connaît déjà vos services, horaires, FAQ, carte et chambres.</p>
                    </div>

                    <!-- Réservation restaurant -->
                    <div v-else-if="open==='restaurant'" class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="lbl">Pas entre créneaux (min)<select v-model="cfg.slot_minutes" class="fld"><option :value="15">15</option><option :value="30">30</option><option :value="45">45</option><option :value="60">60</option></select></label>
                            <label class="lbl">Couverts max / créneau<input type="number" v-model.number="cfg.max_covers_per_slot" class="fld" min="1" /></label>
                            <label class="lbl">Taille max d'une table<input type="number" v-model.number="cfg.max_party" class="fld" min="1" /></label>
                            <label class="lbl">Délai minimum (heures)<input type="number" v-model.number="cfg.min_advance_hours" class="fld" min="0" /></label>
                            <label class="lbl">Réservation jusqu'à (jours)<input type="number" v-model.number="cfg.max_advance_days" class="fld" min="1" /></label>
                            <label class="lbl">Email de notification<input v-model="cfg.notify_email" class="fld" placeholder="vous@exemple.fr" /></label>
                        </div>
                        <div class="flex flex-wrap gap-6 text-sm text-slate-300">
                            <label class="flex items-center gap-2"><input type="checkbox" v-model="cfg.auto_confirm" class="h-4 w-4 rounded border-white/20 bg-white/5" /> Confirmation automatique</label>
                            <label class="flex items-center gap-2"><input type="checkbox" v-model="cfg.require_email" class="h-4 w-4 rounded border-white/20 bg-white/5" /> Email obligatoire</label>
                            <label class="flex items-center gap-2"><input type="checkbox" v-model="cfg.sms_confirm" class="h-4 w-4 rounded border-white/20 bg-white/5" /> SMS de confirmation</label>
                            <label class="flex items-center gap-2"><input type="checkbox" v-model="cfg.sms_reminder" class="h-4 w-4 rounded border-white/20 bg-white/5" /> SMS de rappel la veille</label>
                        </div>
                        <p v-if="!state.sms_enabled" class="text-xs text-amber-300/90">Les SMS seront envoyés dès que l'envoi SMS sera activé sur la plateforme (Brevo). Les emails brandés, eux, partent déjà.</p>
                        <label class="lbl">Message de confirmation<textarea v-model="cfg.confirmation_message" rows="2" class="fld"></textarea></label>
                        <div>
                            <p class="mb-2 text-sm font-semibold text-white">Services par jour</p>
                            <div class="overflow-x-auto rounded-xl border border-white/[0.06]">
                                <table class="w-full text-sm">
                                    <thead class="text-xs uppercase text-slate-500"><tr><th class="px-3 py-2 text-left">Jour</th><th class="px-3 py-2">Fermé</th><th class="px-3 py-2">Midi</th><th class="px-3 py-2">Soir</th></tr></thead>
                                    <tbody>
                                        <tr v-for="[d,label] in DAYS" :key="d" class="border-t border-white/[0.05]">
                                            <td class="px-3 py-2 font-medium text-slate-200">{{ label }}</td>
                                            <td class="px-3 py-2 text-center"><input type="checkbox" v-model="dayCfg(d).closed" class="h-4 w-4 rounded border-white/20 bg-white/5" /></td>
                                            <td class="px-3 py-2"><div class="flex items-center gap-1"><input type="time" v-model="dayCfg(d).lunch[0]" :disabled="dayCfg(d).closed" class="fld w-28" /><span class="text-slate-500">–</span><input type="time" v-model="dayCfg(d).lunch[1]" :disabled="dayCfg(d).closed" class="fld w-28" /></div></td>
                                            <td class="px-3 py-2"><div class="flex items-center gap-1"><input type="time" v-model="dayCfg(d).dinner[0]" :disabled="dayCfg(d).closed" class="fld w-28" /><span class="text-slate-500">–</span><input type="time" v-model="dayCfg(d).dinner[1]" :disabled="dayCfg(d).closed" class="fld w-28" /></div></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Laissez un service vide pour ne pas le proposer. Les rappels sont envoyés la veille à 18h aux clients ayant laissé un email.</p>
                        </div>
                    </div>

                    <!-- Chambres -->
                    <div v-else-if="open==='rooms'" class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="lbl">Titre de la section<input v-model="cfg.title" class="fld" placeholder="Nos chambres" /></label>
                            <label class="lbl">Email de notification<input v-model="cfg.notify_email" class="fld" placeholder="vous@exemple.fr" /></label>
                            <label class="lbl sm:col-span-2">Introduction<input v-model="cfg.intro" class="fld" placeholder="Des chambres au calme, petit-déjeuner inclus…" /></label>
                            <label class="flex items-start gap-3 rounded-xl border border-white/[0.06] p-3 sm:col-span-2">
                                <input type="checkbox" v-model="cfg.instant" class="mt-0.5 rounded border-white/20 bg-white/5 text-brand-500" />
                                <span class="text-sm"><span class="font-semibold text-white">Réservation instantanée</span><span class="block text-slate-400">Si les dates sont libres, la réservation est confirmée tout de suite (et l'acompte demandé si le module Paiement est actif). Sinon, vous validez chaque demande.</span></span>
                            </label>
                        </div>
                        <div class="rounded-xl border border-white/[0.06] p-4">
                            <p class="mb-3 text-sm font-semibold text-white">Vos chambres / hébergements ({{ rooms.length }})</p>
                            <div v-for="r in rooms" :key="r.id" class="mb-2 flex items-center justify-between gap-3 rounded-lg bg-white/[0.03] px-3 py-2 text-sm">
                                <span class="min-w-0"><span class="font-semibold text-white">{{ r.name }}</span><span class="text-slate-400"> · {{ roomSummary(r) }}</span><span v-if="feedCount(r)" class="chip ml-2 bg-emerald-500/15 text-emerald-300">{{ feedCount(r) }} iCal</span></span>
                                <span class="flex shrink-0 gap-2"><button @click="editRoom(r)" class="text-brand-400">Modifier</button><button @click="deleteRoom(r)" class="text-slate-500 hover:text-rose-300">✕</button></span>
                            </div>

                            <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ roomForm.id ? 'Modifier « ' + roomForm.name + ' »' : 'Nouvelle chambre' }}</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="lbl">Nom<input v-model="roomForm.name" class="fld" placeholder="Chambre Double Vue Mer" /></label>
                                <div class="grid grid-cols-2 gap-3"><label class="lbl">Capacité (pers.)<input type="number" v-model="roomForm.capacity" class="fld" min="1" /></label><label class="lbl">Nuits minimum<input type="number" v-model="roomForm.min_nights" class="fld" min="1" /></label></div>
                                <label class="lbl sm:col-span-2">Description<input v-model="roomForm.description" class="fld" /></label>
                                <label class="lbl">Équipements (séparés par des virgules)<input v-model="roomForm.amenities" class="fld" placeholder="Wifi, Climatisation, Balcon" /></label>
                                <label class="lbl">Photo (URL)<input v-model="roomForm.photo" class="fld" placeholder="https://…" /></label>
                            </div>

                            <!-- Tarifs -->
                            <div class="mt-4 rounded-lg bg-white/[0.02] p-3">
                                <p class="text-sm font-semibold text-white">Tarifs par nuit</p>
                                <div class="mt-2 grid grid-cols-2 gap-3">
                                    <label class="lbl">Semaine (€)<input type="number" v-model="roomForm.price_night" class="fld" min="0" placeholder="95" /></label>
                                    <label class="lbl">Vendredi et samedi (€)<input type="number" v-model="roomForm.weekend_price" class="fld" min="0" placeholder="Identique" /></label>
                                </div>
                                <div v-for="(sz, i) in roomForm.seasons" :key="i" class="mt-3 rounded-lg border border-white/[0.06] p-3">
                                    <div class="flex items-center gap-2">
                                        <input v-model="sz.name" class="fld flex-1" placeholder="Haute saison" />
                                        <button @click="roomForm.seasons.splice(i, 1)" class="text-slate-500 hover:text-rose-300" title="Retirer">✕</button>
                                    </div>
                                    <div class="mt-2 grid grid-cols-[auto_4.5rem_1fr] items-center gap-2 text-sm text-slate-400 sm:grid-cols-[auto_4.5rem_1fr_auto_4.5rem_1fr]">
                                        <span>du</span><input type="number" v-model="sz.from.d" min="1" max="31" class="fld" /><select v-model.number="sz.from.m" class="fld"><option v-for="(mn, k) in MONTHS" :key="k" :value="k + 1">{{ mn }}</option></select>
                                        <span>au</span><input type="number" v-model="sz.to.d" min="1" max="31" class="fld" /><select v-model.number="sz.to.m" class="fld"><option v-for="(mn, k) in MONTHS" :key="k" :value="k + 1">{{ mn }}</option></select>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Revient chaque année.</p>
                                    <div class="mt-2 grid grid-cols-3 gap-2">
                                        <label class="lbl">Prix / nuit (€)<input type="number" v-model="sz.price" class="fld" min="0" /></label>
                                        <label class="lbl">Ven. et sam. (€)<input type="number" v-model="sz.weekend_price" class="fld" min="0" placeholder="Identique" /></label>
                                        <label class="lbl">Nuits min.<input type="number" v-model="sz.min_nights" class="fld" min="1" :placeholder="String(roomForm.min_nights || 1)" /></label>
                                    </div>
                                </div>
                                <button @click="addSeason" class="mt-3 rounded-lg border border-dashed border-white/15 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:border-white/30">+ Ajouter une saison (été, vacances, fêtes…)</button>

                                <button @click="showAdvanced = !showAdvanced" class="mt-3 block text-xs font-semibold text-brand-400">{{ showAdvanced ? '▾' : '▸' }} Frais, taxe de séjour et durée maximale</button>
                                <div v-if="showAdvanced" class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <label class="lbl">Frais de ménage (€, par séjour)<input type="number" v-model="roomForm.cleaning_fee" class="fld" min="0" /></label>
                                    <label class="lbl">Taxe de séjour (€ / pers. / nuit)<input type="number" step="0.01" v-model="roomForm.tourist_tax" class="fld" min="0" /></label>
                                    <label class="lbl">Nuits maximum<input type="number" v-model="roomForm.max_nights" class="fld" min="1" /></label>
                                    <label class="lbl">Personnes incluses dans le prix<input type="number" v-model="roomForm.included_guests" class="fld" min="1" placeholder="Toutes" /></label>
                                    <label class="lbl">Supplément / pers. en plus / nuit (€)<input type="number" v-model="roomForm.extra_guest_fee" class="fld" min="0" /></label>
                                </div>
                            </div>

                            <!-- Calendriers -->
                            <div class="mt-4 rounded-lg bg-white/[0.02] p-3">
                                <p class="text-sm font-semibold text-white">Calendriers synchronisés</p>
                                <p class="mt-0.5 text-xs text-slate-500">Les dates réservées sur Airbnb, Booking, Abritel ou Bed-Booking sont bloquées automatiquement (toutes les heures).</p>
                                <div v-for="(f, i) in roomForm.feeds" :key="i" class="mt-2 flex gap-2">
                                    <input v-model="f.label" class="fld !w-32 shrink-0" placeholder="Booking" />
                                    <input v-model="f.url" class="fld flex-1" placeholder="https://…/calendar.ics" />
                                    <button @click="roomForm.feeds.splice(i, 1)" class="text-slate-500 hover:text-rose-300">✕</button>
                                </div>
                                <button @click="addFeed" class="mt-2 rounded-lg border border-dashed border-white/15 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:border-white/30">+ Importer un calendrier</button>
                                <div v-if="roomForm.export_url" class="mt-3 rounded-lg border border-brand-400/20 bg-brand-500/5 p-2.5">
                                    <p class="text-xs text-slate-300">À coller dans Airbnb / Booking pour qu'ils bloquent les réservations faites sur votre site :</p>
                                    <div class="mt-1.5 flex gap-2"><input :value="roomForm.export_url" readonly class="fld flex-1 text-xs" @focus="$event.target.select()" /><button @click="copy(roomForm.export_url)" class="rounded-lg bg-white/10 px-3 text-xs font-semibold text-white">{{ copied === roomForm.export_url ? 'Copié ✓' : 'Copier' }}</button></div>
                                </div>
                                <p v-else class="mt-2 text-xs text-slate-500">Le lien d'export apparaîtra une fois la chambre enregistrée.</p>
                            </div>

                            <div class="mt-3 flex gap-2"><button @click="submitRoom" :disabled="busy || !roomForm.name" class="btn-brand text-sm">{{ roomForm.id ? 'Enregistrer' : '+ Ajouter la chambre' }}</button><button v-if="roomForm.id" @click="resetRoom" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Annuler</button><button @click="syncIcal" :disabled="busy" class="ml-auto rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:border-white/25">🔄 Synchroniser iCal</button></div>
                            <p v-if="info" class="mt-2 text-xs text-emerald-300">{{ info }}</p>
                        </div>
                        <div class="rounded-xl border border-white/[0.06] p-4">
                            <p class="mb-3 text-sm font-semibold text-white">Périodes bloquées</p>
                            <div v-for="b in blocks" :key="b.id" class="mb-1.5 flex items-center justify-between rounded-lg bg-white/[0.03] px-3 py-2 text-sm"><span class="text-slate-300">{{ b.start.slice(0,10) }} → {{ b.end.slice(0,10) }} · {{ roomName(b.room_id) }} <span class="chip ml-1" :class="b.source==='ical' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-white/10 text-slate-400'">{{ b.source==='ical' ? 'iCal' : 'manuel' }}</span> <span v-if="b.summary" class="text-slate-500">· {{ b.summary }}</span></span><button v-if="b.source==='manual'" @click="removeBlock(b)" class="text-slate-500 hover:text-rose-300">✕</button></div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-4"><select v-model="blockForm.room_id" class="fld"><option value="">Toutes les chambres</option><option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option></select><input type="date" v-model="blockForm.start" class="fld" /><input type="date" v-model="blockForm.end" class="fld" /><button @click="addBlock" class="rounded-xl border border-white/10 px-3 py-2 text-sm text-slate-200 hover:border-white/25">Bloquer</button></div>
                        </div>
                    </div>

                    <!-- Menu -->
                    <div v-else-if="open==='menu'" class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="lbl">Titre de la section<input v-model="cfg.title" class="fld" placeholder="Notre carte" /></label>
                            <label class="flex items-center gap-2 self-end text-sm text-slate-300"><input type="checkbox" v-model="cfg.qr" class="h-4 w-4 rounded border-white/20 bg-white/5" /> Afficher un QR code de la carte</label>
                        </div>
                        <div class="rounded-xl border border-white/[0.06] p-4">
                            <p class="mb-3 text-sm font-semibold text-white">Plats ({{ items.length }})</p>
                            <div v-for="c in categories" :key="c" class="mb-3">
                                <p class="mb-1 text-xs font-bold uppercase tracking-wider text-brand-400">{{ c }}</p>
                                <div v-for="i in items.filter(x=>x.category===c)" :key="i.id" class="mb-1 flex items-center justify-between gap-3 rounded-lg bg-white/[0.03] px-3 py-2 text-sm">
                                    <span class="min-w-0" :class="!i.available && 'opacity-50 line-through'"><span class="font-semibold text-white">{{ i.name }}</span><span v-if="i.price !== null" class="text-slate-400"> · {{ Number(i.price).toFixed(2) }} €</span></span>
                                    <span class="flex shrink-0 gap-2"><button @click="toggleItem(i)" class="text-slate-400" :title="i.available ? 'Marquer indisponible' : 'Rendre disponible'">{{ i.available ? '👁' : '🚫' }}</button><button @click="editItem(i)" class="text-brand-400">Modifier</button><button @click="deleteItem(i)" class="text-slate-500 hover:text-rose-300">✕</button></span>
                                </div>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                                <input v-model="itemForm.category" list="cats" class="fld" placeholder="Catégorie" /><datalist id="cats"><option v-for="c in ['Entrées','Plats','Desserts','Boissons','Menus','Vins']" :key="c" :value="c" /></datalist>
                                <input v-model="itemForm.name" class="fld" placeholder="Nom du plat" />
                                <input type="number" step="0.5" v-model="itemForm.price" class="fld" placeholder="Prix €" />
                                <button @click="submitItem" :disabled="busy || !itemForm.name" class="btn-brand text-sm">{{ itemForm.id ? 'Enregistrer' : '+ Ajouter' }}</button>
                                <input v-model="itemForm.description" class="fld sm:col-span-4" placeholder="Description (optionnel)" />
                            </div>
                        </div>
                        <details class="rounded-xl border border-white/[0.06] p-4"><summary class="cursor-pointer text-sm font-semibold text-white">Import rapide (une ligne par plat : Catégorie | Nom | Prix | Description)</summary>
                            <textarea v-model="importText" rows="5" class="fld mt-3" placeholder="Entrées | Salade César | 12 | Poulet, parmesan&#10;Plats | Entrecôte frites | 24"></textarea>
                            <button @click="doImport" :disabled="busy" class="btn-brand mt-2 text-sm">Importer</button>
                        </details>
                    </div>

                    <!-- ZenChef -->
                    <div v-else-if="open==='zenchef'" class="space-y-4">
                        <label class="lbl">Identifiant restaurant ZenChef (rid)<input v-model="cfg.restaurant_id" class="fld" placeholder="123456" /></label>
                        <p class="text-xs text-slate-500">Vous le trouvez dans votre espace ZenChef → Widget de réservation (paramètre <code>rid</code>). Le widget remplacera le formulaire de réservation Joow.</p>
                    </div>

                    <!-- Paiement en ligne (Stripe Connect) -->
                    <div v-else-if="open==='payment'" class="space-y-5">
                        <div class="rounded-xl border p-4" :class="state.stripe.charges_enabled ? 'border-emerald-400/30 bg-emerald-500/[0.06]' : 'border-amber-400/30 bg-amber-500/[0.06]'">
                            <p class="font-semibold text-white">{{ state.stripe.charges_enabled ? '✅ Compte Stripe connecté — vous pouvez encaisser' : (state.stripe.connected ? '⏳ Onboarding Stripe à terminer' : '💳 Connectez votre compte Stripe') }}</p>
                            <p class="mt-1 text-sm text-slate-400">L'argent des acomptes et des empreintes arrive <strong class="text-slate-200">directement sur votre compte bancaire</strong> via Stripe (compte gratuit, ouverture en 5 minutes).</p>
                            <p v-if="!state.stripe.configured" class="mt-2 text-xs text-amber-300">La connexion Stripe sera disponible dès l'activation des clés Stripe de la plateforme.</p>
                            <a v-else-if="!state.stripe.charges_enabled" :href="route('modules.stripe.connect', current)" class="btn-brand mt-3 inline-flex text-sm">{{ state.stripe.connected ? 'Terminer la configuration Stripe →' : 'Connecter mon compte Stripe →' }}</a>
                        </div>
                        <label class="lbl">Ce que vous encaissez
                            <select v-model="cfg.type" class="fld">
                                <option value="deposit">Acompte sur les séjours (% du total)</option>
                                <option value="full">Paiement intégral des séjours</option>
                                <option value="hold">Empreinte bancaire anti no-show sur les tables</option>
                            </select>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label v-if="cfg.type==='deposit'" class="lbl">Acompte (% du total)<input type="number" v-model.number="cfg.deposit_percent" class="fld" min="1" max="100" /></label>
                            <label v-if="cfg.type==='hold'" class="lbl">Empreinte par couvert (€)<input type="number" v-model.number="cfg.hold_per_cover" class="fld" min="1" /></label>
                            <label class="lbl">Email de notification<input v-model="cfg.notify_email" class="fld" placeholder="vous@exemple.fr" /></label>
                        </div>
                        <ul class="space-y-1 text-xs text-slate-500">
                            <li>• <strong class="text-slate-300">Séjours</strong> : quand vous confirmez une demande, le client reçoit un email brandé avec le bouton « Régler l'acompte ». Le séjour est garanti dès paiement.</li>
                            <li>• <strong class="text-slate-300">Tables</strong> : à la réservation, le client enregistre sa carte (aucun débit). Marquez « No-show » dans Réservations pour débiter l'empreinte ; « Installés » ou « Annuler » la libère automatiquement.</li>
                        </ul>
                    </div>

                    <div v-else class="text-sm text-slate-400">Aucun réglage pour ce module.</div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="open = null" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-semibold text-slate-300">Fermer</button>
                        <button v-if="!['rooms','menu'].includes(open) || true" @click="saveConfig" :disabled="saving" class="btn-brand text-sm">{{ saving ? 'Enregistrement…' : 'Enregistrer et activer' }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<style scoped>
.lbl { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; }
.lbl .fld { margin-top: 4px; }
.fld { width: 100%; border-radius: 10px; border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04); padding: 8px 10px; font-size: 13px; color: #e2e8f0; outline: none; color-scheme: dark; }
.fld:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.3); }
.fld:disabled { opacity: .4; }
select.fld option { background: #14141f; }
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
