<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tab: String, q: String, days: Number,
    kpis: Object, data: Object, impersonating: Boolean,
});

const page = usePage();
const tabs = [
    ['overview', 'Vue d\'ensemble'], ['subscriptions', 'Abonnements'], ['clients', 'Clients'],
    ['sites', 'Sites'], ['invoices', 'Factures'], ['domains', 'Domaines'], ['ai', 'IA & coûts'],
];
const search = ref(props.q || '');
const status = ref(new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('status') || '');

const go = (extra = {}) => router.get(route('admin.index'), { tab: props.tab, days: props.days, q: search.value || undefined, status: status.value || undefined, ...extra }, { preserveState: true, preserveScroll: true });

const eur = (n) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: n % 1 ? 2 : 0 }).format(n || 0);
const date = (d) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: '2-digit' }) : '—';
const pct = (n) => `${String(n ?? 0).replace('.', ',')} %`;

const subLabel = { active: 'Actif', trialing: 'Essai', past_due: 'Impayé', canceled: 'Résilié', incomplete: 'Incomplet', unpaid: 'Impayé' };
const subStyle = (s) => ({ active: 'bg-emerald-500/15 text-emerald-300', trialing: 'bg-sky-500/15 text-sky-300', past_due: 'bg-rose-500/15 text-rose-300', unpaid: 'bg-rose-500/15 text-rose-300', canceled: 'bg-white/10 text-slate-400' }[s] || 'bg-white/10 text-slate-400');
const statusStyle = (s) => ({ published: 'bg-emerald-500/15 text-emerald-300', paid: 'bg-emerald-500/15 text-emerald-300', ready: 'bg-sky-500/15 text-sky-300', generating: 'bg-amber-500/15 text-amber-300', failed: 'bg-rose-500/15 text-rose-300' }[s] || 'bg-white/10 text-slate-400');

const cards = computed(() => [
    { label: 'MRR', value: eur(props.kpis.mrr), hint: `${props.kpis.active_pro} abonnés Pro actifs`, accent: true },
    { label: 'ARR', value: eur(props.kpis.arr), hint: 'MRR × 12' },
    { label: `CA encaissé · ${props.days} j`, value: eur(props.kpis.revenue_period), hint: 'HT, d\'après les factures' },
    { label: `Nouveaux payants · ${props.days} j`, value: props.kpis.new_paid, hint: `${props.kpis.generated} sites générés` },
    { label: 'Conversion', value: pct(props.kpis.conversion), hint: 'site généré → payé' },
    { label: `Churn · ${props.days} j`, value: pct(props.kpis.churn), hint: `${props.kpis.canceled_period} résiliation(s)` },
    { label: 'Impayés', value: props.kpis.past_due, hint: 'abonnements en défaut', warn: props.kpis.past_due > 0 },
    { label: 'Liberté', value: props.kpis.liberte_total, hint: `${eur(props.kpis.liberte_revenue)} encaissés au total` },
    { label: 'ARPU', value: eur(props.kpis.arpu), hint: `${props.kpis.clients} comptes clients` },
]);

const maxOf = (rows, k) => Math.max(1, ...rows.map((r) => r[k] || 0));
const series = computed(() => props.data?.series || []);
const funnelMax = computed(() => Math.max(1, ...(props.data?.funnel || []).map((f) => f.n)));

const busy = ref(null);
const confirmText = { cancel: 'Résilier cet abonnement à la fin de la période en cours ?', resume: 'Réactiver cet abonnement ?', cancel_now: 'Résilier IMMÉDIATEMENT cet abonnement ? Le site sera dépublié.' };
const subAction = (row, action) => {
    if (!confirm(`${row.name} · ${confirmText[action]}`)) return;
    busy.value = row.id;
    router.post(route('admin.subscription', row.id), { action }, { preserveScroll: true, onFinish: () => (busy.value = null) });
};
const destroy = (row) => {
    if (!confirm(`Supprimer définitivement l'aperçu « ${row.name} » ?`)) return;
    router.delete(route('admin.sites.destroy', row.id), { preserveScroll: true });
};
const impersonate = (u) => {
    if (!confirm(`Se connecter en tant que ${u.email} ? Un bandeau permet de revenir à votre compte.`)) return;
    router.post(route('admin.impersonate', u.id));
};

