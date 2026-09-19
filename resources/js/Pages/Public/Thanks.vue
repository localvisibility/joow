<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ site: Object, paid: Boolean });
const liveUrl = props.site.preview_url || `https://${props.site.slug}.joow.fr`;
</script>

<template>
    <Head title="Merci" />

    <div class="flex min-h-screen items-center justify-center bg-ink-950 bg-mesh px-5 text-center text-slate-200">
        <div class="glass w-full max-w-lg rounded-3xl p-10">
            <template v-if="paid">
                <div class="mx-auto mb-6 grid h-16 w-16 place-items-center rounded-full bg-emerald-500/15 text-3xl">🎉</div>
                <h1 class="font-display text-3xl font-bold text-white">Votre site est en ligne !</h1>
                <p class="mt-3 text-slate-400">Bravo — <span class="font-semibold text-white">{{ site.name }}</span> est désormais publié et hébergé sur Joow.</p>
                <div class="mt-8 flex flex-col gap-3">
                    <a :href="liveUrl" target="_blank" rel="noopener" class="btn-brand">Voir mon site ↗</a>
                    <Link :href="route('dashboard')" class="rounded-xl border border-white/10 px-6 py-3 font-semibold text-slate-200 transition hover:border-white/25">Accéder à mon espace</Link>
                </div>
                <p class="mt-6 text-xs text-slate-500">Un email avec vos accès vous a été envoyé.</p>
            </template>
            <template v-else>
                <div class="mx-auto mb-6 grid h-16 w-16 place-items-center rounded-full bg-amber-500/15 text-3xl">⏳</div>
                <h1 class="font-display text-2xl font-bold text-white">Paiement en cours de confirmation</h1>
                <p class="mt-3 text-slate-400">Si le paiement a bien été effectué, votre site sera activé dans un instant.</p>
                <Link :href="route('public.site', site.slug)" class="btn-brand mt-8 inline-flex">Revenir à mon aperçu</Link>
            </template>
        </div>
    </div>
</template>
