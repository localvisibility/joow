<script setup>
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-ink-950 bg-mesh px-4 py-10">
        <div class="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-brand-600/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 bottom-0 h-96 w-96 rounded-full bg-fuchsia-600/10 blur-3xl"></div>

        <div class="relative w-full max-w-md">
            <div class="mb-8 flex flex-col items-center">
                <Link href="/" class="block" aria-label="Joow"><BrandLogo class="h-12 w-auto" /></Link>
                <p class="mt-3 text-sm text-slate-400">Votre plateforme de sites, en un seul endroit.</p>
            </div>

            <div class="glass rounded-3xl p-8 shadow-2xl">
                <slot />
            </div>

            <p class="mt-8 text-center text-xs text-slate-600">© 2026 Joow — Vos sites, en mieux.</p>
        </div>
    </div>
</template>
