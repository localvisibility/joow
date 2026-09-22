<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({ site: Object, can_edit: { type: Boolean, default: false } });

const checkout = useForm({ email: '', plan: 'pro' });
// Envoi en formulaire natif : le serveur redirige vers Stripe (domaine externe), impossible en XHR Inertia.
const buy = (plan) => {
    if (!checkout.email) { checkout.setError('email', 'Indiquez votre email pour recevoir vos accès.'); return; }
    checkout.plan = plan; checkout.processing = true;
    const f = document.createElement('form'); f.method = 'POST'; f.action = route('public.checkout', props.site.slug);
    const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
    add('_token', document.querySelector('meta[name=csrf-token]')?.content || ''); add('email', checkout.email); add('plan', plan);
    document.body.appendChild(f); f.submit();
};

const retryForm = useForm({});
const retry = () => retryForm.post(route('public.site.retry', props.site.slug));

const ready = ref(['preview', 'paid', 'published'].includes(props.site.status));
const failed = ref(props.site.status === 'failed');
const liveUrl = props.site.preview_url || `https://${props.site.slug}.joow.fr`;
let timer = null;

const poll = async () => {
    try {
        const r = await fetch(route('public.site.status', props.site.slug), { headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (d.ready) { ready.value = true; failed.value = false; if (timer) clearInterval(timer); }
        else if (d.failed) { failed.value = true; if (timer) clearInterval(timer); }
    } catch (e) { /* retry au prochain tick */ }
};

onMounted(() => {
    if (!ready.value && !failed.value) { poll(); timer = setInterval(poll, 2500); }
});
onUnmounted(() => timer && clearInterval(timer));
</script>

<template>
    <Head :title="ready ? `Site prêt — ${site.name}` : 'Génération en cours…'" />

    <div class="min-h-screen bg-ink-950 bg-mesh text-slate-200">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5">
            <Link :href="route('home')" class="block" aria-label="Joow"><BrandLogo class="h-9 w-auto" /></Link>
            <Link :href="route('login')" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25">Se connecter</Link>
        </header>

        <!-- Échec -->
        <section v-if="failed" class="mx-auto max-w-xl px-5 py-24 text-center">
            <div class="mx-auto mb-6 grid h-16 w-16 place-items-center rounded-full bg-rose-500/15 text-3xl">⚠️</div>
            <h1 class="font-display text-3xl font-bold text-white">La génération a échoué</h1>
            <p class="mt-3 text-slate-400">Un souci temporaire est survenu pendant la création de <span class="font-semibold text-white">{{ site.name }}</span>. Vous pouvez relancer la génération.</p>
            <button @click="retry" class="btn-brand mt-8" :class="{ 'opacity-60': retryForm.processing }" :disabled="retryForm.processing">
                {{ retryForm.processing ? 'Relance…' : 'Relancer la génération →' }}
            </button>
            <Link :href="route('home')" class="mt-4 block text-sm text-slate-400 hover:text-white">Repartir de zéro</Link>
        </section>

        <!-- En cours -->
        <section v-else-if="!ready" class="mx-auto max-w-xl px-5 py-24 text-center">
            <div class="mx-auto mb-8 h-16 w-16 animate-spin rounded-full border-4 border-white/10 border-t-brand-500"></div>
            <h1 class="font-display text-3xl font-bold text-white">Génération de votre site…</h1>
            <p class="mt-3 text-slate-400">On assemble les textes, les photos et vos avis pour <span class="font-semibold text-white">{{ site.name }}</span>. Quelques secondes.</p>
            <div class="glass mt-8 space-y-3 rounded-2xl p-6 text-left text-sm">
                <p class="flex items-center gap-3 text-slate-300"><span class="text-brand-400">✓</span> Fiche Google analysée</p>
                <p class="flex items-center gap-3 text-slate-300"><span class="animate-pulse text-brand-400">◐</span> Rédaction du contenu par l'IA</p>
                <p class="flex items-center gap-3 text-slate-500"><span>○</span> Mise en ligne de l'aperçu</p>
            </div>
        </section>

        <!-- Prêt -->
        <section v-else class="mx-auto max-w-6xl px-5 py-12">
            <div class="mb-8 text-center">
                <p class="mb-2 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-semibold text-emerald-300">🎉 Votre site est prêt</p>
                <h1 class="font-display text-3xl font-bold text-white sm:text-4xl">{{ site.name }}</h1>
                <p class="mt-2 text-slate-400">Voici l'aperçu de votre site. Personnalisez-le gratuitement dans le Studio, puis mettez-le en ligne quand il est parfait.</p>
                <div v-if="can_edit" class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <Link :href="route('sites.editor', site.slug)" class="btn-brand !px-7 !py-3.5 text-base">✨ Personnaliser mon site dans le Studio</Link>
                    <a :href="liveUrl" target="_blank" rel="noopener" class="rounded-xl border border-white/10 px-5 py-3.5 text-sm font-semibold text-slate-300 transition hover:border-white/25">Voir en plein écran ↗</a>
                </div>
                <p v-if="can_edit" class="mt-3 text-xs text-slate-500">Textes, photos, pages, couleurs, formulaire… tout est modifiable en 2 clics ou avec l'assistant IA. Gratuit, sans compte.</p>
            </div>

            <!-- Fenêtre navigateur -->
            <div class="glass overflow-hidden rounded-2xl">
                <div class="flex items-center gap-2 border-b border-white/[0.06] px-4 py-3">
                    <span class="h-3 w-3 rounded-full bg-rose-400/70"></span>
                    <span class="h-3 w-3 rounded-full bg-amber-400/70"></span>
                    <span class="h-3 w-3 rounded-full bg-emerald-400/70"></span>
                    <span class="ml-3 truncate rounded-md bg-white/5 px-3 py-1 text-xs text-slate-400">{{ site.slug }}.joow.fr</span>
                </div>
                <iframe :src="liveUrl" class="h-[70vh] w-full bg-white" loading="lazy"></iframe>
            </div>

            <!-- Choix de la formule -->
            <div class="mx-auto mt-10 max-w-3xl">
                <div class="mb-5 text-center">
                    <p class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-semibold text-emerald-300">Aperçu gratuit · vous ne payez qu'en publiant</p>
                    <h3 class="mt-3 font-display text-2xl font-bold text-white">Choisissez votre formule</h3>
                </div>

                <div class="mb-4">
                    <input v-model="checkout.email" type="email" required class="field text-center" placeholder="Votre email pour recevoir vos accès" />
                    <p v-if="checkout.errors.email" class="mt-2 text-center text-sm text-rose-400">{{ checkout.errors.email }}</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <!-- Pro -->
                    <div class="glass relative overflow-hidden rounded-2xl border-brand-500/40 p-6">
                        <span class="absolute right-4 top-4 rounded-full bg-brand-gradient px-3 py-1 text-xs font-bold text-white shadow-glow">Recommandé</span>
                        <h4 class="font-display text-lg font-bold text-white">Pro</h4>
                        <p class="mt-1 text-xs text-slate-400">Sans engagement · .joow.fr + domaine .com/.fr</p>
                        <p class="mt-4 font-display text-4xl font-bold text-white">39€<span class="text-base font-medium text-slate-400"> HT/mois</span></p>
                        <p class="mt-1 text-xs font-semibold text-emerald-300">🎉 7 jours d'essai gratuit</p>
                        <ul class="mt-4 space-y-1.5 text-sm text-slate-300">
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Nom de domaine .com / .fr inclus</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur IA & modifications illimitées</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Certificat SSL + SEO avancé</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Support prioritaire 7j/7</li>
                        </ul>
                        <button @click="buy('pro')" class="btn-brand mt-6 w-full" :class="{ 'opacity-60': checkout.processing }" :disabled="checkout.processing">
                            {{ checkout.processing && checkout.plan==='pro' ? '…' : 'Essai gratuit 7 jours' }}
                        </button>
                        <p class="mt-2 text-center text-xs text-slate-500">0€ aujourd'hui · résiliez quand vous voulez</p>
                    </div>

                    <!-- Liberté -->
                    <div class="glass relative overflow-hidden rounded-2xl p-6">
                        <h4 class="font-display text-lg font-bold text-white">Liberté</h4>
                        <p class="mt-1 text-xs text-slate-400">Paiement unique · autonomie totale</p>
                        <p class="mt-4 font-display text-4xl font-bold text-white">349€<span class="text-base font-medium text-slate-400"> HT</span></p>
                        <p class="mt-1 text-xs font-semibold text-brand-400">≈ 9 mois d'abo · économies garanties</p>
                        <ul class="mt-4 space-y-1.5 text-sm text-slate-300">
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Domaine .com / .fr inclus</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Hébergement 1 an inclus</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur + modifications illimitées</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Téléchargement de votre site</li>
                        </ul>
                        <button @click="buy('liberte')" class="mt-6 w-full rounded-xl border border-emerald-400/40 px-6 py-3 font-semibold text-emerald-300 transition hover:bg-emerald-500/10" :class="{ 'opacity-60': checkout.processing }" :disabled="checkout.processing">
                            {{ checkout.processing && checkout.plan==='liberte' ? '…' : 'Paiement unique 349€' }}
                        </button>
                        <p class="mt-2 text-center text-xs text-slate-500">Sans abonnement · site à vie</p>
                    </div>
                </div>

                <p class="mt-5 flex items-center justify-center gap-1.5 text-xs text-slate-500"><span>🔒</span> Paiement sécurisé Stripe</p>
                <a :href="liveUrl" target="_blank" rel="noopener" class="mt-2 block text-center text-sm text-slate-400 hover:text-white">Ouvrir l'aperçu en plein écran ↗</a>
            </div>
        </section>
    </div>
</template>
