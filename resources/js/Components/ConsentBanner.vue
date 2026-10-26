<script setup>
import { ref } from 'vue';
import { consent, setConsent, initPixel } from '@/tracking';
const props = defineProps({ pixelId: String });
const show = ref(!!props.pixelId && consent() === '');
const accept = () => { setConsent('all'); initPixel(props.pixelId); show.value = false; };
const refuse = () => { setConsent('essential'); show.value = false; };
</script>

<template>
    <Transition name="consent">
        <div v-if="show" class="fixed inset-x-3 bottom-3 z-[90] mx-auto max-w-xl rounded-2xl border border-white/10 bg-ink-900/95 p-4 text-sm text-slate-200 shadow-2xl backdrop-blur sm:bottom-5" role="dialog" aria-live="polite" aria-label="Cookies">
            <p class="text-[13px] leading-relaxed">Nous utilisons un cookie de mesure publicitaire (Meta) pour savoir quelles campagnes vous ont amené ici. Aucune donnée n'est revendue. <a href="https://joow.fr/confidentialite" class="underline underline-offset-2 hover:text-white">En savoir plus</a></p>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button type="button" @click="refuse" class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-300 hover:text-white">Refuser</button>
                <button type="button" @click="accept" class="btn-brand !px-4 !py-2 text-xs">Accepter</button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.consent-enter-active, .consent-leave-active { transition: opacity .3s ease, transform .3s ease; }
.consent-enter-from, .consent-leave-to { opacity: 0; transform: translateY(12px); }
</style>
