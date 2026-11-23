<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({ tables: Array, stays: Array, sites: Array, kpi: Object, rooms: { type: Array, default: () => [] }, blocks: { type: Array, default: () => [] } });

const tab = ref(props.tables.length || !(props.stays.length || props.rooms.length) ? 'tables' : 'stays');
const hasRooms = computed(() => props.rooms.length > 0);
const roomName = (id) => props.rooms.find((r) => r.id === id)?.name;
const filter = ref('upcoming');
const today = new Date().toISOString().slice(0, 10);

const tables = computed(() => props.tables.filter((r) => filter.value === 'all' ? true : filter.value === 'pending' ? r.status === 'pending' : r.date >= today && r.status !== 'cancelled'));
const stays = computed(() => props.stays.filter((b) => filter.value === 'all' ? true : filter.value === 'pending' ? b.status === 'pending' : b.check_out >= today && !['cancelled', 'archived'].includes(b.status)));

const fmt = (d) => new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
const money = (n) => n == null ? '—' : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n);
const statusStyle = (s) => ({ pending: 'bg-amber-500/15 text-amber-300', confirmed: 'bg-emerald-500/15 text-emerald-300', seated: 'bg-brand-500/15 text-brand-400', cancelled: 'bg-white/10 text-slate-500', archived: 'bg-white/5 text-slate-400', noshow: 'bg-rose-500/15 text-rose-300' }[s] || 'bg-white/10 text-slate-400');
const statusLabel = (s) => ({ pending: 'En attente', confirmed: 'Confirmée', seated: 'Installés', cancelled: 'Annulée', archived: 'Historique', noshow: 'No-show' }[s] || s);

const setTable = (r, status) => router.patch(route('reservations.table.update', r.id), { status }, { preserveScroll: true });
const setStay = (b, status) => router.patch(route('reservations.stay.update', b.id), { status }, { preserveScroll: true });

const add = ref(null);
const tf = reactive({ site_slug: props.sites[0]?.slug || '', date: today, time: '20:00', covers: 2, name: '', phone: '', email: '', notes: '' });
const sf = reactive({ site_slug: props.sites[0]?.slug || '', room_id: '', check_in: today, check_out: '', guests: 2, total: '', name: '', phone: '', email: '', notes: '' });
const sfErrors = ref({});
const roomsOf = (slug) => props.rooms.filter((r) => r.site_slug === slug);
// Fermer des dates (dernière nuit incluse)
const bf = reactive({ site_slug: props.sites[0]?.slug || '', room_id: '', start: today, end: today, summary: '' });
const bfErrors = ref({});
const submitBlock = () => router.post(route('reservations.block.store'), { ...bf, room_id: bf.room_id || null }, { preserveScroll: true, onError: (e) => { bfErrors.value = e; }, onSuccess: () => { add.value = null; bfErrors.value = {}; bf.summary = ''; tab.value = 'closed'; } });
const reopen = (b) => { if (confirm('Rouvrir ces dates à la réservation ?')) router.delete(route('reservations.block.destroy', b.id), { preserveScroll: true }); };
const lastNight = (end) => { const d = new Date(end + 'T00:00:00'); d.setDate(d.getDate() - 1); return d.toISOString().slice(0, 10); };
const submitTable = () => router.post(route('reservations.table.store'), tf, { preserveScroll: true, onSuccess: () => { add.value = null; tf.name = ''; tf.phone = ''; } });
const submitStay = () => router.post(route('reservations.stay.store'), { ...sf, room_id: sf.room_id || null, total: sf.total === '' ? null : Number(sf.total) }, { preserveScroll: true, onError: (e) => { sfErrors.value = e; }, onSuccess: () => { add.value = null; sfErrors.value = {}; sf.name = ''; sf.phone = ''; sf.email = ''; sf.notes = ''; sf.total = ''; tab.value = 'stays'; } });
</script>