const exportCsv = (rows, name) => {
    if (!rows?.length) return;
    const cols = Object.keys(rows[0]);
    const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = [cols.join(';'), ...rows.map((r) => cols.map((c) => esc(r[c])).join(';'))].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    a.download = `joow-${name}-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
};
const rows = computed(() => props.data?.rows || []);
</script>

<template>
    <Head title="Back-office" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">Back-office Joow</h1>
                    <p class="mt-1 text-sm text-slate-400">Revenus, abonnements, clients et activité de la plateforme.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Période</span>
                    <button v-for="d in [7, 30, 90, 365]" :key="d" type="button" @click="go({ days: d })"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                        :class="days === d ? 'bg-brand-500 text-white' : 'bg-white/5 text-slate-300 hover:bg-white/10'">{{ d === 365 ? '1 an' : `${d} j` }}</button>
                </div>
            </div>
        </template>

        <div v-if="page.props.flash?.status" class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ page.props.flash.status }}</div>
        <div v-if="page.props.errors?.action" class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ page.props.errors.action }}</div>

        <!-- Indicateurs -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            <div v-for="c in cards" :key="c.label" class="glass rounded-2xl p-4"
                :class="c.accent ? 'ring-1 ring-brand-400/40' : c.warn ? 'ring-1 ring-rose-400/40' : ''">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ c.label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" :class="c.accent ? 'text-brand-300' : c.warn ? 'text-rose-300' : 'text-white'">{{ c.value }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ c.hint }}</p>
            </div>
        </div>

        <!-- Onglets -->
        <div class="mt-8 flex gap-1 overflow-x-auto border-b border-white/[0.06]">
            <Link v-for="[k, l] in tabs" :key="k" :href="route('admin.index', { tab: k, days })" preserve-scroll
                class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                :class="tab === k ? 'border-brand-400 text-white' : 'border-transparent text-slate-400 hover:text-white'">{{ l }}</Link>
        </div>

        <!-- Recherche -->
        <form v-if="['subscriptions', 'clients', 'sites', 'invoices'].includes(tab)" class="mt-5 flex flex-wrap items-center gap-2" @submit.prevent="go()">
            <input v-model="search" type="search" placeholder="Nom, slug ou email…" class="w-72 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-400 focus:ring-0" />
            <select v-if="tab === 'sites'" v-model="status" @change="go()" class="rounded-xl border border-white/10 bg-ink-900 px-3 py-2 text-sm text-white">
                <option value="">Tous les statuts</option>
                <option v-for="(n, s) in data.statuses" :key="s" :value="s">{{ s }} ({{ n }})</option>
            </select>
            <button class="rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">Rechercher</button>
            <button type="button" @click="exportCsv(rows, tab)" class="ml-auto rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">Exporter CSV</button>
        </form>

        <!-- Vue d'ensemble -->
        <template v-if="tab === 'overview'">
            <div class="mt-6 grid gap-4 lg:grid-cols-3">
                <div class="glass rounded-2xl p-5 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-white">Activité quotidienne</h2>
                        <div class="flex gap-4 text-xs text-slate-400">
                            <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-slate-500/60"></i>Générés</span>
                            <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-brand-400"></i>Payés</span>
                        </div>
                    </div>
                    <div class="mt-5 flex h-44 items-end gap-[2px]">
                        <div v-for="d in series" :key="d.date" class="group relative flex h-full flex-1 flex-col justify-end">
                            <div class="w-full rounded-t-sm bg-slate-500/40" :style="{ height: `${(d.generated / maxOf(series, 'generated')) * 100}%` }"></div>
                            <div v-if="d.paid" class="absolute bottom-0 w-full rounded-t-sm bg-brand-400" :style="{ height: `${(d.paid / maxOf(series, 'generated')) * 100}%`, minHeight: '3px' }"></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink-950 px-2.5 py-1.5 text-xs text-white shadow-xl ring-1 ring-white/10 group-hover:block">
                                {{ date(d.date) }} · {{ d.generated }} générés · {{ d.paid }} payés · {{ eur(d.revenue) }}
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex justify-between text-[11px] text-slate-500"><span>{{ date(series[0]?.date) }}</span><span>aujourd'hui</span></div>
                </div>

                <div class="glass rounded-2xl p-5">
                    <h2 class="font-semibold text-white">Entonnoir · {{ days }} j</h2>
                    <div class="mt-5 space-y-4">
                        <div v-for="(f, i) in data.funnel" :key="f.label">
                            <div class="flex justify-between text-sm"><span class="text-slate-300">{{ f.label }}</span>
                                <span class="font-semibold text-white">{{ f.n }}<span v-if="i" class="ml-1.5 text-xs font-normal text-slate-500">{{ data.funnel[0].n ? Math.round(f.n / data.funnel[0].n * 100) : 0 }} %</span></span></div>
                            <div class="mt-1.5 h-2 rounded-full bg-white/5"><div class="h-2 rounded-full bg-gradient-to-r from-brand-500 to-brand-300" :style="{ width: `${(f.n / funnelMax) * 100}%` }"></div></div>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3 border-t border-white/[0.06] pt-4 text-sm">
                        <div class="flex-1"><p class="text-xs text-slate-500">Pro</p><p class="font-display text-xl font-bold text-white">{{ data.plans.pro }}</p></div>
                        <div class="flex-1"><p class="text-xs text-slate-500">Liberté</p><p class="font-display text-xl font-bold text-white">{{ data.plans.liberte }}</p></div>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <div v-for="[key, title] in [['by_campaign', 'Par campagne'], ['by_source', 'Par source'], ['by_sector', 'Par métier']]" :key="key" class="glass overflow-hidden rounded-2xl">
                    <h2 class="px-5 pt-4 font-semibold text-white">{{ title }}</h2>
                    <table class="mt-2 w-full text-sm">
                        <thead class="text-left text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-2"></th><th class="px-2 py-2 text-right">Gén.</th><th class="px-2 py-2 text-right">Payés</th><th class="px-5 py-2 text-right">CA</th></tr></thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-for="r in data[key]" :key="r.key">
                                <td class="max-w-[10rem] truncate px-5 py-2 text-slate-300" :title="r.key">{{ r.key }}</td>
                                <td class="px-2 py-2 text-right text-slate-400">{{ r.generated }}</td>
                                <td class="px-2 py-2 text-right font-semibold text-white">{{ r.paid }}</td>
                                <td class="px-5 py-2 text-right text-slate-300">{{ eur(r.revenue) }}</td>
                            </tr>
                            <tr v-if="!data[key].length"><td colspan="4" class="px-5 py-6 text-center text-slate-500">Aucune donnée sur la période.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Abonnements -->
        <div v-else-if="tab === 'subscriptions'" class="glass mt-5 overflow-x-auto rounded-2xl">
            <table class="w-full text-sm">
                <thead class="border-b border-white/[0.06] text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Site</th><th class="px-3 py-3">Client</th><th class="px-3 py-3">Formule</th><th class="px-3 py-3">Statut</th><th class="px-3 py-3">Depuis</th><th class="px-3 py-3">Échéance</th><th class="px-3 py-3 text-right">MRR</th><th class="px-5 py-3 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-white/[0.02]">
                        <td class="px-5 py-3"><a :href="r.url" target="_blank" class="font-semibold text-white hover:text-brand-300">{{ r.name }}</a><span class="block text-xs text-slate-500">{{ r.domain || r.slug }}</span></td>
                        <td class="px-3 py-3 text-slate-300">{{ r.email || '—' }}</td>
                        <td class="px-3 py-3 text-slate-300">{{ r.plan === 'liberte' ? 'Liberté' : 'Pro' }}</td>
                        <td class="px-3 py-3">
                            <span v-if="r.plan === 'liberte'" class="chip bg-violet-500/15 text-violet-300">Payé</span>
                            <span v-else class="chip" :class="subStyle(r.sub)">{{ subLabel[r.sub] || r.sub || '—' }}</span>
                            <span v-if="r.cancel_at_end" class="chip ml-1 bg-amber-500/15 text-amber-300">fin programmée</span>
                        </td>
                        <td class="px-3 py-3 text-slate-400">{{ date(r.paid_at) }}</td>
                        <td class="px-3 py-3 text-slate-400">{{ date(r.period_end) }}</td>
                        <td class="px-3 py-3 text-right font-semibold text-white">{{ r.mrr ? eur(r.mrr) : '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <a v-if="r.stripe" :href="r.stripe" target="_blank" class="mr-2 text-xs text-slate-400 hover:text-white">Stripe ↗</a>
                            <template v-if="r.plan === 'pro' && r.sub !== 'canceled'">
                                <button v-if="r.cancel_at_end" :disabled="busy === r.id" @click="subAction(r, 'resume')" class="rounded-lg bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">Réactiver</button>
                                <button v-else :disabled="busy === r.id" @click="subAction(r, 'cancel')" class="rounded-lg bg-white/5 px-2.5 py-1 text-xs font-semibold text-slate-200 hover:bg-white/10">Résilier fin de période</button>
                                <button :disabled="busy === r.id" @click="subAction(r, 'cancel_now')" class="ml-1 rounded-lg bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-300 hover:bg-rose-500/25">Immédiat</button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="8" class="px-5 py-10 text-center text-slate-500">Aucun abonnement.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Clients -->
        <div v-else-if="tab === 'clients'" class="glass mt-5 overflow-x-auto rounded-2xl">
            <table class="w-full text-sm">
                <thead class="border-b border-white/[0.06] text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Client</th><th class="px-3 py-3">Inscrit</th><th class="px-3 py-3 text-right">Sites</th><th class="px-3 py-3 text-right">Payés</th><th class="px-3 py-3 text-right">MRR</th><th class="px-3 py-3"></th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-for="u in rows" :key="u.id" class="hover:bg-white/[0.02]">
                        <td class="px-5 py-3"><span class="font-semibold text-white">{{ u.name || '—' }}</span><span class="block text-xs text-slate-500">{{ u.email }}</span></td>
                        <td class="px-3 py-3 text-slate-400">{{ date(u.created_at) }}</td>
                        <td class="px-3 py-3 text-right text-slate-300">{{ u.sites }}</td>
                        <td class="px-3 py-3 text-right font-semibold text-white">{{ u.paid }}</td>
                        <td class="px-3 py-3 text-right text-slate-300">{{ u.mrr ? eur(u.mrr) : '—' }}</td>
                        <td class="px-3 py-3 text-xs">
                            <span v-if="u.admin" class="chip bg-brand-500/20 text-brand-300">admin</span>
                            <span v-if="u.google" class="chip ml-1 bg-white/10 text-slate-300">Google</span>
                            <span v-if="u.optout" class="chip ml-1 bg-white/10 text-slate-400">désabonné</span>
                        </td>
                        <td class="px-5 py-3 text-right"><button v-if="!u.admin" @click="impersonate(u)" class="rounded-lg bg-white/5 px-2.5 py-1 text-xs font-semibold text-slate-200 hover:bg-white/10">Se connecter en tant que</button></td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="7" class="px-5 py-10 text-center text-slate-500">Aucun client.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Sites -->
        <div v-else-if="tab === 'sites'" class="glass mt-5 overflow-x-auto rounded-2xl">
            <table class="w-full text-sm">
                <thead class="border-b border-white/[0.06] text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Site</th><th class="px-3 py-3">Métier</th><th class="px-3 py-3">Email</th><th class="px-3 py-3">Campagne</th><th class="px-3 py-3">Statut</th><th class="px-3 py-3">Créé</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-white/[0.02]">
                        <td class="px-5 py-3"><a :href="r.url" target="_blank" class="font-semibold text-white hover:text-brand-300">{{ r.name }}</a><span class="block text-xs text-slate-500">{{ r.slug }}</span></td>
                        <td class="px-3 py-3 text-slate-300">{{ r.sector || '—' }}</td>
                        <td class="px-3 py-3 text-slate-300">{{ r.email || '—' }}</td>
                        <td class="max-w-[9rem] truncate px-3 py-3 text-slate-400">{{ r.campaign || 'direct' }}</td>
                        <td class="px-3 py-3"><span class="chip" :class="statusStyle(r.status)">{{ r.status }}</span><span v-if="r.error" class="mt-1 block max-w-xs text-xs text-rose-300/90" :title="r.error">{{ r.error }}</span></td>
                        <td class="px-3 py-3 text-slate-400">{{ date(r.created_at) }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <Link :href="route('sites.editor', r.slug)" class="mr-2 text-xs text-slate-400 hover:text-white">Studio</Link>
                            <button v-if="!['paid', 'published'].includes(r.status)" @click="destroy(r)" class="rounded-lg bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-300 hover:bg-rose-500/20">Supprimer</button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="7" class="px-5 py-10 text-center text-slate-500">Aucun site.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Factures -->
        <div v-else-if="tab === 'invoices'" class="mt-5">
            <p class="mb-3 text-sm text-slate-400">Total HT des factures listées : <span class="font-semibold text-white">{{ eur(data.total_ht) }}</span></p>
            <div class="glass overflow-x-auto rounded-2xl">
                <table class="w-full text-sm">
                    <thead class="border-b border-white/[0.06] text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">N°</th><th class="px-3 py-3">Client</th><th class="px-3 py-3">Objet</th><th class="px-3 py-3">Date</th><th class="px-3 py-3 text-right">HT</th><th class="px-3 py-3 text-right">TTC</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="i in rows" :key="i.id" class="hover:bg-white/[0.02]">
                            <td class="px-5 py-3 font-mono text-xs text-slate-300">{{ i.number }}</td>
                            <td class="px-3 py-3 text-slate-300">{{ i.email }}<span v-if="i.site" class="block text-xs text-slate-500">{{ i.site }}</span></td>
                            <td class="max-w-xs truncate px-3 py-3 text-slate-400" :title="i.label">{{ i.label }}</td>
                            <td class="px-3 py-3 text-slate-400">{{ date(i.issued_at) }}</td>
                            <td class="px-3 py-3 text-right text-slate-300">{{ eur(i.ht) }}</td>
                            <td class="px-3 py-3 text-right font-semibold text-white">{{ eur(i.ttc) }}</td>
                            <td class="px-5 py-3 text-right"><a :href="route('invoices.show', i.id)" target="_blank" class="text-xs text-slate-400 hover:text-white">Voir ↗</a></td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="7" class="px-5 py-10 text-center text-slate-500">Aucune facture.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Domaines -->
        <div v-else-if="tab === 'domains'" class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="glass overflow-x-auto rounded-2xl">
                <h2 class="px-5 pt-4 font-semibold text-white">Domaines en service</h2>
                <table class="mt-2 w-full text-sm">
                    <thead class="text-left text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-2">Domaine</th><th class="px-3 py-2">Origine</th><th class="px-3 py-2">SSL</th><th class="px-5 py-2">Expire</th></tr></thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="d in data.custom" :key="d.domain">
                            <td class="px-5 py-2"><span class="text-white">{{ d.domain }}</span><span class="block text-xs text-slate-500">{{ d.site }}</span></td>
                            <td class="px-3 py-2 text-slate-400">{{ d.source || '—' }}</td>
                            <td class="px-3 py-2"><span class="chip" :class="d.ssl ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'">{{ d.ssl ? 'actif' : 'en attente' }}</span></td>
                            <td class="px-5 py-2 text-slate-400">{{ d.expires || '—' }}<span v-if="d.renewal?.auto_renew === false" class="block text-xs text-amber-300">renouvellement coupé</span></td>
                        </tr>
                        <tr v-if="!data.custom.length"><td colspan="4" class="px-5 py-6 text-center text-slate-500">Aucun domaine.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="glass overflow-x-auto rounded-2xl">
                <h2 class="px-5 pt-4 font-semibold text-white">Demandes et commandes</h2>
                <table class="mt-2 w-full text-sm">
                    <thead class="text-left text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-2">Domaine</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Statut</th><th class="px-5 py-2">Date</th></tr></thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="(d, i) in rows" :key="i">
                            <td class="px-5 py-2"><span class="text-white">{{ d.domain }}</span><span class="block text-xs text-slate-500">{{ d.site }} · {{ d.email }}</span><span v-if="d.notes" class="mt-1 block max-w-md text-xs" :class="d.status === 'failed' ? 'text-rose-300' : 'text-slate-400'">{{ d.notes }}</span></td>
                            <td class="px-3 py-2 text-slate-400">{{ d.type }}</td>
                            <td class="px-3 py-2"><span class="chip" :class="statusStyle(d.status === 'done' || d.status === 'active' ? 'published' : d.status)">{{ d.status }}</span></td>
                            <td class="px-5 py-2 text-slate-400">{{ date(d.created_at) }}</td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="4" class="px-5 py-6 text-center text-slate-500">Aucune demande.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- IA -->
        <div v-else-if="tab === 'ai'" class="mt-5">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div v-for="[l, v] in [['Générations', data.generations], ['Échecs', data.failed], ['Durée moyenne', data.avg_seconds ? `${data.avg_seconds} s` : '—'], ['Durée p90', data.p90_seconds ? `${data.p90_seconds} s` : '—'], ['Crédits consommés', data.credits_used], ['Coût IA estimé', eur(data.cost_estimate)]]" :key="l" class="glass rounded-2xl p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ l }}</p>
                    <p class="mt-1 font-display text-2xl font-bold text-white">{{ v }}</p>
                </div>
            </div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="glass rounded-2xl p-5">
                    <h2 class="font-semibold text-white">Crédits par usage · {{ days }} j</h2>
                    <div v-for="(n, r) in data.by_reason" :key="r" class="mt-3 flex justify-between text-sm"><span class="text-slate-300">{{ r }}</span><span class="font-semibold" :class="n < 0 ? 'text-rose-300' : 'text-emerald-300'">{{ n > 0 ? '+' : '' }}{{ n }}</span></div>
                    <p v-if="!Object.keys(data.by_reason || {}).length" class="mt-3 text-sm text-slate-500">Aucun mouvement.</p>
                    <p class="mt-4 border-t border-white/[0.06] pt-3 text-xs text-slate-500">Crédits vendus sur la période : {{ data.credits_sold }}</p>
                </div>
                <div class="glass rounded-2xl p-5">
                    <h2 class="font-semibold text-white">Dernières erreurs</h2>
                    <div v-for="(e, i) in data.recent_errors" :key="i" class="mt-3 text-sm"><span class="text-xs text-slate-500">{{ date(e.at) }}</span><p class="text-rose-200/90">{{ e.error || '(sans message)' }}</p></div>
                    <p v-if="!data.recent_errors.length" class="mt-3 text-sm text-slate-500">Aucune erreur sur la période.</p>
                    <a :href="route('admin.journal')" target="_blank" class="mt-4 inline-block text-xs text-slate-400 hover:text-white">Journal applicatif ↗</a>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
