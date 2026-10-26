<script setup>
import { Head, Link } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';
defineProps({ email: String });
</script>

<template>
    <Head title="Désinscription" />
    <div class="min-h-screen bg-ink-950 bg-mesh text-slate-200">
        <header class="mx-auto flex max-w-3xl items-center px-5 py-5"><Link :href="route('home')" aria-label="Joow"><BrandLogo class="h-9 w-auto" /></Link></header>
        <main class="mx-auto max-w-lg px-5 py-16 text-center">
            <div class="mx-auto mb-5 grid h-14 w-14 place-items-center rounded-full bg-emerald-500/15 text-2xl">✓</div>
            <h1 class="font-display text-2xl font-bold text-white">C'est noté</h1>
            <p class="mt-3 text-slate-400">Vous ne recevrez plus nos conseils à l'adresse <span class="font-semibold text-white">{{ email }}</span>. Vous continuerez à recevoir les emails indispensables : liens de connexion, confirmation de mise en ligne, factures.</p>
            <Link :href="route('dashboard')" class="btn-brand mt-8 inline-flex">Aller à mon espace</Link>
        </main>
    </div>
</template>
