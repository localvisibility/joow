<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({ site: Object });

const checkout = useForm({ email: '' });
const buy = () => checkout.post(route('public.checkout', props.site.slug));

const ready = ref(['preview', 'paid', 'published'].includes(props.site.status));
const liveUrl = props.site.preview_url || `https://${props.site.slug}.joow.fr`;
let timer = null;

const poll = async () => {
    try {
        const r = await fetch(route('public.site.status', props.site.slug), { headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (d.ready) { ready.value = true; if (timer) clearInterval(timer); }
    } catch (e) { /* retry au prochain tick */ }
};

onMounted(() => {
    if (!ready.value) { poll(); timer = setInterval(poll, 2500); }
});
onUnmounted(() => timer && clearInterval(timer));
</script>

<template>
    <Head :title="ready ? `Site prêt — ${site.name}` : 'Génération en cours…'" />

    <div class="min-h-screen bg-ink-950 bg-mesh text-slate-200">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5">
            <Link :href="route('home')" class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-gradient font-display text-lg font-bold text-white shadow-glow">J</span>
                <span class="font-display text-xl font-bold text-white">joow</span>
            </Link>
            <Link :href="route('login')" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25">Se connecter</Link>
        </header>

        <!-- En cours -->
        <section v-if="!ready" class="mx-auto max-w-xl px-5 py-24 text-center">
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
                <p class="mt-2 text-slate-400">Voici l'aperçu de votre site. Mettez-le en ligne sur votre domaine en un clic.</p>
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

            <div class="glass mx-auto mt-8 max-w-lg rounded-2xl p-6">
                <div class="flex items-baseline justify-between">
                    <h3 class="font-display text-lg font-bold text-white">Mettre mon site en ligne</h3>
                    <p class="font-display text-xl font-bold text-white">9,90€<span class="text-sm font-medium text-slate-400">/mois</span></p>
                </div>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-400">
                    <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Site en ligne, hébergement inclus</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur pour tout modifier</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Sans engagement, résiliable à tout moment</li>
                </ul>
                <form @submit.prevent="buy" class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <input v-model="checkout.email" type="email" required class="field sm:flex-1" placeholder="Votre email" />
                    <button type="submit" class="btn-brand shrink-0" :class="{ 'opacity-60': checkout.processing }" :disabled="checkout.processing">
                        {{ checkout.processing ? '…' : 'Payer et publier →' }}
                    </button>
                </form>
                <p v-if="checkout.errors.email" class="mt-2 text-sm text-rose-400">{{ checkout.errors.email }}</p>
                <a :href="liveUrl" target="_blank" rel="noopener" class="mt-4 block text-center text-sm text-slate-400 hover:text-white">Ouvrir l'aperçu en plein écran ↗</a>
            </div>
        </section>
    </div>
</template>
