<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    business: Object,
});

const lookup = useForm({ query: '' });
const submitLookup = () => lookup.post(route('sites.lookup'), { preserveScroll: true, preserveState: true });

const create = useForm({});
const generate = () => {
    const b = props.business;
    create.transform(() => ({
        place_id: b.place_id, name: b.name, sector: b.sector, city: b.city,
        address: b.address, phone: b.phone, rating: b.rating,
        reviews_count: b.reviews_count, maps_url: b.maps_url, lat: b.lat, lng: b.lng,
    })).post(route('sites.store'));
};
</script>

<template>
    <Head title="Nouveau site" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-3xl font-bold tracking-tight text-white">Créer un site</h1>
            <p class="mt-1 text-sm text-slate-400">À partir d'une fiche Google Business — le site se construit tout seul.</p>
        </template>

        <div class="mx-auto max-w-3xl">
            <!-- Recherche -->
            <div class="glass rounded-3xl p-6 sm:p-8">
                <label class="mb-2 block text-sm font-medium text-slate-300">Lien Google Maps ou « nom + ville »</label>
                <form @submit.prevent="submitLookup" class="flex flex-col gap-3 sm:flex-row">
                    <input v-model="lookup.query" type="text" class="field flex-1"
                        placeholder="https://maps.app.goo.gl/…  ou  « Plombier Dupont Lyon »" autofocus />
                    <button type="submit" class="btn-brand shrink-0" :class="{ 'opacity-60': lookup.processing }" :disabled="lookup.processing">
                        {{ lookup.processing ? 'Analyse…' : 'Analyser' }}
                    </button>
                </form>
                <p v-if="lookup.errors.query" class="mt-2 text-sm text-rose-400">{{ lookup.errors.query }}</p>
            </div>

            <!-- Aperçu de la fiche -->
            <div v-if="business" class="glass mt-6 overflow-hidden rounded-3xl">
                <div v-if="business.photos && business.photos.length" class="grid grid-cols-3 gap-1">
                    <img v-for="(p, i) in business.photos.slice(0, 3)" :key="i" :src="p" class="h-32 w-full object-cover" loading="lazy" />
                </div>

                <div class="p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-display text-2xl font-bold text-white">{{ business.name }}</h2>
                                <span class="chip bg-brand-500/15 capitalize text-brand-400">{{ business.sector }}</span>
                            </div>
                            <p class="mt-1 text-slate-400">{{ business.address }}</p>
                        </div>
                        <div v-if="business.rating" class="shrink-0 text-right">
                            <div class="font-display text-2xl font-bold text-amber-300">★ {{ business.rating }}</div>
                            <div class="text-xs text-slate-500">{{ business.reviews_count }} avis</div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-400">
                        <span v-if="business.phone">📞 {{ business.phone }}</span>
                        <span v-if="business.city">📍 {{ business.city }}</span>
                        <span v-if="business.website">🔗 {{ business.website }}</span>
                    </div>

                    <div v-if="business.reviews && business.reviews.length" class="mt-6 space-y-3">
                        <div v-for="(r, i) in business.reviews.slice(0, 2)" :key="i" class="rounded-xl bg-white/[0.03] p-4">
                            <div class="text-xs text-slate-500">{{ r.author }} · ★ {{ r.rating }}</div>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-300">{{ r.text }}</p>
                        </div>
                    </div>

                    <div class="mt-8 flex items-center justify-between border-t border-white/[0.06] pt-6">
                        <p class="text-sm text-slate-500">Secteur détecté : <span class="font-semibold text-slate-300 capitalize">{{ business.sector }}</span></p>
                        <button @click="generate" class="btn-brand" :class="{ 'opacity-60': create.processing }" :disabled="create.processing">
                            {{ create.processing ? 'Création…' : 'Générer le site →' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
