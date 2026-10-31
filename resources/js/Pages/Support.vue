<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HelpChat from '@/Components/HelpChat.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ sites: Array, tickets: Array });
const page = usePage();
const q = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('q') : null;
const form = useForm({ subject: q ? q.slice(0, 150) : '', message: q ? `Bonjour,\n\n${q}\n\n` : '', site: props.sites[0]?.slug || '' });
const formEl = ref(null);
const toForm = (question) => { if (question && !form.subject) form.subject = question.slice(0, 150); if (question && !form.message) form.message = `Bonjour,\n\n${question}\n\n`; formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
const send = () => form.post(route('support.contact'), { preserveScroll: true, onSuccess: () => form.reset('subject', 'message') });

const FAQ = [
    ['Comment modifier mon site ?', 'Mes sites → « Éditer » ouvre le Studio. Cliquez directement sur un texte ou une photo dans l\'aperçu pour le changer ; Entrée pour valider. L\'onglet IA permet de demander des changements en français (« ajoute une page Tarifs »).'],
    ['Comment mettre mon site en ligne ?', 'Bouton « 🚀 Mettre en ligne » dans le Studio, ou menu Hébergement. Formule Pro : 39 € HT/mois sans engagement ; Liberté : 349 € HT en une fois. Le site est en ligne dès le paiement.'],
    ['Comment avoir mon nom de domaine ?', 'Hébergement → Nom de domaine. Un .fr ou .com est inclus : tapez le nom, puis « Commander ». Tout se configure automatiquement (quelques minutes à quelques heures). Vous avez déjà un domaine ? Onglet « J\'ai déjà un domaine ».'],
    ['Comment fonctionnent les crédits IA ?', 'Une demande à l\'assistant du Studio qui modifie le site coûte 1 crédit (+1 par page créée). Les questions et les modifications à la main sont gratuites. Pro : 100 crédits par mois. Recharge possible depuis le Studio.'],
    ['Où sont mes factures ?', 'Menu « Mes factures » : toutes vos factures, téléchargeables. La carte bancaire se change dans Hébergement → « Gérer l\'abonnement ».'],
    ['Comment résilier ?', 'Hébergement → « Gérer l\'abonnement » → Annuler. La résiliation prend effet à la fin de la période déjà payée, sans frais.'],
];
const openFaq = ref(null);
</script>

<template>
    <Head title="Support" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-display text-3xl font-bold tracking-tight text-white">Support</h1>
                <p class="mt-1 text-sm text-slate-400">Une question ? L'assistant répond tout de suite, et l'équipe Joow prend le relais si besoin.</p>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <div class="glass flex flex-col overflow-hidden rounded-2xl">
                <div class="border-b border-white/[0.06] px-5 py-4"><p class="font-semibold text-white">💬 Assistant Joow</p><p class="text-xs text-slate-500">Disponible 24 h/24 · connaît votre compte et vos sites</p></div>
                <HelpChat @contact="toForm" />
            </div>

            <div class="space-y-6">
                <div class="glass rounded-2xl p-5">
                    <p class="font-semibold text-white">Questions fréquentes</p>
                    <div v-for="([qq, a], i) in FAQ" :key="i" class="mt-2 border-b border-white/[0.04] pb-2 last:border-0">
                        <button @click="openFaq = openFaq === i ? null : i" class="flex w-full items-center justify-between gap-3 py-1.5 text-left text-sm font-medium text-slate-200 hover:text-white">{{ qq }}<span class="text-slate-500">{{ openFaq === i ? '−' : '+' }}</span></button>
                        <p v-if="openFaq === i" class="pb-1 text-sm text-slate-400">{{ a }}</p>
                    </div>
                </div>

                <div ref="formEl" class="glass scroll-mt-6 rounded-2xl p-5">
                    <p class="font-semibold text-white">✉ Écrire à l'équipe</p>
                    <p class="mt-1 text-xs text-slate-500">Réponse par email sous 24 h ouvrées.</p>
                    <p v-if="page.props.flash?.status" class="mt-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">{{ page.props.flash.status }}</p>
                    <form @submit.prevent="send" class="mt-3 space-y-3">
                        <label v-if="sites.length > 1" class="block text-xs font-semibold text-slate-400">Site concerné<select v-model="form.site" class="mt-1 w-full rounded-xl border border-white/10 bg-ink-900 px-3 py-2 text-sm text-white"><option value="">Aucun en particulier</option><option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}</option></select></label>
                        <label class="block text-xs font-semibold text-slate-400">Objet<input v-model="form.subject" required maxlength="160" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                        <label class="block text-xs font-semibold text-slate-400">Message<textarea v-model="form.message" required rows="5" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"></textarea></label>
                        <p v-if="form.errors.message || form.errors.subject" class="text-sm text-rose-300">{{ form.errors.message || form.errors.subject }}</p>
                        <button :disabled="form.processing" class="btn-brand w-full text-sm">{{ form.processing ? 'Envoi…' : 'Envoyer' }}</button>
                    </form>
                    <div v-if="tickets.length" class="mt-4 border-t border-white/[0.06] pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Vos messages</p>
                        <p v-for="t in tickets" :key="t.id" class="mt-1.5 flex justify-between gap-3 text-sm"><span class="truncate text-slate-300">#{{ t.id }} · {{ t.subject }}</span><span class="shrink-0 text-xs" :class="t.status === 'open' ? 'text-amber-300' : 'text-emerald-300'">{{ t.status === 'open' ? 'en cours' : 'traité' }}</span></p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
