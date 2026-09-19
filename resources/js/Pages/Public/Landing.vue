<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const form = useForm({ query: '', email: '' });

const q = ref('');
const results = ref([]);
const open = ref(false);
const selected = ref(null);
const searching = ref(false);
let debounce = null;

watch(q, (val) => {
    selected.value = null;
    form.query = '';
    clearTimeout(debounce);
    if (val.trim().length < 3) { results.value = []; open.value = false; return; }
    searching.value = true;
    debounce = setTimeout(async () => {
        try {
            const r = await fetch(route('public.search', { q: val }), { headers: { Accept: 'application/json' } });
            const d = await r.json();
            results.value = d.results || [];
            open.value = results.value.length > 0;
        } catch (e) { results.value = []; }
        finally { searching.value = false; }
    }, 300);
});

const pick = (r) => {
    selected.value = r;
    form.query = r.place_id;
    q.value = r.main;
    open.value = false;
};

const submit = () => {
    if (!form.query) return;
    form.post(route('public.generate'));
};

const steps = [
    { n: '1', t: 'Trouvez votre établissement', d: 'Tapez son nom — on le retrouve sur Google.' },
    { n: '2', t: "L'IA construit tout", d: 'Textes, photos, avis, design — assemblés automatiquement.' },
    { n: '3', t: 'En ligne en 30 s', d: 'Votre site est prêt. Vous le mettez en ligne en un clic.' },
];
</script>

<template>
    <Head title="Créez votre site pro en 30 secondes" />

    <div class="min-h-screen bg-ink-950 bg-mesh text-slate-200">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5">
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-gradient font-display text-lg font-bold text-white shadow-glow">J</span>
                <span class="font-display text-xl font-bold text-white">joow</span>
            </div>
            <Link :href="route('login')" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25">Se connecter</Link>
        </header>

        <section class="relative mx-auto max-w-3xl px-5 pb-20 pt-16 text-center sm:pt-24">
            <div class="pointer-events-none absolute -top-10 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="relative">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400">⚡ Propulsé par l'IA · à partir de votre fiche Google</p>
                <h1 class="font-display text-4xl font-bold leading-tight text-white sm:text-6xl">
                    Votre site professionnel<br><span class="bg-brand-gradient bg-clip-text text-transparent">en 30 secondes.</span>
                </h1>
                <p class="mx-auto mt-5 max-w-xl text-lg text-slate-400">Cherchez votre établissement : Joow génère un site complet — textes, photos, avis, design premium.</p>

                <form @submit.prevent="submit" class="mx-auto mt-10 max-w-xl">
                    <!-- Recherche intelligente -->
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a1 1 0 01-1.42 1.42l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                        <input v-model="q" type="text" autocomplete="off" class="field pl-12 text-base"
                            placeholder="Nom de votre établissement…" autofocus @focus="open = results.length > 0" />
                        <div v-if="searching" class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-white/20 border-t-brand-500"></div>

                        <!-- Dropdown résultats -->
                        <div v-if="open" class="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-white/10 bg-ink-800 text-left shadow-2xl">
                            <button v-for="r in results" :key="r.place_id" type="button" @click="pick(r)"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/5">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/5 text-slate-400"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6c0 4.314 4.686 9.44 5.29 10.08a1 1 0 001.42 0C11.314 17.44 16 12.314 16 8a6 6 0 00-6-6zm0 8a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg></span>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-white">{{ r.main }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ r.secondary }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                    <p v-if="form.errors.query" class="mt-2 text-sm text-rose-400">{{ form.errors.query }}</p>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input v-model="form.email" type="email" class="field sm:flex-1" placeholder="Votre email (optionnel)" />
                        <button type="submit" class="btn-brand shrink-0" :class="{ 'opacity-50': !form.query || form.processing }" :disabled="!form.query || form.processing">
                            {{ form.processing ? 'Création…' : 'Créer mon site' }}
                        </button>
                    </div>
                    <p v-if="selected" class="mt-2 text-sm text-emerald-400">✓ {{ selected.main }} sélectionné</p>
                    <p class="mt-3 text-xs text-slate-500">Gratuit · sans engagement · aperçu immédiat</p>
                </form>
            </div>
        </section>

        <section class="mx-auto max-w-5xl px-5 pb-24">
            <div class="grid gap-6 sm:grid-cols-3">
                <div v-for="s in steps" :key="s.n" class="glass rounded-2xl p-6">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand-gradient font-display font-bold text-white">{{ s.n }}</div>
                    <h3 class="mt-4 font-display text-lg font-bold text-white">{{ s.t }}</h3>
                    <p class="mt-1.5 text-sm text-slate-400">{{ s.d }}</p>
                </div>
            </div>
        </section>

        <footer class="border-t border-white/[0.06] py-8 text-center text-xs text-slate-600">© 2026 Joow — Vos sites, en mieux.</footer>
    </div>
</template>
