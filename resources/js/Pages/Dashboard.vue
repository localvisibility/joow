<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stats: Object,
    sites: Array,
});

const q = ref('');
const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    if (!term) return props.sites;
    return props.sites.filter(s =>
        [s.name, s.city, s.sector, s.owner_email].join(' ').toLowerCase().includes(term)
    );
});

const statusStyle = (s) => ({
    paid: 'bg-emerald-500/15 text-emerald-300',
    published: 'bg-brand-500/15 text-brand-400',
    preview: 'bg-white/10 text-slate-400',
}[s] || 'bg-white/10 text-slate-400');

const statusLabel = (s) => ({ paid: 'Payé', published: 'En ligne', preview: 'Aperçu' }[s] || s);

const cards = computed(() => [
    { label: 'Sites', value: props.stats.sites, accent: 'text-white' },
    { label: 'Clients', value: props.stats.clients, accent: 'text-brand-400' },
    { label: 'Payés', value: props.stats.paid, accent: 'text-emerald-300' },
    { label: 'En ligne', value: props.stats.published, accent: 'text-violet-300' },
]);

const liveUrl = (s) => s.custom_domain
    ? `https://${s.custom_domain}`
    : `https://${s.subdomain || s.slug}.joow.fr`;
</script>

<template>
    <Head title="Sites" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">Vos sites</h1>
                    <p class="mt-1 text-sm text-slate-400">Le parc géré sur Joow, en direct.</p>
                </div>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a1 1 0 01-1.42 1.42l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                    <input v-model="q" type="search" placeholder="Rechercher un site, une ville…" class="field w-full pl-10 sm:w-80" />
                </div>
            </div>
        </template>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="c in cards" :key="c.label" class="glass rounded-2xl p-5">
                <div class="text-sm font-medium text-slate-400">{{ c.label }}</div>
                <div class="mt-1 font-display text-3xl font-bold" :class="c.accent">{{ c.value }}</div>
            </div>
        </div>

        <!-- Sites -->
        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="s in filtered" :key="s.id"
                class="glass glass-hover group rounded-2xl p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold text-white">{{ s.name }}</h3>
                        <p class="mt-0.5 truncate text-sm text-slate-400">{{ s.city || '—' }}</p>
                    </div>
                    <span class="chip shrink-0" :class="statusStyle(s.status)">{{ statusLabel(s.status) }}</span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span v-if="s.sector" class="chip bg-white/[0.06] text-slate-300 capitalize">{{ s.sector }}</span>
                    <span v-if="s.hosting_plan" class="chip bg-white/[0.06] text-slate-300 capitalize">{{ s.hosting_plan }}</span>
                    <span v-if="s.reviews_count" class="chip bg-amber-500/10 text-amber-300">★ {{ s.rating }} · {{ s.reviews_count }}</span>
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-white/[0.06] pt-4">
                    <span class="truncate text-xs text-slate-500">{{ s.owner_email || 'sans propriétaire' }}</span>
                    <a :href="liveUrl(s)" target="_blank" rel="noopener"
                        class="text-sm font-semibold text-brand-400 opacity-0 transition group-hover:opacity-100">
                        Voir →
                    </a>
                </div>
            </div>
        </div>

        <p v-if="filtered.length === 0" class="mt-10 text-center text-slate-500">Aucun site ne correspond.</p>
    </AuthenticatedLayout>
</template>
