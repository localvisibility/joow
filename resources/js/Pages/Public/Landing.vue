<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ query: '', email: '' });
const submit = () => form.post(route('public.generate'));

const steps = [
    { n: '1', t: 'Votre fiche Google', d: 'Collez le lien de votre établissement (ou son nom + ville).' },
    { n: '2', t: 'L\'IA construit tout', d: 'Textes, photos, avis, design — assemblés automatiquement.' },
    { n: '3', t: 'En ligne en 30 s', d: 'Votre site est prêt. Vous le mettez en ligne en un clic.' },
];
</script>

<template>
    <Head title="Créez votre site pro en 30 secondes" />

    <div class="min-h-screen bg-ink-950 bg-mesh text-slate-200">
        <!-- Nav -->
        <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5">
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-gradient font-display text-lg font-bold text-white shadow-glow">J</span>
                <span class="font-display text-xl font-bold text-white">joow</span>
            </div>
            <Link :href="route('login')" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25">Se connecter</Link>
        </header>

        <!-- Hero + form -->
        <section class="relative mx-auto max-w-3xl px-5 pb-20 pt-16 text-center sm:pt-24">
            <div class="pointer-events-none absolute -top-10 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="relative">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400">
                    ⚡ Propulsé par l'IA · à partir de votre fiche Google
                </p>
                <h1 class="font-display text-4xl font-bold leading-tight text-white sm:text-6xl">
                    Votre site professionnel<br><span class="bg-brand-gradient bg-clip-text text-transparent">en 30 secondes.</span>
                </h1>
                <p class="mx-auto mt-5 max-w-xl text-lg text-slate-400">
                    Collez votre fiche Google : Joow génère un site complet — textes, photos, avis, design premium — prêt à être mis en ligne.
                </p>

                <form @submit.prevent="submit" class="glass mx-auto mt-10 max-w-xl rounded-2xl p-2 text-left">
                    <input v-model="form.query" type="text" class="field border-0 bg-transparent text-base focus:ring-0"
                        placeholder="Lien Google Maps ou « Plombier Dupont Lyon »" autofocus />
                    <p v-if="form.errors.query" class="px-4 pb-1 text-sm text-rose-400">{{ form.errors.query }}</p>
                    <div class="mt-1 flex flex-col gap-2 border-t border-white/5 pt-2 sm:flex-row">
                        <input v-model="form.email" type="email" class="field border-0 bg-transparent focus:ring-0 sm:flex-1" placeholder="Votre email (optionnel)" />
                        <button type="submit" class="btn-brand shrink-0" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">
                            {{ form.processing ? 'Analyse…' : 'Créer mon site' }}
                        </button>
                    </div>
                </form>
                <p class="mt-3 text-xs text-slate-500">Gratuit · sans engagement · aperçu immédiat</p>
            </div>
        </section>

        <!-- Comment ça marche -->
        <section class="mx-auto max-w-5xl px-5 pb-24">
            <div class="grid gap-6 sm:grid-cols-3">
                <div v-for="s in steps" :key="s.n" class="glass rounded-2xl p-6">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand-gradient font-display font-bold text-white">{{ s.n }}</div>
                    <h3 class="mt-4 font-display text-lg font-bold text-white">{{ s.t }}</h3>
                    <p class="mt-1.5 text-sm text-slate-400">{{ s.d }}</p>
                </div>
            </div>
        </section>

        <footer class="border-t border-white/[0.06] py-8 text-center text-xs text-slate-600">
            © 2026 Joow — Vos sites, en mieux.
        </footer>
    </div>
</template>
