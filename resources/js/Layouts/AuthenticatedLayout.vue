<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const menuOpen = ref(false);

const nav = [
    { label: 'Sites', route: 'dashboard' },
];
</script>

<template>
    <div class="min-h-screen bg-ink-950 bg-mesh">
        <header class="sticky top-0 z-40 border-b border-white/[0.06] bg-ink-950/70 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <Link :href="route('dashboard')" class="flex items-center gap-2.5">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-gradient font-display text-lg font-bold text-white shadow-glow">J</span>
                        <span class="font-display text-xl font-bold tracking-tight text-white">joow</span>
                    </Link>
                    <nav class="hidden items-center gap-1 md:flex">
                        <Link v-for="item in nav" :key="item.route" :href="route(item.route)"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-white/5 hover:text-white"
                            :class="route().current(item.route) && 'bg-white/[0.06] text-white'">
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    <Link :href="route('sites.create')"
                        class="hidden items-center gap-2 rounded-xl bg-brand-gradient px-4 py-2 text-sm font-semibold text-white shadow-glow transition hover:-translate-y-0.5 sm:inline-flex">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                        Nouveau site
                    </Link>
                    <div class="relative">
                    <button @click="menuOpen = !menuOpen"
                        class="flex items-center gap-2.5 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm transition hover:border-white/20">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10 text-xs font-bold text-white">
                            {{ (page.props.auth.user.name || '?').charAt(0).toUpperCase() }}
                        </span>
                        <span class="hidden text-slate-300 sm:block">{{ page.props.auth.user.name }}</span>
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0">
                        <div v-if="menuOpen"
                            class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-white/10 bg-ink-800 shadow-2xl">
                            <Link :href="route('profile.edit')" class="block px-4 py-3 text-sm text-slate-300 transition hover:bg-white/5">Mon profil</Link>
                            <Link :href="route('logout')" method="post" as="button"
                                class="block w-full px-4 py-3 text-left text-sm text-slate-300 transition hover:bg-white/5">Déconnexion</Link>
                        </div>
                    </transition>
                    </div>
                </div>
            </div>
        </header>

        <header v-if="$slots.header" class="border-b border-white/[0.06]">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
