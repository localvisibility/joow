<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ sites: Array, current: String, days: Number, series: Array, kpi: Object, recent: Array });

const go = (params) => router.get(route('stats.index'), { site: props.current || undefined, days: props.days, ...params }, { preserveState: false });

const hover = ref(null);
const W = 900, H = 260, P = { l: 36, r: 12, t: 12, b: 28 };
const max = computed(() => Math.max(5, ...props.series.map((d) => Math.max(d.views, d.leads, d.reservations))));
const x = (i) => P.l + (i * (W - P.l - P.r)) / Math.max(1, props.series.length - 1);
const y = (v) => H - P.b - (v / max.value) * (H - P.t - P.b);
const path = (key) => props.series.map((d, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(d[key]).toFixed(1)}`).join(' ');
const area = (key) => `${path(key)} L${x(props.series.length - 1).toFixed(1)},${H - P.b} L${x(0).toFixed(1)},${H - P.b} Z`;
const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((t) => ({ y: y(t * max.value), v: Math.round(t * max.value) })));
const labels = computed(() => props.series.filter((_, i) => i % Math.ceil(props.series.length / 8) === 0 || i === props.series.length - 1));
const fmtDay = (d) => new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });

const tiles = computed(() => [
    { label: 'Visites', value: props.kpi.views, color: 'text-white', icon: '👁️' },
    { label: 'Demandes', value: props.kpi.leads, color: 'text-brand-400', icon: '📩' },
    { label: 'Réservations', value: props.kpi.reservations, color: 'text-emerald-300', icon: '📅' },
    { label: 'Taux de conversion', value: props.kpi.conversion + ' %', color: 'text-amber-300', icon: '🎯' },
    { label: 'Conversations IA', value: props.kpi.bot, color: 'text-violet-300', icon: '🤖' },
]);
</script>

<template>
    <Head title="Statistiques" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">Statistiques</h1>
                    <p class="mt-1 text-sm text-slate-400">Visites, demandes et réservations, jour après jour.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <select v-if="sites.length > 1" :value="current || ''" @change="go({ site: $event.target.value || undefined })" class="field w-56"><option value="">Tous mes sites</option><option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}</option></select>
                    <div class="glass flex rounded-xl p-1 text-sm font-semibold">
                        <button v-for="d in [7,30,90]" :key="d" @click="go({ days: d })" class="rounded-lg px-3 py-1.5" :class="days===d ? 'bg-white/10 text-white' : 'text-slate-400'">{{ d }} j</button>
                    </div>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <div v-for="t in tiles" :key="t.label" class="glass rounded-2xl p-5">
                <p class="text-sm text-slate-400">{{ t.icon }} {{ t.label }}</p>
                <p class="mt-1 font-display text-3xl font-bold" :class="t.color">{{ t.value }}</p>
            </div>
        </div>

        <div class="glass mt-6 rounded-2xl p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <p class="font-display font-bold text-white">Évolution sur {{ days }} jours</p>
                <div class="flex gap-4 text-xs text-slate-400"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-400"></span>Visites</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fuchsia-400"></span>Demandes</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>Réservations</span></div>
            </div>
            <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" @mouseleave="hover = null">
                <defs><linearGradient id="gv" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#818cf8" stop-opacity=".35"/><stop offset="1" stop-color="#818cf8" stop-opacity="0"/></linearGradient></defs>
                <g v-for="t in ticks" :key="t.v"><line :x1="P.l" :x2="W - P.r" :y1="t.y" :y2="t.y" stroke="rgba(255,255,255,.06)"/><text :x="P.l - 6" :y="t.y + 4" text-anchor="end" font-size="10" fill="#64748b">{{ t.v }}</text></g>
                <path :d="area('views')" fill="url(#gv)"/>
                <path :d="path('views')" fill="none" stroke="#818cf8" stroke-width="2.5" stroke-linejoin="round"/>
                <path :d="path('leads')" fill="none" stroke="#e879f9" stroke-width="2" stroke-linejoin="round"/>
                <path :d="path('reservations')" fill="none" stroke="#34d399" stroke-width="2" stroke-linejoin="round"/>
                <g v-for="(d, i) in series" :key="d.day">
                    <rect :x="x(i) - 8" :y="P.t" width="16" :height="H - P.t - P.b" fill="transparent" @mouseenter="hover = i"/>
                    <circle v-if="hover === i" :cx="x(i)" :cy="y(d.views)" r="4" fill="#818cf8"/>
                </g>
                <text v-for="d in labels" :key="d.day" :x="x(series.indexOf(d))" :y="H - 8" text-anchor="middle" font-size="10" fill="#64748b">{{ fmtDay(d.day) }}</text>
            </svg>
            <p v-if="hover !== null" class="mt-2 text-center text-xs text-slate-300">{{ fmtDay(series[hover].day) }} · {{ series[hover].views }} visites · {{ series[hover].leads }} demandes · {{ series[hover].reservations }} réservations</p>
            <p v-else-if="!kpi.views" class="mt-3 text-center text-xs text-slate-500">Les visites sont comptées dès qu'un site est (re)publié avec la mesure d'audience intégrée.</p>
        </div>

        <div v-if="recent.length" class="glass mt-6 rounded-2xl p-5">
            <p class="mb-3 font-display font-bold text-white">Dernières réservations</p>
            <div v-for="(r,i) in recent" :key="i" class="flex items-center justify-between border-t border-white/[0.05] py-2 text-sm"><span class="text-slate-200">{{ r.label }}</span><span class="text-slate-400">{{ r.when }}</span><span class="chip bg-white/10 text-slate-300">{{ r.status }}</span></div>
        </div>
    </AuthenticatedLayout>
</template>
