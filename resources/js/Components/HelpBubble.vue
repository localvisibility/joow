<script setup>
import HelpChat from '@/Components/HelpChat.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const open = ref(false);
const contact = (q) => { open.value = false; router.get(route('support.index'), q ? { q } : {}); };
</script>

<template>
    <div class="fixed bottom-5 right-5 z-[65]">
        <Transition enter-from-class="opacity-0 translate-y-3" leave-to-class="opacity-0 translate-y-3" enter-active-class="transition" leave-active-class="transition">
            <div v-if="open" class="glass absolute bottom-16 right-0 flex w-[min(92vw,400px)] flex-col overflow-hidden rounded-2xl bg-ink-900 shadow-2xl ring-1 ring-white/10">
                <div class="flex items-center justify-between border-b border-white/[0.06] px-4 py-3">
                    <p class="font-semibold text-white">💬 Aide Joow</p>
                    <button @click="open = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <HelpChat compact @contact="contact" />
            </div>
        </Transition>
        <button @click="open = !open" class="grid h-14 w-14 place-items-center rounded-full bg-brand-gradient text-2xl text-white shadow-glow transition hover:scale-105" :aria-label="open ? 'Fermer l\'aide' : 'Ouvrir l\'aide'">{{ open ? '✕' : '?' }}</button>
    </div>
</template>
