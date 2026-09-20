<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    invoices: { type: Array, default: () => [] },
    totalTtc: { type: Number, default: 0 },
    isAdmin: { type: Boolean, default: false },
});

const fmt = (n) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n || 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const statusStyle = (s) => ({
    paid: 'bg-emerald-500/15 text-emerald-300',
    pending: 'bg-amber-500/15 text-amber-300',
    refunded: 'bg-white/10 text-slate-400',
    failed: 'bg-rose-500/15 text-rose-300',
}[s] || 'bg-white/10 text-slate-400');
const statusLabel = (s) => ({ paid: 'Payée', pending: 'En attente', refunded: 'Remboursée', failed: 'Échouée' }[s] || s);
</script>

<template>
    <Head title="Mes factures" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">Mes factures</h1>
                    <p class="mt-1 text-sm text-slate-400">Historique de vos paiements et abonnements.</p>
                </div>
                <div class="glass rounded-2xl px-5 py-3 text-right">
                    <p class="text-xs text-slate-400">Total réglé</p>
                    <p class="font-display text-2xl font-bold text-white">{{ fmt(totalTtc) }}</p>
                </div>
            </div>
        </template>

        <div v-if="invoices.length" class="glass overflow-hidden rounded-2xl">
            <table class="w-full text-sm">
                <thead class="border-b border-white/[0.06] text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Facture</th>
                        <th class="px-5 py-3 font-semibold">Objet</th>
                        <th class="hidden px-5 py-3 font-semibold sm:table-cell">Date</th>
                        <th class="px-5 py-3 text-right font-semibold">Montant TTC</th>
                        <th class="px-5 py-3 text-center font-semibold">Statut</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-for="i in invoices" :key="i.id" class="transition hover:bg-white/[0.02]">
                        <td class="px-5 py-4 font-mono text-xs text-slate-300">{{ i.number }}</td>
                        <td class="px-5 py-4">
                            <span class="text-white">{{ i.label }}</span>
                            <span v-if="isAdmin && i.site_slug" class="block text-xs text-slate-500">{{ i.site_slug }}</span>
                        </td>
                        <td class="hidden px-5 py-4 text-slate-400 sm:table-cell">{{ fmtDate(i.issued_at) }}</td>
                        <td class="px-5 py-4 text-right font-semibold text-white">{{ fmt(i.amount_ttc) }}</td>
                        <td class="px-5 py-4 text-center"><span class="chip" :class="statusStyle(i.status)">{{ statusLabel(i.status) }}</span></td>
                        <td class="px-5 py-4 text-right">
                            <a :href="route('invoices.show', i.id)" target="_blank" rel="noopener" class="text-sm font-semibold text-brand-400 hover:text-brand-300">PDF ↗</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="glass rounded-2xl p-10 text-center">
            <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full bg-white/5 text-2xl">🧾</div>
            <p class="font-display text-lg font-bold text-white">Aucune facture pour l'instant</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-400">Vos factures apparaîtront ici dès votre première mise en ligne.</p>
        </div>
    </AuthenticatedLayout>
</template>
