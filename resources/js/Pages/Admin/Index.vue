<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    tab: String, q: String, days: Number,
    kpis: Object, data: Object, impersonating: Boolean,
});

const page = usePage();
const tabs = [
    ['overview', 'Vue d\'ensemble'], ['subscriptions', 'Abonnements'], ['clients', 'Clients'],
    ['sites', 'Sites'], ['emails', 'Emails'], ['invoices', 'Factures'], ['domains', 'Domaines'], ['ai', 'IA & coûts'],
];
const search = ref(props.q || '');
const qs = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
const status = ref(qs.get('status') || '');
const f = ref(qs.get('f') || '');

const go = (extra = {}) => router.get(route('admin.index'), { tab: props.tab, days: props.days, q: search.value || undefined, status: status.value || undefined, f: f.value || undefined, ...extra }, { preserveState: true, preserveScroll: true });

const eur = (n) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: n % 1 ? 2 : 0 }).format(n || 0);
const date = (d) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—';
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
const ACT = [['preview', '👁', 'Aperçu ouvert'], ['fullpage', '🌐', 'Site vu en plein écran'], ['email_click', '📧', 'Lien email cliqué'], ['studio', '✏️', 'Studio ouvert'], ['edit', '🛠', 'Modifications']];
const ago = (d) => { if (!d) return ''; const m = Math.round((Date.now() - new Date(d)) / 60000); return m < 60 ? `il y a ${m} min` : m < 1440 ? `il y a ${Math.round(m / 60)} h` : `il y a ${Math.round(m / 1440)} j`; };
// ── Écrire au prospect
const TEMPLATES = [
    { key: 'relance', label: 'Relance aperçu', subject: '{prenom}, votre site {site} est prêt', link: 'preview',
        message: "Bonjour {prenom},\n\nJ'ai vu que vous aviez créé le site de {site} sur Joow. Il est prêt et vous pouvez le consulter à tout moment.\n\nSi vous voulez changer un texte, une photo ou ajouter une page, je peux vous aider directement : répondez simplement à cet email.\n\nBelle journée," },
    { key: 'studio', label: 'Aide Studio', subject: 'Besoin d\'un coup de main pour {site} ?', link: 'studio',
        message: "Bonjour {prenom},\n\nVous avez commencé à personnaliser le site de {site}, bravo ! Le bouton ci-dessous vous ramène directement dans votre Studio, sans mot de passe.\n\nUne question, une modification qui coince ? Répondez à cet email, je m'en occupe.\n\nÀ bientôt," },
    { key: 'online', label: 'Mise en ligne', subject: 'Mettez {site} en ligne aujourd\'hui', link: 'hosting',
        message: "Bonjour {prenom},\n\nVotre site {site} n'attend plus que vous : nom de domaine inclus, certificat sécurisé et modifications illimitées, sans engagement.\n\nLe bouton ci-dessous vous amène directement à la mise en ligne. Si vous avez la moindre question, répondez à cet email.\n\nÀ très vite," },
    { key: 'libre', label: 'Message libre', subject: '', link: 'none', message: 'Bonjour {prenom},\n\n' },
];
const mail = reactive({ open: false, site: null, subject: '', message: '', link: 'preview', sending: false });
const openMail = (r, t = TEMPLATES[0]) => Object.assign(mail, { open: true, site: r, subject: t.subject, message: t.message, link: t.link, sending: false });
const useTemplate = (t) => Object.assign(mail, { subject: t.subject, message: t.message, link: t.link });
const preview = (txt) => (txt || '').replaceAll('{prenom}', (mail.site?.owner_name || '').split(' ')[0] || '').replaceAll('{site}', mail.site?.name || '').replaceAll('{ville}', '').replace(/ +([,.!?])/g, '$1');
const sendMail = () => {
    mail.sending = true;
    router.post(route('admin.sites.email', mail.site.id), { subject: mail.subject, message: mail.message, link: mail.link }, {
        preserveScroll: true, onSuccess: () => { mail.open = false; }, onFinish: () => { mail.sending = false; },
    });
};
const chatSite = ref(null);
const aiAsks = (r) => (r.ai_chat || []).filter((m) => m.role === 'user').length;
const lastSeen = (r) => Object.values(r.activity || {}).map((a) => a.last).filter(Boolean).sort().pop();
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
        <form v-if="['subscriptions', 'clients', 'sites', 'emails', 'invoices'].includes(tab)" class="mt-5 flex flex-wrap items-center gap-2" @submit.prevent="go()">
            <input v-model="search" type="search" :placeholder="tab === 'emails' ? 'Email ou objet…' : 'Nom, slug ou email…'" class="w-72 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-400 focus:ring-0" />
            <select v-if="tab === 'sites'" v-model="status" @change="go()" class="rounded-xl border border-white/10 bg-ink-900 px-3 py-2 text-sm text-white">
                <option value="">Tous les statuts</option>
                <option v-for="(n, s) in data.statuses" :key="s" :value="s">{{ s }} ({{ n }})</option>
            </select>
            <select v-if="tab === 'sites'" v-model="f" @change="go()" class="rounded-xl border border-white/10 bg-ink-900 px-3 py-2 text-sm text-white">
                <option value="">Tous les prospects</option>
                <option value="email">A laissé son email</option>
                <option value="noemail">Sans email</option>
                <option value="studio">A ouvert le Studio</option>
                <option value="checkout">A ouvert le paiement</option>
                <option value="multi">Crée plusieurs sites</option>
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
                    <tr><th class="px-4 py-3">Site · métier</th><th class="px-3 py-3">Email</th><th class="px-3 py-3">Activité</th><th class="px-3 py-3">Campagne</th><th class="px-3 py-3">Statut</th><th class="px-3 py-3">Créé</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-white/[0.02]">
                        <td class="max-w-[14rem] px-4 py-3"><a :href="r.url" target="_blank" class="line-clamp-2 font-semibold text-white hover:text-brand-300" :title="r.name">{{ r.name }}</a><span class="block truncate text-xs text-slate-500">{{ r.sector || '—' }}</span><span v-if="r.multi > 1" class="chip mt-1 bg-orange-500/15 text-orange-300" title="Sites créés par le même appareil ou le même email">⚠ {{ r.multi }} sites même personne</span></td>
                                                <td class="max-w-[13rem] px-3 py-3"><span v-if="r.email" class="block truncate text-slate-200" :title="r.email">{{ r.email }}</span><span v-else class="text-xs text-slate-500">pas d'email</span></td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="[k, ic, label] in ACT" :key="k" v-show="r.activity?.[k]" class="chip bg-white/[0.06] text-slate-200" :title="`${label} · ${r.activity?.[k]?.n} fois · dernière ${ago(r.activity?.[k]?.last)}`">{{ ic }} {{ r.activity?.[k]?.n }}</span>
                                <button v-if="aiAsks(r)" type="button" @click="chatSite = r" class="chip bg-violet-500/15 text-violet-300 hover:bg-violet-500/25" title="Voir la conversation avec l'assistant IA">🤖 {{ aiAsks(r) }}</button>
                                <span v-if="r.mails" class="chip bg-sky-500/15 text-sky-300" :title="r.mails.last.map((m) => (m.opened ? '✓ ouvert ' : (m.tracked ? '· non ouvert ' : '· (non suivi) ')) + ago(m.at) + ' · ' + m.subject).join('\n')">📬 {{ r.mails.opened }}/{{ r.mails.sent }}</span>
                                <span v-if="r.messages?.length" class="chip bg-brand-500/15 text-brand-300" :title="r.messages.map((m) => ago(m.at) + ' · ' + m.subject).join('\n')">📨 {{ r.messages.length }}</span>
                                <span v-if="r.checkout_at" class="chip bg-amber-500/15 text-amber-300" :title="'Page de paiement ouverte ' + ago(r.checkout_at)">💳</span>
                                <span v-if="!Object.keys(r.activity || {}).length && !r.checkout_at && !r.mails && !aiAsks(r)" class="text-xs text-slate-600">aucune</span>
                            </div>
                            <span v-if="lastSeen(r)" class="mt-1 block text-[11px] text-slate-500">vu {{ ago(lastSeen(r)) }}</span>
                        </td>
                        <td class="max-w-[7rem] truncate px-3 py-3 text-slate-400" :title="r.campaign || 'direct'">{{ r.campaign || 'direct' }}</td>
                        <td class="px-3 py-3"><span class="chip" :class="statusStyle(r.status)">{{ r.status }}</span><span v-if="r.error" class="mt-1 block max-w-xs text-xs text-rose-300/90" :title="r.error">{{ r.error }}</span></td>
                        <td class="whitespace-nowrap px-3 py-3 text-slate-400">{{ date(r.created_at) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-1">
                                <button v-if="r.email" @click="openMail(r)" class="rounded-lg bg-brand-500/15 px-2.5 py-1 text-xs font-semibold text-brand-300 hover:bg-brand-500/25" title="Écrire au prospect">✉ Écrire</button>
                                <Link :href="route('sites.editor', r.slug)" class="rounded-lg bg-white/[0.06] px-2.5 py-1 text-xs font-semibold text-slate-200 hover:bg-white/10" title="Ouvrir dans le Studio">Studio</Link>
                                <button v-if="!['paid', 'published'].includes(r.status)" @click="destroy(r)" class="rounded-lg bg-rose-500/10 px-2 py-1 text-xs text-rose-300 hover:bg-rose-500/20" title="Supprimer l'aperçu">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="7" class="px-5 py-10 text-center text-slate-500">Aucun site.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Emails -->
        <div v-else-if="tab === 'emails'" class="mt-5">
            <p class="mb-3 text-sm text-slate-400">30 derniers jours : <span class="font-semibold text-white">{{ data.sent_30 }}</span> envoyés · <span class="font-semibold text-white">{{ data.opened_30 }}</span> ouverts<span v-if="data.tracked_30"> ({{ Math.round(data.opened_30 / data.tracked_30 * 100) }} % des emails suivis)</span></p>
            <div class="glass overflow-x-auto rounded-2xl">
                <table class="w-full text-sm">
                    <thead class="border-b border-white/[0.06] text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">Destinataire</th><th class="px-3 py-3">Objet</th><th class="px-3 py-3">Envoyé</th><th class="px-5 py-3">Ouverture</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="m in rows" :key="m.id" class="hover:bg-white/[0.02]">
                            <td class="max-w-[14rem] truncate px-5 py-3 text-slate-200" :title="m.to">{{ m.to }}</td>
                            <td class="max-w-md truncate px-3 py-3 text-slate-300" :title="m.subject">{{ m.subject }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-400">{{ ago(m.sent_at) }}</td>
                            <td class="whitespace-nowrap px-5 py-3"><span v-if="!m.tracked" class="text-xs text-slate-600" title="Envoyé avant la mise en place du suivi">non suivi</span><span v-else-if="m.opened_at" class="chip bg-emerald-500/15 text-emerald-300">✓ ouvert {{ ago(m.opened_at) }}<span v-if="m.opens > 1"> · {{ m.opens }}×</span></span><span v-else class="text-xs text-slate-500">non ouvert</span></td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="4" class="px-5 py-10 text-center text-slate-500">Aucun email journalisé pour le moment.</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-500">L'ouverture est mesurée par une image invisible : certaines messageries (Apple Mail) l'ouvrent automatiquement, d'autres la bloquent. Prenez-la comme une tendance.</p>
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
            <div class="glass mt-4 overflow-x-auto rounded-2xl">
                <div class="flex items-baseline justify-between px-5 pt-4"><h2 class="font-semibold text-white">Demandes à l'assistant IA du Studio</h2><p class="text-xs text-slate-500">{{ data.requests.users }} site(s) · {{ data.requests.rows.length }} demande(s) sur {{ days }} j</p></div>
                <table class="mt-2 w-full text-sm">
                    <thead class="text-left text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-2">Quand</th><th class="px-3 py-2">Site</th><th class="px-3 py-2">Demande</th><th class="px-3 py-2">Modifications faites</th><th class="px-5 py-2 text-right">Crédits</th></tr></thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="(q, i) in data.requests.rows" :key="i" class="align-top">
                            <td class="whitespace-nowrap px-5 py-2 text-slate-400">{{ ago(q.at) }}</td>
                            <td class="max-w-[12rem] px-3 py-2"><Link v-if="q.slug" :href="route('sites.editor', q.slug)" class="block truncate font-semibold text-white hover:text-brand-300">{{ q.site }}</Link><span class="block truncate text-xs text-slate-500">{{ q.email || 'pas d\'email' }}</span></td>
                            <td class="max-w-sm px-3 py-2 text-slate-200">{{ q.message }}</td>
                            <td class="max-w-xs px-3 py-2"><span v-for="(a, k) in q.applied" :key="k" class="chip mb-1 mr-1 bg-emerald-500/15 text-emerald-300">{{ a }}</span><span v-if="!q.applied.length" class="text-xs text-slate-500">aucune (question)</span></td>
                            <td class="px-5 py-2 text-right text-slate-300">{{ q.credits || 'gratuit' }}</td>
                        </tr>
                        <tr v-if="!data.requests.rows.length"><td colspan="5" class="px-5 py-6 text-center text-slate-500">Aucune demande sur la période.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Conversation avec l'assistant IA -->
        <div v-if="chatSite" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4" @click.self="chatSite = null">
            <div class="glass flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl bg-ink-900 p-6">
                <div class="flex items-start justify-between gap-3">
                    <div><h3 class="font-display text-lg font-bold text-white">🤖 Assistant IA · {{ chatSite.name }}</h3><p class="text-sm text-slate-400">{{ chatSite.email || 'pas d\'email' }} · {{ aiAsks(chatSite) }} demande(s)</p></div>
                    <button @click="chatSite = null" class="text-slate-500 hover:text-white">✕</button>
                </div>
                <div class="mt-4 space-y-3 overflow-y-auto pr-1">
                    <div v-for="(m, i) in chatSite.ai_chat" :key="i" :class="m.role === 'user' ? 'ml-10' : 'mr-10'">
                        <div class="rounded-2xl px-4 py-3 text-sm" :class="m.role === 'user' ? 'bg-brand-500/20 text-white' : 'bg-white/[0.05] text-slate-300'">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide" :class="m.role === 'user' ? 'text-brand-300' : 'text-slate-500'">{{ m.role === 'user' ? 'Client' : 'Assistant' }}<span v-if="m.at" class="ml-2 font-normal normal-case">{{ ago(m.at) }}</span></p>
                            <p class="whitespace-pre-line">{{ m.text }}</p>
                            <div v-if="m.applied?.length" class="mt-2 flex flex-wrap gap-1"><span v-for="(a, k) in m.applied" :key="k" class="chip bg-emerald-500/15 text-emerald-300">✓ {{ a }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <Link :href="route('sites.editor', chatSite.slug)" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-200">Ouvrir dans le Studio</Link>
                    <button v-if="chatSite.email" @click="openMail(chatSite, TEMPLATES[1]); chatSite = null" class="btn-brand text-sm">✉ Lui écrire</button>
                </div>
            </div>
        </div>

        <!-- Écrire au prospect -->
        <div v-if="mail.open" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4" @click.self="mail.open = false">
            <div class="glass w-full max-w-2xl rounded-2xl bg-ink-900 p-6">
                <div class="flex items-start justify-between gap-3">
                    <div><h3 class="font-display text-lg font-bold text-white">Écrire à {{ mail.site.email }}</h3><p class="text-sm text-slate-400">{{ mail.site.name }} · les réponses arrivent dans votre boîte mail</p></div>
                    <button @click="mail.open = false" class="text-slate-500 hover:text-white">✕</button>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-for="t in TEMPLATES" :key="t.key" @click="useTemplate(t)" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:border-white/25">{{ t.label }}</button>
                </div>
                <label class="mt-4 block text-xs font-semibold text-slate-400">Objet<input v-model="mail.subject" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                <label class="mt-3 block text-xs font-semibold text-slate-400">Message<textarea v-model="mail.message" rows="9" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"></textarea></label>
                <p class="mt-1 text-[11px] text-slate-500">Raccourcis : {prenom} · {site} · {ville}</p>
                <label class="mt-3 block text-xs font-semibold text-slate-400">Bouton dans l'email
                    <select v-model="mail.link" class="mt-1 w-full rounded-xl border border-white/10 bg-ink-900 px-3 py-2 text-sm text-white">
                        <option value="preview">Voir mon site (aperçu)</option>
                        <option value="studio">Ouvrir mon site dans le Studio (connexion automatique)</option>
                        <option value="hosting">Mettre mon site en ligne (connexion automatique)</option>
                        <option value="none">Aucun bouton</option>
                    </select>
                </label>
                <details class="mt-3 rounded-xl border border-white/[0.06] p-3 text-sm"><summary class="cursor-pointer text-xs font-semibold text-slate-400">Aperçu du texte envoyé</summary>
                    <p class="mt-2 font-semibold text-white">{{ preview(mail.subject) }}</p><p class="mt-1 whitespace-pre-line text-slate-300">{{ preview(mail.message) }}</p></details>
                <div v-if="mail.site.mails" class="mt-3 rounded-xl border border-white/[0.06] p-3 text-xs">
                    <p class="font-semibold text-slate-400">Emails déjà reçus ({{ mail.site.mails.opened }} ouvert{{ mail.site.mails.opened > 1 ? 's' : '' }} sur {{ mail.site.mails.sent }})</p>
                    <p v-for="(m, i) in mail.site.mails.last" :key="i" class="mt-1 flex justify-between gap-3"><span class="truncate text-slate-300">{{ m.subject }}</span><span class="shrink-0" :class="m.opened ? 'text-emerald-300' : 'text-slate-500'">{{ m.opened ? '✓ ouvert' : (m.tracked ? 'non ouvert' : 'non suivi') }} · {{ ago(m.at) }}</span></p>
                </div>
                <p v-if="page.props.errors?.message || page.props.errors?.subject" class="mt-3 text-sm text-rose-300">{{ page.props.errors.message || page.props.errors.subject }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button @click="mail.open = false" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Annuler</button>
                    <button @click="sendMail" :disabled="mail.sending || !mail.subject.trim() || !mail.message.trim()" class="btn-brand text-sm">{{ mail.sending ? 'Envoi…' : 'Envoyer' }}</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
