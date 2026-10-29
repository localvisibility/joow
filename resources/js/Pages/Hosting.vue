<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DomainPanel from '@/Components/DomainPanel.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const trialDays = page.props.trial_days || 0;
const props = defineProps({
    sites: { type: Array, default: () => [] },
    userEmail: String,
    cancelAtEnd: Boolean,
    subEndsAt: String,
});

const offline = computed(() => props.sites.filter((s) => !s.online));
const online = computed(() => props.sites.filter((s) => s.online));
const selected = ref(offline.value[0]?.slug || null);
const domainSite = ref(online.value[0]?.slug || null);

const form = useForm({ email: props.userEmail, plan: 'pro' });
const buy = (plan) => {
    if (!selected.value) return;
    form.plan = plan;
    form.post(route('public.checkout', selected.value));
};
const switching = ref(null);
const toLiberte = (s) => {
    if (!confirm(`Passer « ${s.name} » en formule Liberté pour 349 € HT, en paiement unique ?\n\nVotre site reste en ligne sans interruption et l'abonnement Pro s'arrête à la fin de la période déjà payée : plus aucun prélèvement mensuel.`)) return;
    switching.value = s.slug;
    form.plan = 'liberte';
    form.post(route('public.checkout', s.slug), { onFinish: () => (switching.value = null) });
};
const err = computed(() => form.errors.email || page.props.errors?.email);
const date = (d) => (d ? new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : null);
const subLabel = (s) => ({ active: 'Abonnement actif', trialing: 'Période d\'essai', past_due: 'Paiement en échec', canceled: 'Abonnement résilié', lifetime: 'Payé · sans abonnement' }[s.sub] || 'En ligne');

const included = [
    'Hébergement illimité', 'Certificat SSL', 'Sauvegardes auto',
    'Support email', 'CDN performance', 'SEO optimisé',
];
</script>