<template>
    <Head title="Réservations" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">Réservations</h1>
                    <p class="mt-1 text-sm text-slate-400">Tables et séjours reçus depuis vos sites, ou ajoutés à la main.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="!hasRooms || tables.length" @click="add = 'table'" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-white/25">+ Table</button>
                    <button v-if="hasRooms" @click="add = 'block'" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-white/25">🔒 Fermer des dates</button>
                    <button @click="add = 'stay'" class="btn-brand text-sm">+ Ajouter une réservation</button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-3 gap-4">
            <div class="glass rounded-2xl p-5"><p class="text-sm text-slate-400">Couverts aujourd'hui</p><p class="mt-1 font-display text-3xl font-bold text-white">{{ kpi.today }}</p></div>
            <div class="glass rounded-2xl p-5"><p class="text-sm text-slate-400">En attente</p><p class="mt-1 font-display text-3xl font-bold text-amber-300">{{ kpi.pending }}</p></div>
            <div class="glass rounded-2xl p-5"><p class="text-sm text-slate-400">Arrivées du jour</p><p class="mt-1 font-display text-3xl font-bold text-emerald-300">{{ kpi.arrivals }}</p></div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-2">
            <div class="glass flex rounded-xl p-1 text-sm font-semibold">
                <button @click="tab='tables'" class="rounded-lg px-4 py-2" :class="tab==='tables' ? 'bg-white/10 text-white' : 'text-slate-400'">🍽️ Tables ({{ props.tables.length }})</button>
                <button @click="tab='stays'" class="rounded-lg px-4 py-2" :class="tab==='stays' ? 'bg-white/10 text-white' : 'text-slate-400'">🛏️ Séjours ({{ props.stays.length }})</button>
                <button v-if="hasRooms" @click="tab='closed'" class="rounded-lg px-4 py-2" :class="tab==='closed' ? 'bg-white/10 text-white' : 'text-slate-400'">🔒 Dates fermées ({{ blocks.length }})</button>
            </div>
            <div v-if="tab!=='closed'" class="ml-auto flex gap-1 text-xs font-semibold">
                <button v-for="f in [['upcoming','À venir'],['pending','En attente'],['all','Tout']]" :key="f[0]" @click="filter=f[0]" class="rounded-lg px-3 py-1.5" :class="filter===f[0] ? 'bg-brand-gradient text-white' : 'glass text-slate-300'">{{ f[1] }}</button>
            </div>
        </div>

        <!-- Tables -->
        <div v-if="tab==='tables'" class="mt-4 space-y-2">
            <div v-for="r in tables" :key="r.id" class="glass flex flex-wrap items-center gap-4 rounded-2xl p-4">
                <div class="w-28 shrink-0"><p class="font-display text-lg font-bold text-white">{{ r.time }}</p><p class="text-xs text-slate-400">{{ fmt(r.date) }}</p></div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white">{{ r.name }} <span class="text-slate-400">· {{ r.covers }} couv.</span></p>
                    <p class="text-xs text-slate-400">{{ r.phone }}<span v-if="r.email"> · {{ r.email }}</span><span v-if="sites.length>1"> · {{ r.site }}</span><span v-if="r.source==='manual'"> · manuel</span></p>
                    <p v-if="r.notes" class="mt-1 text-xs text-slate-300">“{{ r.notes }}”</p>
                </div>
                <span class="chip" :class="statusStyle(r.status)">{{ statusLabel(r.status) }}</span>
                <div class="flex gap-1.5 text-xs font-semibold">
                    <button v-if="r.status==='pending'" @click="setTable(r,'confirmed')" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-emerald-300">Confirmer</button>
                    <button v-if="r.status==='confirmed'" @click="setTable(r,'seated')" class="rounded-lg bg-brand-500/15 px-3 py-1.5 text-brand-400">Installés</button>
                    <button v-if="r.status==='confirmed'" @click="setTable(r,'noshow')" class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-400">No-show</button>
                    <button v-if="!['cancelled','noshow'].includes(r.status)" @click="setTable(r,'cancelled')" class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-400 hover:text-rose-300">Annuler</button>
                </div>
            </div>
            <div v-if="!tables.length" class="glass rounded-2xl p-10 text-center text-slate-400">Aucune réservation de table. Activez le module <strong class="text-white">Réservation Restaurant</strong> dans Modules pour recevoir des réservations en ligne.</div>
        </div>

        <!-- Dates fermées -->
        <div v-else-if="tab==='closed'" class="mt-4 space-y-2">
            <p class="text-sm text-slate-400">Sur ces nuits, plus aucune demande n'est possible sur votre site. Les séjours confirmés ferment aussi leurs dates automatiquement.</p>
            <div v-for="b in blocks" :key="b.id" class="glass flex flex-wrap items-center gap-4 rounded-2xl p-4">
                <div class="w-64 shrink-0"><p class="font-display font-bold text-white">{{ fmt(b.start) }} → {{ fmt(lastNight(b.end)) }}</p><p class="text-xs text-slate-400">dernière nuit incluse</p></div>
                <div class="min-w-0 flex-1"><p class="font-semibold text-white">{{ b.room_id ? roomName(b.room_id) : 'Tout l\'établissement' }}</p><p class="text-xs text-slate-400">{{ b.summary || 'Fermé' }}<span v-if="b.source==='ical'"> · calendrier synchronisé</span><span v-if="sites.length>1"> · {{ b.site }}</span></p></div>
                <button v-if="b.source==='manual'" @click="reopen(b)" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-emerald-300">Rouvrir</button>
            </div>
            <div v-if="!blocks.length" class="glass rounded-2xl p-10 text-center text-slate-400">Aucune date fermée. Cliquez sur « 🔒 Fermer des dates » pour bloquer une période.</div>
        </div>

        <!-- Séjours -->
        <div v-else class="mt-4 space-y-2">
            <div v-for="b in stays" :key="b.id" class="glass flex flex-wrap items-center gap-4 rounded-2xl p-4">
                <div class="w-56 shrink-0"><p class="font-display font-bold text-white">{{ fmt(b.check_in) }} → {{ fmt(b.check_out) }}</p><p class="text-xs text-slate-400">{{ b.nights }} nuit{{ b.nights>1?'s':'' }} · {{ b.guests }} pers.</p></div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white">{{ b.name }} <span v-if="b.room" class="text-slate-400">· {{ b.room }}</span></p>
                    <p class="text-xs text-slate-400">{{ b.phone }}<span v-if="b.email"> · {{ b.email }}</span><span v-if="sites.length>1"> · {{ b.site }}</span><span v-if="b.source!=='website'"> · {{ b.source }}</span></p>
                    <p v-if="b.notes" class="mt-1 text-xs text-slate-300">“{{ b.notes }}”</p>
                </div>
                <div class="text-right"><p class="font-display font-bold text-white">{{ money(b.total) }}</p><span class="chip" :class="statusStyle(b.status)">{{ statusLabel(b.status) }}</span></div>
                <div class="flex gap-1.5 text-xs font-semibold">
                    <button v-if="b.status==='pending'" @click="setStay(b,'confirmed')" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-emerald-300">Confirmer</button>
                    <button v-if="!['cancelled','archived'].includes(b.status)" @click="setStay(b,'cancelled')" class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-400 hover:text-rose-300">Annuler</button>
                </div>
            </div>
            <div v-if="!stays.length" class="glass rounded-2xl p-10 text-center text-slate-400">Aucun séjour. Activez le module <strong class="text-white">Chambres & Calendrier</strong> pour recevoir des demandes de séjour.</div>
        </div>

        <!-- Ajout manuel -->
        <Transition name="fade">
            <div v-if="add" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur" @click="add=null">
                <div class="glass w-full max-w-lg rounded-2xl bg-ink-800 p-6" @click.stop>
                    <h3 class="font-display text-lg font-bold text-white">{{ add==='table' ? 'Nouvelle réservation de table' : add==='block' ? 'Fermer des dates' : 'Ajouter une réservation' }}</h3>
                    <p v-if="add==='stay'" class="mt-1 text-sm text-slate-400">Réservation prise en direct (téléphone, e-mail, sur place…). Elle est confirmée et ses dates sont fermées sur votre site.</p>
                    <p v-if="add==='block'" class="mt-1 text-sm text-slate-400">Aucune demande ne sera possible sur ces nuits (dernière nuit incluse).</p>
                    <form v-if="add==='block'" @submit.prevent="submitBlock" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-if="sites.length>1" v-model="bf.site_slug" class="field sm:col-span-2"><option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}</option></select>
                        <select v-model="bf.room_id" class="field sm:col-span-2"><option value="">Tout l'établissement (toutes les chambres)</option><option v-for="r in roomsOf(bf.site_slug)" :key="r.id" :value="r.id">{{ r.name }}</option></select>
                        <label class="text-xs text-slate-400">Première nuit<input type="date" v-model="bf.start" class="field mt-1" required /></label>
                        <label class="text-xs text-slate-400">Dernière nuit<input type="date" v-model="bf.end" :min="bf.start" class="field mt-1" required /></label>
                        <input v-model="bf.summary" class="field sm:col-span-2" placeholder="Motif (facultatif) : fermeture annuelle, travaux…" />
                        <p v-if="bfErrors.end || bfErrors.start" class="text-sm text-rose-400 sm:col-span-2">{{ bfErrors.end || bfErrors.start }}</p>
                        <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" @click="add=null" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Annuler</button><button class="btn-brand text-sm">Fermer ces dates</button></div>
                    </form>
                    <form v-if="add==='table'" @submit.prevent="submitTable" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-if="sites.length>1" v-model="tf.site_slug" class="field sm:col-span-2"><option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}</option></select>
                        <input type="date" v-model="tf.date" class="field" required /><input type="time" v-model="tf.time" class="field" required />
                        <input v-model="tf.name" class="field" placeholder="Nom" required /><input type="number" v-model="tf.covers" class="field" min="1" placeholder="Couverts" />
                        <input v-model="tf.phone" class="field" placeholder="Téléphone" /><input v-model="tf.email" type="email" class="field" placeholder="Email" />
                        <input v-model="tf.notes" class="field sm:col-span-2" placeholder="Notes" />
                        <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" @click="add=null" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Annuler</button><button class="btn-brand text-sm">Enregistrer</button></div>
                    </form>
                    <form v-else-if="add==='stay'" @submit.prevent="submitStay" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-if="sites.length>1" v-model="sf.site_slug" class="field sm:col-span-2"><option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}</option></select>
                        <select v-if="roomsOf(sf.site_slug).length" v-model="sf.room_id" class="field sm:col-span-2" required><option value="" disabled>Chambre…</option><option v-for="r in roomsOf(sf.site_slug)" :key="r.id" :value="r.id">{{ r.name }}</option></select>
                        <label class="text-xs text-slate-400">Arrivée<input type="date" v-model="sf.check_in" class="field mt-1" required /></label><label class="text-xs text-slate-400">Départ<input type="date" v-model="sf.check_out" :min="sf.check_in" class="field mt-1" required /></label>
                        <input v-model="sf.name" class="field" placeholder="Nom" required /><input type="number" v-model="sf.guests" class="field" min="1" placeholder="Personnes" />
                        <input v-model="sf.phone" class="field" placeholder="Téléphone" /><input v-model="sf.email" type="email" class="field" placeholder="Email" />
                        <input type="number" v-model="sf.total" class="field" placeholder="Total (€) — calculé si vide" /><input v-model="sf.notes" class="field" placeholder="Notes" />
                        <p v-if="Object.keys(sfErrors).length" class="text-sm text-rose-400 sm:col-span-2">{{ Object.values(sfErrors)[0] }}</p>
                        <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" @click="add=null" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Annuler</button><button class="btn-brand text-sm">Enregistrer</button></div>
                    </form>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
