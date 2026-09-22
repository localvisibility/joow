<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DomainPanel from '@/Components/DomainPanel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    sites: { type: Array, default: () => [] },
    userEmail: String,
});

const offline = computed(() => props.sites.filter((s) => !s.online));
const online = computed(() => props.sites.filter((s) => s.online));
const selected = ref(offline.value[0]?.slug || props.sites[0]?.slug || null);
const domainSite = ref(online.value[0]?.slug || null);

const form = useForm({ email: props.userEmail, plan: 'pro' });
const buy = (plan) => {
    if (!selected.value) return;
    form.plan = plan;
    form.post(route('public.checkout', selected.value));
};

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
                <h1 class="font-display text-3xl font-bold tracking-tight text-white">Mettre en ligne</h1>
                <p class="mt-1 text-sm text-slate-400">Configurez l'hébergement et le domaine de votre site.</p>
            </div>
        </template>

        <!-- Inclus -->
        <div class="glass rounded-2xl p-6">
            <p class="mb-4 flex items-center gap-2 font-semibold text-white">🎁 Inclus dans votre abonnement</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <p v-for="f in included" :key="f" class="flex items-center gap-2 text-sm text-slate-300"><span class="text-emerald-400">✓</span> {{ f }}</p>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl bg-gradient-to-r from-ink-800 to-ink-700 p-6 sm:flex sm:items-center sm:gap-5">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-gradient text-2xl shadow-glow">🚀</span>
            <div class="mt-3 sm:mt-0">
                <p class="font-display text-lg font-bold text-white">Hébergement professionnel tout inclus</p>
                <p class="text-sm text-slate-400">Certificat SSL, sauvegardes automatiques, support prioritaire et performances optimales. Nous gérons tout pour vous.</p>
            </div>
        </div>

        <!-- Sélecteur de site -->
        <div v-if="sites.length" class="mt-8">
            <label class="mb-2 block text-sm font-semibold text-white">Site à mettre en ligne</label>
            <select v-model="selected" class="field max-w-md">
                <option v-for="s in sites" :key="s.slug" :value="s.slug" :disabled="s.online">
                    {{ s.name }}{{ s.city ? ' · ' + s.city : '' }}{{ s.online ? ' (déjà en ligne)' : '' }}
                </option>
            </select>
            <p v-if="form.errors.email" class="mt-2 text-sm text-rose-400">{{ form.errors.email }}</p>
        </div>

        <div v-else class="glass mt-8 rounded-2xl p-10 text-center">
            <p class="font-display text-lg font-bold text-white">Aucun site à héberger</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-400">Créez d'abord un site, vous pourrez le mettre en ligne ici.</p>
            <Link :href="route('sites.create')" class="btn-brand mt-6 inline-flex">Créer un site →</Link>
        </div>

        <!-- Formules -->
        <div v-if="sites.length" class="mt-6">
            <p class="mb-4 flex items-center gap-2 font-semibold text-white">📦 Choisissez votre formule</p>
            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Pro -->
                <div class="glass relative overflow-hidden rounded-3xl border-brand-500/40 p-7">
                    <span class="absolute right-5 top-5 rounded-full bg-brand-gradient px-3 py-1 text-xs font-bold text-white shadow-glow">Recommandé</span>
                    <h3 class="font-display text-xl font-bold text-white">Pro</h3>
                    <p class="mt-1 text-xs text-slate-400">Sans engagement · .joow.fr + domaine .com/.fr</p>
                    <p class="mt-5 font-display text-5xl font-bold text-white">39€<span class="text-lg font-medium text-slate-400"> HT/mois</span></p>
                    <p class="mt-2 text-sm font-semibold text-emerald-300">🎉 7 jours d'essai gratuit</p>
                    <ul class="mt-5 space-y-2 text-sm text-slate-300">
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Site web professionnel</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Nom de domaine .com / .fr inclus</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur IA & modifications illimitées</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Certificat SSL + SEO avancé</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Support prioritaire 7j/7</li>
                    </ul>
                    <button @click="buy('pro')" class="btn-brand mt-7 w-full" :disabled="form.processing || !selected">
                        {{ form.processing && form.plan==='pro' ? '…' : 'Essai gratuit 7 jours' }}
                    </button>
                    <p class="mt-2 text-center text-xs text-slate-500">0€ aujourd'hui · résiliez quand vous voulez</p>
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

        <!-- Sites déjà en ligne -->
        <div v-if="online.length" class="mt-8">
            <p class="mb-3 text-sm font-semibold text-white">Déjà en ligne</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button v-for="s in online" :key="s.slug" type="button" @click="domainSite = s.slug" class="glass flex items-center justify-between rounded-xl p-4 text-left transition" :class="domainSite === s.slug ? 'border-brand-500/50' : ''">
                    <span class="min-w-0"><span class="block truncate text-sm font-semibold text-white">{{ s.name }}</span><span class="chip mt-1 bg-emerald-500/15 text-emerald-300 capitalize">{{ s.plan || 'en ligne' }}</span></span>
                    <a :href="`https://${s.slug}.joow.fr`" target="_blank" rel="noopener" class="shrink-0 text-sm font-semibold text-brand-400" @click.stop>Voir →</a>
                </button>
            </div>
            <!-- Nom de domaine du site sélectionné -->
            <div v-if="domainSite" class="mt-5"><DomainPanel :key="domainSite" :slug="domainSite" /></div>
        </div>
    </AuthenticatedLayout>
</template>