<template>
    <Head title="Hébergement" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-display text-3xl font-bold tracking-tight text-white">Hébergement</h1>
                <p class="mt-1 text-sm text-slate-400">Votre formule, votre abonnement et votre nom de domaine.</p>
            </div>
        </template>

        <!-- Inclus (avant la mise en ligne) -->
        <div v-if="offline.length || !online.length" class="glass rounded-2xl p-6">
            <p class="mb-4 flex items-center gap-2 font-semibold text-white">🎁 Inclus dans votre abonnement</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <p v-for="f in included" :key="f" class="flex items-center gap-2 text-sm text-slate-300"><span class="text-emerald-400">✓</span> {{ f }}</p>
            </div>
        </div>

        <div v-if="offline.length || !online.length" class="mt-5 overflow-hidden rounded-2xl bg-gradient-to-r from-ink-800 to-ink-700 p-6 sm:flex sm:items-center sm:gap-5">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-gradient text-2xl shadow-glow">🚀</span>
            <div class="mt-3 sm:mt-0">
                <p class="font-display text-lg font-bold text-white">Hébergement professionnel tout inclus</p>
                <p class="text-sm text-slate-400">Certificat SSL, sauvegardes automatiques, support prioritaire et performances optimales. Nous gérons tout pour vous.</p>
            </div>
        </div>

        <!-- Sélecteur de site -->
        <p v-if="err" class="mt-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ err }}</p>

        <!-- Sites en ligne : formule actuelle -->
        <div v-if="online.length" class="mt-8">
            <p class="mb-3 flex items-center gap-2 font-semibold text-white">✅ Vos sites en ligne</p>
            <div class="space-y-3">
                <div v-for="s in online" :key="s.slug" class="glass rounded-2xl p-5 transition" :class="domainSite === s.slug ? 'ring-1 ring-brand-500/40' : ''">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2"><span class="font-display text-lg font-bold text-white">{{ s.name }}</span>
                                <span class="chip" :class="s.plan === 'liberte' ? 'bg-violet-500/15 text-violet-300' : 'bg-brand-500/15 text-brand-300'">{{ s.plan === 'liberte' ? 'Liberté' : 'Pro' }}</span>
                                <span class="chip" :class="s.sub === 'past_due' ? 'bg-rose-500/15 text-rose-300' : 'bg-emerald-500/15 text-emerald-300'">{{ subLabel(s) }}</span></p>
                            <p class="mt-1 text-sm text-slate-400">
                                <template v-if="s.plan === 'pro'">39 € HT / mois<span v-if="cancelAtEnd && subEndsAt"> · s'arrête le {{ date(subEndsAt) }}</span><span v-else-if="s.period_end"> · prochain prélèvement le {{ date(s.period_end) }}</span></template>
                                <template v-else>Payé en une fois<span v-if="s.paid_at"> le {{ date(s.paid_at) }}</span></template>
                                · <a :href="`https://${s.slug}.joow.fr`" target="_blank" rel="noopener" class="text-brand-400 hover:text-brand-300">voir le site ↗</a>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="domainSite = s.slug" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-white/25">🌐 Nom de domaine</button>
                            <a v-if="s.plan === 'pro'" :href="route('hosting.portal')" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-white/25">Gérer l'abonnement</a>
                        </div>
                    </div>
                    <!-- Bascule vers Liberté -->
                    <div v-if="s.plan === 'pro'" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-400/20 bg-emerald-500/[0.05] px-4 py-3">
                        <p class="text-sm text-slate-300"><span class="font-semibold text-emerald-300">Passer en Liberté</span> · 349 € HT une seule fois, plus d'abonnement. Votre site reste en ligne, sans interruption.</p>
                        <button type="button" @click="toLiberte(s)" :disabled="form.processing" class="rounded-xl border border-emerald-400/40 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/10">{{ switching === s.slug ? '…' : 'Passer en Liberté' }}</button>
                    </div>
                    <div v-if="domainSite === s.slug" class="mt-5"><DomainPanel :key="s.slug" :slug="s.slug" /></div>
                </div>
            </div>
        </div>

        <div v-if="offline.length" class="mt-10">
            <label class="mb-2 block text-sm font-semibold text-white">Site à mettre en ligne</label>
            <select v-model="selected" class="field max-w-md">
                <option v-for="s in offline" :key="s.slug" :value="s.slug">{{ s.name }}{{ s.city ? ' · ' + s.city : '' }}</option>
            </select>
        </div>

        <div v-if="!sites.length" class="glass mt-8 rounded-2xl p-10 text-center">
            <p class="font-display text-lg font-bold text-white">Aucun site à héberger</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-400">Créez d'abord un site, vous pourrez le mettre en ligne ici.</p>
            <Link :href="route('sites.create')" class="btn-brand mt-6 inline-flex">Créer un site →</Link>
        </div>

        <!-- Formules (sites pas encore en ligne) -->
        <div v-if="offline.length" class="mt-6">
            <p class="mb-4 flex items-center gap-2 font-semibold text-white">📦 Choisissez votre formule</p>
            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Pro -->
                <div class="glass relative overflow-hidden rounded-3xl border-brand-500/40 p-7">
                    <span class="absolute right-5 top-5 rounded-full bg-brand-gradient px-3 py-1 text-xs font-bold text-white shadow-glow">Recommandé</span>
                    <h3 class="font-display text-xl font-bold text-white">Pro</h3>
                    <p class="mt-1 text-xs text-slate-400">Sans engagement · .joow.fr + domaine .com/.fr</p>
                    <p class="mt-5 font-display text-5xl font-bold text-white">39€<span class="text-lg font-medium text-slate-400"> HT/mois</span></p>
                    <p class="mt-2 text-sm font-semibold text-emerald-300">{{ trialDays > 0 ? '🎉 ' + trialDays + ' jours d\'essai gratuit' : 'Mise en ligne immédiate · sans engagement' }}</p>
                    <ul class="mt-5 space-y-2 text-sm text-slate-300">
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Site web professionnel</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Nom de domaine .com / .fr inclus</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur IA & modifications illimitées</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Certificat SSL + SEO avancé</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Support prioritaire 7j/7</li>
                    </ul>
                    <button @click="buy('pro')" class="btn-brand mt-7 w-full" :disabled="form.processing || !selected">
                        {{ form.processing && form.plan==='pro' ? '…' : (trialDays > 0 ? 'Essai gratuit ' + trialDays + ' jours' : 'Souscrire · 39 € HT/mois') }}
                    </button>
                    <p class="mt-2 text-center text-xs text-slate-500">{{ trialDays > 0 ? '0 € aujourd\'hui · résiliez quand vous voulez' : 'Résiliable à tout moment' }}</p>
                </div>

                <!-- Liberté -->
                <div class="glass relative overflow-hidden rounded-3xl p-7">
                    <h3 class="font-display text-xl font-bold text-white">Liberté</h3>
                    <p class="mt-1 text-xs text-slate-400">Paiement unique · autonomie totale</p>
                    <p class="mt-5 font-display text-5xl font-bold text-white">349€<span class="text-lg font-medium text-slate-400"> HT</span></p>
                    <p class="mt-2 text-sm font-semibold text-brand-400">≈ 9 mois d'abo · économies garanties</p>
                    <ul class="mt-5 space-y-2 text-sm text-slate-300">
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Site web professionnel</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Domaine .com / .fr inclus</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Hébergement 1 an inclus</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur + modifications illimitées</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Téléchargement de votre site</li>
                    </ul>
                    <button @click="buy('liberte')" class="mt-7 w-full rounded-xl border border-emerald-400/40 px-6 py-3 font-semibold text-emerald-300 transition hover:bg-emerald-500/10" :disabled="form.processing || !selected">
                        {{ form.processing && form.plan==='liberte' ? '…' : 'Paiement unique 349€' }}
                    </button>
                    <p class="mt-2 text-center text-xs text-slate-500">Sans abonnement · site à vie</p>
                </div>
            </div>
            <p class="mt-5 flex items-center justify-center gap-1.5 text-xs text-slate-500"><span>🔒</span> Paiement sécurisé Stripe</p>
        </div>

    </AuthenticatedLayout>
</template>
