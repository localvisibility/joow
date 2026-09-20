<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    leads: Array,
    isAdmin: { type: Boolean, default: false },
});

const filter = ref('all');
const tabs = [
    { key: 'all', label: 'Toutes' },
    { key: 'new', label: 'Nouvelles' },
    { key: 'read', label: 'Traitées' },
    { key: 'archived', label: 'Archivées' },
];

const shown = computed(() => filter.value === 'all' ? props.leads : props.leads.filter(l => l.status === filter.value));
const newCount = computed(() => props.leads.filter(l => l.status === 'new').length);

const typeLabel = (t) => ({ reservation: 'Réservation', rdv: 'Rendez-vous', devis: 'Devis', contact: 'Contact' }[t] || t);
const typeStyle = (t) => ({
    reservation: 'bg-rose-500/15 text-rose-300',
    rdv: 'bg-teal-500/15 text-teal-300',
    devis: 'bg-amber-500/15 text-amber-300',
    contact: 'bg-white/10 text-slate-300',
}[t] || 'bg-white/10 text-slate-300');

const fmtDate = (d) => new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
const setStatus = (lead, status) => router.patch(route('leads.update', lead.id), { status }, { preserveScroll: true });
</script>

<template>
    <Head title="Demandes" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-display text-3xl font-bold tracking-tight text-white">Demandes reçues</h1>
                <p class="mt-1 text-sm text-slate-400">Réservations, rendez-vous et devis envoyés depuis vos sites.</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap gap-2">
            <button v-for="t in tabs" :key="t.key" @click="filter = t.key"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                :class="filter === t.key ? 'bg-brand-gradient text-white shadow-glow' : 'glass text-slate-300 hover:text-white'">
                {{ t.label }}<span v-if="t.key === 'new' && newCount" class="ml-1.5 rounded-full bg-white/20 px-1.5 text-xs">{{ newCount }}</span>
            </button>
        </div>

        <div class="space-y-3">
            <div v-for="l in shown" :key="l.id" class="glass rounded-2xl p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="chip" :class="typeStyle(l.type)">{{ typeLabel(l.type) }}</span>
                            <span v-if="l.status === 'new'" class="chip bg-emerald-500/15 text-emerald-300">Nouveau</span>
                            <span class="text-xs text-slate-500">{{ fmtDate(l.created_at) }}</span>
                            <span v-if="isAdmin" class="text-xs text-slate-500">· {{ l.site_name }}</span>
                        </div>
                        <p class="mt-2 font-semibold text-white">{{ l.name || 'Sans nom' }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-4 text-sm text-slate-400">
                            <a v-if="l.phone" :href="`tel:${l.phone}`" class="hover:text-white">📞 {{ l.phone }}</a>
                            <a v-if="l.email" :href="`mailto:${l.email}`" class="hover:text-white">✉️ {{ l.email }}</a>
                        </div>
                        <p v-if="l.message" class="mt-3 rounded-xl bg-white/[0.03] p-3 text-sm text-slate-300">{{ l.message }}</p>
                        <div v-if="l.payload && Object.keys(l.payload).length" class="mt-3 flex flex-wrap gap-2">
                            <span v-for="(v, k) in l.payload" :key="k" class="chip bg-white/[0.06] text-slate-300">{{ k }} : {{ v }}</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button v-if="l.status !== 'read'" @click="setStatus(l, 'read')" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:border-white/25">Traité</button>
                        <button v-if="l.status !== 'archived'" @click="setStatus(l, 'archived')" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-400 transition hover:border-white/25">Archiver</button>
                        <button v-if="l.status === 'archived'" @click="setStatus(l, 'new')" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-400 transition hover:border-white/25">Rétablir</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="shown.length === 0" class="glass mt-6 rounded-2xl p-10 text-center">
            <p class="font-display text-lg font-bold text-white">Aucune demande {{ filter !== 'all' ? 'dans cette catégorie' : 'pour l\'instant' }}</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-400">Les demandes envoyées depuis le formulaire de vos sites apparaîtront ici, et vous serez notifié par email.</p>
        </div>
    </AuthenticatedLayout>
</template>
