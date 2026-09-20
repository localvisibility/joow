<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const drawer = ref(false);
const userMenu = ref(false);

const ic = {
    sites: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    rocket: '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
    receipt: '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
};

const groups = [
    { title: 'Principal', items: [
        { label: 'Mes sites', route: 'dashboard', icon: ic.sites },
        { label: 'Demandes', route: 'leads.index', icon: ic.inbox },
    ]},
    { title: 'Hébergement', items: [
        { label: 'Hébergement', route: 'hosting.index', icon: ic.rocket },
    ]},
    { title: 'Compte', items: [
        { label: 'Mes factures', route: 'invoices.index', icon: ic.receipt },
        { label: 'Paramètres', route: 'profile.edit', icon: ic.settings },
    ]},
];

const has = (name) => { try { return !!route().has(name); } catch (e) { return true; } };
const isActive = (name) => { try { return route().current(name); } catch (e) { return false; } };
</script>

<template>
    <div class="min-h-screen bg-ink-950 bg-mesh text-slate-200">
        <!-- Sidebar (desktop) -->
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-white/[0.06] bg-ink-900/60 backdrop-blur-xl lg:flex">
            <div class="flex h-16 items-center gap-2.5 px-6">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-gradient font-display text-lg font-bold text-white shadow-glow">J</span>
                <span class="font-display text-xl font-bold tracking-tight text-white">joow</span>
            </div>

            <div class="px-4 pb-2 pt-2">
                <Link :href="route('sites.create')" class="btn-brand w-full text-sm">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                    Nouveau site
                </Link>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                <div v-for="g in groups" :key="g.title">
                    <p class="px-3 pb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-600">{{ g.title }}</p>
                    <template v-for="item in g.items" :key="item.route">
                        <Link v-if="has(item.route)" :href="route(item.route)"
                            class="group mb-0.5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                            :class="isActive(item.route) ? 'bg-white/[0.07] text-white' : 'text-slate-400 hover:bg-white/[0.04] hover:text-white'">
                            <span class="shrink-0" :class="isActive(item.route) ? 'text-brand-400' : 'text-slate-500 group-hover:text-slate-300'" v-html="`<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>${item.icon}</svg>`"></span>
                            {{ item.label }}
                        </Link>
                    </template>
                </div>
            </nav>

            <div class="border-t border-white/[0.06] p-3">
                <div class="flex items-center gap-3 rounded-xl px-3 py-2">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-gradient text-sm font-bold text-white">{{ (page.props.auth.user.name || '?').charAt(0).toUpperCase() }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">{{ page.props.auth.user.name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ page.props.auth.user.email }}</p>
                    </div>
                    <Link :href="route('logout')" method="post" as="button" class="shrink-0 rounded-lg p-2 text-slate-500 transition hover:bg-white/5 hover:text-white" title="Déconnexion">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Mobile top bar -->
        <header class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-white/[0.06] bg-ink-950/70 px-4 backdrop-blur-xl lg:hidden">
            <button @click="drawer = true" class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 text-slate-300">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <Link :href="route('dashboard')" class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-gradient font-display text-base font-bold text-white">J</span><span class="font-display text-lg font-bold text-white">joow</span></Link>
            <Link :href="route('sites.create')" class="grid h-10 w-10 place-items-center rounded-xl bg-brand-gradient text-white shadow-glow"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg></Link>
        </header>

        <!-- Mobile drawer -->
        <transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
            <div v-if="drawer" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm lg:hidden" @click="drawer = false">
                <aside class="absolute inset-y-0 left-0 flex w-72 flex-col bg-ink-900 p-4" @click.stop>
                    <div class="mb-4 flex items-center justify-between">
                        <span class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-gradient font-display font-bold text-white">J</span><span class="font-display text-lg font-bold text-white">joow</span></span>
                        <button @click="drawer = false" class="text-slate-400">✕</button>
                    </div>
                    <nav class="flex-1 space-y-5 overflow-y-auto">
                        <div v-for="g in groups" :key="g.title">
                            <p class="px-2 pb-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-600">{{ g.title }}</p>
                            <template v-for="item in g.items" :key="item.route">
                                <Link v-if="has(item.route)" :href="route(item.route)" @click="drawer = false"
                                    class="mb-0.5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                    :class="isActive(item.route) ? 'bg-white/[0.07] text-white' : 'text-slate-300 hover:bg-white/[0.04]'">
                                    <span :class="isActive(item.route) ? 'text-brand-400' : 'text-slate-500'" v-html="`<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>${item.icon}</svg>`"></span>
                                    {{ item.label }}
                                </Link>
                            </template>
                        </div>
                    </nav>
                    <Link :href="route('logout')" method="post" as="button" class="mt-3 rounded-xl border border-white/10 px-3 py-2.5 text-left text-sm font-semibold text-slate-300">Déconnexion</Link>
                </aside>
            </div>
        </transition>

        <!-- Main -->
        <div class="lg:pl-64">
            <header v-if="$slots.header" class="border-b border-white/[0.06]">
                <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>
            <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
