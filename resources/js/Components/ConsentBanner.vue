<script setup>
import { ref } from 'vue';
import { consent, choose } from '@/tracking';
const props = defineProps({ pixelId: String, gtmId: String });
const show = ref((!!props.pixelId || !!props.gtmId) && consent() === '');
const custom = ref(false);
const ads = ref(true);
const analytics = ref(true);
const acceptAll = () => { choose(true); show.value = false; };
const refuse = () => { choose([]); show.value = false; };
const save = () => { choose([ads.value && 'ads', analytics.value && 'analytics']); show.value = false; };
</script>

<template>
    <Transition name="consent">
        <div v-if="show" class="consent-card" role="dialog" aria-live="polite" aria-label="Cookies et mesure d'audience">
            <div class="flex items-start justify-between gap-4">
                <p class="font-display text-base font-bold text-white">🍪 Cookies &amp; mesure d'audience</p>
                <button type="button" @click="refuse" class="shrink-0 text-xs text-slate-400 underline decoration-slate-600 underline-offset-4 hover:text-white">Continuer sans accepter</button>
            </div>
            <p class="mt-2 text-[13px] leading-relaxed text-slate-300">Nous utilisons des cookies pour améliorer votre expérience et mesurer l'efficacité de nos campagnes. Rien n'est déposé sans votre accord. <button type="button" @click="custom = !custom" class="underline underline-offset-2 hover:text-white">Personnaliser</button></p>
            <div v-if="custom" class="mt-3 space-y-2 rounded-xl border border-white/10 bg-white/[0.03] p-3 text-[13px]">
                <label class="flex items-center justify-between gap-3"><span><span class="font-semibold text-white">Mesure d'audience</span><span class="block text-xs text-slate-400">Comprendre quelles pages sont utiles (Google Analytics).</span></span><input v-model="analytics" type="checkbox" class="h-5 w-5 rounded accent-brand-500"></label>
                <label class="flex items-center justify-between gap-3"><span><span class="font-semibold text-white">Mesure publicitaire</span><span class="block text-xs text-slate-400">Savoir quelle publicité vous a amené ici (Meta, Google Ads).</span></span><input v-model="ads" type="checkbox" class="h-5 w-5 rounded accent-brand-500"></label>
                <label class="flex items-center justify-between gap-3 opacity-60"><span><span class="font-semibold text-white">Fonctionnement du site</span><span class="block text-xs text-slate-400">Session, sécurité, mémorisation de ce choix.</span></span><input type="checkbox" checked disabled class="h-5 w-5 rounded"></label>
                <button type="button" @click="save" class="mt-1 w-full rounded-xl border border-white/15 px-3 py-2 text-xs font-semibold text-white hover:bg-white/5">Enregistrer mes choix</button>
            </div>
            <button type="button" @click="acceptAll" class="btn-brand mt-3 w-full !py-3 text-sm">Accepter</button>
            <p class="mt-2 text-center text-[11px] text-slate-500"><a href="https://joow.fr/confidentialite" class="hover:text-white">Politique de confidentialité</a></p>
        </div>
    </Transition>
</template>

<style scoped>
.consent-enter-active, .consent-leave-active { transition: opacity .3s ease, transform .3s ease; }
.consent-enter-from, .consent-leave-to { opacity: 0; transform: translateY(16px); }
</style>
