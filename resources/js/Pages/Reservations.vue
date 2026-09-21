<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({ tables: Array, stays: Array, sites: Array, kpi: Object });

const tab = ref(props.tables.length || !props.stays.length ? 'tables' : 'stays');
const filter = ref('upcoming');
const today = new Date().toISOString().slice(0, 10);

const tables = computed(() => props.tables.filter((r) => filter.value === 'all' ? true : filter.value === 'pending' ? r.status === 'pending' : r.date >= today && r.status !== 'cancelled'));
const stays = computed(() => props.stays.filter((b) => filter.value === 'all' ? true : filter.value === 'pending' ? b.status === 'pending' : b.check_out >= today && b.status !== 'cancelled'));

const fmt = (d) => new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' });
const money = (n) => n == null ? '—' : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n);
const statusStyle = (s) => ({ pending: 'bg-amber-500/15 text-amber-300', confirmed: 'bg-emerald-500/15 text-emerald-300', seated: 'bg-brand-500/15 text-brand-400', cancelled: 'bg-white/10 text-slate-500', noshow: 'bg-rose-500/15 text-rose-300' }[s] || 'bg-white/10 text-slate-400');
const statusLabel = (s) => ({ pending: 'En attente', confirmed: 'Confirmée', seated: 'Installés', cancelled: 'Annulée', noshow: 'No-show' }[s] || s);

const setTable = (r, status) => router.patch(route('reservations.table.update', r.id), { status }, { preserveScroll: true });
const setStay = (b, status) => router.patch(route('reservations.stay.update', b.id), { status }, { preserveScroll: true });

const add = ref(null);
const tf = reactive({ site_slug: props.sites[0]?.slug || '', date: today, time: '20:00', covers: 2, name: '', phone: '', email: '', notes: '' });
const sf = reactive({ site_slug: props.sites[0]?.slug || '', check_in: today, check_out: '', guests: 2, total: '', name: '', phone: '', email: '', notes: '' });
const submitTable = () => router.post(route('reservations.table.store'), tf, { preserveScroll: true, onSuccess: () => { add.value = null; tf.name = ''; tf.phone = ''; } });
const submitStay = () => router.post(route('reservations.stay.store'), { ...sf, total: sf.total === '' ? null : Number(sf.total) }, { preserveScroll: true, onSuccess: () => { add.value = null; sf.name = ''; sf.phone = ''; } });
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
                <div class="flex gap-2">
                    <button @click="add = 'table'" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-white/25">+ Table</button>
                    <button @click="add = 'stay'" class="btn-brand text-sm">+ Séjour</button>
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
            </div>
            <div class="ml-auto flex gap-1 text-xs font-semibold">
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

        <!-- Séjours -->
        <div v-else class="mt-4 space-y-2">
            <div v-for="b in stays" :key="b.id" class="glass flex flex-wrap items-center gap-4 rounded-2xl p-4">
                <div class="w-40 shrink-0"><p class="font-display font-bold text-white">{{ fmt(b.check_in) }} → {{ fmt(b.check_out) }}</p><p class="text-xs text-slate-400">{{ b.nights }} nuit{{ b.nights>1?'s':'' }} · {{ b.guests }} pers.</p></div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white">{{ b.name }} <span v-if="b.room" class="text-slate-400">· {{ b.room }}</span></p>
                    <p class="text-xs text-slate-400">{{ b.phone }}<span v-if="b.email"> · {{ b.email }}</span><span v-if="sites.length>1"> · {{ b.site }}</span><span v-if="b.source!=='website'"> · {{ b.source }}</span></p>
                    <p v-if="b.notes" class="mt-1 text-xs text-slate-300">“{{ b.notes }}”</p>
                </div>
                <div class="text-right"><p class="font-display font-bold text-white">{{ money(b.total) }}</p><span class="chip" :class="statusStyle(b.status)">{{ statusLabel(b.status) }}</span></div>
                <div class="flex gap-1.5 text-xs font-semibold">
                    <button v-if="b.status==='pending'" @click="setStay(b,'confirmed')" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-emerald-300">Confirmer</button>
                    <button v-if="b.status!=='cancelled'" @click="setStay(b,'cancelled')" class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-400 hover:text-rose-300">Annuler</button>
                </div>
            </div>
            <div v-if="!stays.length" class="glass rounded-2xl p-10 text-center text-slate-400">Aucun séjour. Activez le module <strong class="text-white">Chambres & Calendrier</strong> pour recevoir des demandes de séjour.</div>
        </div>

        <!-- Ajout manuel -->
        <Transition name="fade">
            <div v-if="add" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur" @click="add=null">
                <div class="glass w-full max-w-lg rounded-2xl bg-ink-800 p-6" @click.stop>
                    <h3 class="font-display text-lg font-bold text-white">{{ add==='table' ? 'Nouvelle réservation de table' : 'Nouveau séjour' }}</h3>
                    <form v-if="add==='table'" @submit.prevent="submitTable" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-if="sites.length>1" v-model="tf.site_slug" class="field sm:col-span-2"><option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}</option></select>
                        <input type="date" v-model="tf.date" class="field" required /><input type="time" v-model="tf.time" class="field" required />
                        <input v-model="tf.name" class="field" placeholder="Nom" required /><input type="number" v-model="tf.covers" class="field" min="1" placeholder="Couverts" />
                        <input v-model="tf.phone" class="field" placeholder="Téléphone" /><input v-model="tf.email" type="email" class="field" placeholder="Email" />
                        <input v-model="tf.notes" class="field sm:col-span-2" placeholder="Notes" />
                        <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" @click="add=null" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Annuler</button><button class="btn-brand text-sm">Enregistrer</button></div>
                    </form>
                    <form v-else @submit.prevent="submitStay" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-if="sites.length>1" v-model="sf.site_slug" class="field sm:col-span-2"><option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}</option></select>
                        <input type="date" v-model="sf.check_in" class="field" required /><input type="date" v-model="sf.check_out" class="field" required />
                        <input v-model="sf.name" class="field" placeholder="Nom" required /><input type="number" v-model="sf.guests" class="field" min="1" placeholder="Personnes" />
                        <input v-model="sf.phone" class="field" placeholder="Téléphone" /><input v-model="sf.email" type="email" class="field" placeholder="Email" />
                        <input type="number" v-model="sf.total" class="field" placeholder="Total (€)" /><input v-model="sf.notes" class="field" placeholder="Notes" />
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
