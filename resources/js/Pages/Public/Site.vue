<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import GenerationShow from '@/Components/GenerationShow.vue';
import { track, fire, uuid } from '@/tracking';

const props = defineProps({ site: Object, email: { type: String, default: '' }, can_edit: { type: Boolean, default: false }, has_email: { type: Boolean, default: false }, expires_in_days: { type: Number, default: 7 }, old_url: { type: String, default: null }, combo: { type: Boolean, default: false } });
// Mis à jour pendant la génération (le site indiqué sur la fiche Google est découvert en cours de route)
const oldUrl = ref(props.old_url);
const combo = ref(props.combo);
const oldHost = computed(() => (oldUrl.value || '').replace(/^https?:\/\//i, '').replace(/^www\./i, '').split(/[?#]/)[0].replace(/\/.*$/, ''));

// ── Email : indispensable pour retrouver le site et ouvrir le Studio ──
const hasEmail = ref(props.has_email);
const lead = ref({ email: props.email || '', sending: false, done: false, sent: false, error: '' });
const leadInput = ref(null);
const studioInput = ref(null);
// Renvoyé du Studio sans email (?studio=1) : on explique et on amène sur le champ
const fromStudio = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('studio') === '1';
const googleEnabled = !!usePage().props.google_enabled;
const saveEmail = async (next = null) => {
    const v = (lead.value.email || '').trim();
    if (!emailValid(v)) { lead.value.error = 'Entrez une adresse email valide.'; (next ? studioInput : leadInput).value?.focus(); return; }
    lead.value.sending = true; lead.value.error = '';
    try {
        const r = await fetch(route('public.site.email', props.site.slug), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' }, body: JSON.stringify({ email: v }) });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.message || 'Impossible d\'enregistrer cet email.');
        hasEmail.value = true; lead.value.done = true; lead.value.sent = !!d.sent; checkout.email ||= v; fire(d.track);
        if (next === 'studio') router.visit(d.studio || route('sites.editor', props.site.slug));
    } catch (e) { lead.value.error = e.message; }
    finally { lead.value.sending = false; }
};

const trialDays = usePage().props.trial_days || 0;
const checkout = useForm({ email: props.email || '', plan: 'pro' });
const emailInput = ref(null);
const shake = ref(false);
const emailValid = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v || '');
// Email manquant ou invalide : on amène l'utilisateur dessus (défilement, focus, secousse) avec un message explicite.
const askEmail = (plan) => {
    checkout.setError('email', checkout.email ? 'Cet email ne semble pas valide. Vérifiez-le pour recevoir vos accès.' : `Entrez d'abord votre email ci-dessus : c'est là que nous envoyons vos accès${plan === 'pro' ? ' et votre facture' : ''}.`);
    shake.value = false; requestAnimationFrame(() => { shake.value = true; });
    emailInput.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => emailInput.value?.focus({ preventScroll: true }), 350);
};
// Envoi en formulaire natif : le serveur redirige vers Stripe (domaine externe), impossible en XHR Inertia.
const buy = (plan) => {
    if (!emailValid(checkout.email)) { askEmail(plan); return; }
    checkout.clearErrors('email');
    checkout.plan = plan; checkout.processing = true;
    const f = document.createElement('form'); f.method = 'POST'; f.action = route('public.checkout', props.site.slug);
    const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
    const eventId = uuid();
    track('InitiateCheckout', { value: plan === 'liberte' ? 349 : 39, currency: 'EUR', content_ids: [plan], content_type: 'product' }, eventId);
    add('_token', document.querySelector('meta[name=csrf-token]')?.content || ''); add('email', checkout.email); add('plan', plan); add('event_id', eventId);
    document.body.appendChild(f); f.submit();
};

const retryForm = useForm({});
const retry = () => retryForm.post(route('public.site.retry', props.site.slug));

const ready = ref(['preview', 'paid', 'published'].includes(props.site.status));
const device = ref(typeof window !== 'undefined' && window.innerWidth < 640 ? 'mobile' : 'desktop');
const readying = ref(false);
const failed = ref(props.site.status === 'failed');
const liveUrl = props.site.preview_url || `https://${props.site.slug}.joow.fr`;
let timer = null;
const progress = ref({});

const poll = async () => {
    try {
        const r = await fetch(route('public.site.status', props.site.slug), { headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (d.progress) progress.value = d.progress;
        if (d.old_url) oldUrl.value = d.old_url;
        if (d.combo) combo.value = true;
        // Prêt : on laisse 1,8 s au spectacle pour afficher « site prêt » puis on bascule sur l'aperçu
        if (d.ready) { if (timer) clearInterval(timer); progress.value = { ...progress.value, step: 'done' }; readying.value = true; track('SiteGenerated', { content_name: props.site.slug, content_category: props.site.sector || '' }); setTimeout(() => { ready.value = true; failed.value = false; }, 1800); }
        else if (d.failed) { failed.value = true; if (timer) clearInterval(timer); }
    } catch (e) { /* retry au prochain tick */ }
};

onMounted(() => {
    if (ready.value) track('ViewContent', { content_name: props.site.slug, content_category: props.site.sector || '', content_type: 'site' });
    if (!ready.value && !failed.value) { poll(); timer = setInterval(poll, 1500); }
    if (fromStudio && !hasEmail.value) setTimeout(() => { studioInput.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }); studioInput.value?.focus({ preventScroll: true }); }, 400);
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
        <GenerationShow v-else-if="!ready" :site="site" :progress="progress" :ready="readying" :failed="failed">
            <template #capture>
                <div v-if="can_edit" class="mx-auto mb-6 max-w-2xl">
                    <div v-if="!hasEmail" class="glass rounded-2xl border-brand-500/20 p-4 sm:p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-white"><span class="grid h-6 w-6 place-items-center rounded-full bg-brand-gradient text-xs">✉️</span> Recevez votre lien privé et testez l'éditeur</p>
                        <p class="mt-1 text-xs text-slate-400">Modifiez textes, photos et couleurs en quelques clics, activez réservation, devis ou assistant IA. Gratuit, sans compte, sans carte.</p>
                        <a v-if="googleEnabled" :href="route('google.redirect', { redirect: route('public.site', site.slug) })" class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-white/15 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"><svg class="h-4 w-4" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.3l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4 7.1-10 7.1-17z"/><path fill="#FBBC05" d="M10.5 28.6A14.5 14.5 0 0 1 9.7 24c0-1.6.3-3.1.8-4.6l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg> Continuer avec Google</a>
                        <form class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="saveEmail()">
                            <input ref="leadInput" v-model="lead.email" type="email" inputmode="email" autocomplete="email" class="field sm:flex-1" :class="{ 'border-rose-400 ring-2 ring-rose-400/40': lead.error }" placeholder="prenom@exemple.fr" @input="lead.error = ''" />
                            <button type="submit" class="btn-brand shrink-0 !px-5 !py-3 text-sm" :disabled="lead.sending">{{ lead.sending ? '…' : 'Recevoir mon lien' }}</button>
                        </form>
                        <p v-if="lead.error" class="mt-2 text-xs font-medium text-rose-300">{{ lead.error }}</p>
                    </div>
                    <p v-else class="rounded-2xl border border-emerald-400/20 bg-emerald-500/[0.06] px-4 py-3 text-center text-sm text-emerald-200">✓ Dès que le site est prêt, nous envoyons votre lien privé à <span class="font-semibold">{{ lead.email || email }}</span>.</p>
                </div>
            </template>
        </GenerationShow>

        <!-- Prêt -->
        <section v-else class="mx-auto max-w-6xl px-5 py-12">
            <div class="mb-8 text-center">
                <p class="mb-2 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-semibold text-emerald-300">🎉 Votre site est prêt</p>
                <h1 class="font-display text-3xl font-bold text-white sm:text-4xl">{{ site.name }}</h1>
                <p class="mt-2 text-slate-400">{{ combo ? 'Fiche Google et site actuel combinés : avis, note et photos Google, textes et pages de votre site, le tout réécrit et remis en forme.' : (oldUrl ? 'Voici votre nouveau site, reconstruit à partir de votre site actuel : textes réécrits, photos conservées, pages reprises.' : 'Voici l\'aperçu de votre site. Personnalisez-le gratuitement dans le Studio, puis mettez-le en ligne quand il est parfait.') }}</p>
                <!-- Refonte : avant / après -->
                <div v-if="oldUrl" class="mx-auto mt-4 inline-flex flex-wrap items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-2 text-xs">
                    <span class="text-slate-500">Avant</span><a :href="oldUrl" target="_blank" rel="noopener nofollow" class="font-semibold text-slate-300 line-through decoration-rose-400/70 hover:text-white">{{ oldHost }}</a>
                    <span class="text-slate-600">→</span>
                    <span class="text-slate-500">Après</span><a :href="liveUrl" target="_blank" rel="noopener" class="font-semibold text-emerald-300 hover:text-white">{{ site.slug }}.joow.fr</a>
                </div>
                <!-- Studio accessible : email déjà rattaché -->
                <template v-if="can_edit && hasEmail">
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <Link :href="route('sites.editor', site.slug)" class="btn-brand !px-7 !py-3.5 text-base">✨ Personnaliser mon site dans le Studio</Link>
                        <a :href="liveUrl" target="_blank" rel="noopener" class="rounded-xl border border-white/10 px-5 py-3.5 text-sm font-semibold text-slate-300 transition hover:border-white/25">Voir en plein écran ↗</a>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Textes, photos, pages, couleurs, formulaire… tout est modifiable en 2 clics ou avec l'assistant IA. Gratuit, sans compte.</p>
                    <p v-if="lead.sent" class="mt-2 text-xs text-emerald-300">✓ Votre lien privé vient d'être envoyé à {{ lead.email }}.</p>
                </template>
                <!-- Studio verrouillé : on vend l'éditeur et les modules, l'email ouvre tout -->
                <div v-else-if="can_edit" class="mx-auto mt-6 max-w-2xl text-left">
                    <div class="glass relative overflow-hidden rounded-3xl p-5 sm:p-7" :class="fromStudio || lead.error ? 'border-brand-400/50' : 'border-brand-500/25'">
                        <div class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-brand-gradient opacity-20 blur-3xl"></div>
                        <p class="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold text-emerald-300">Gratuit · sans carte bancaire · sans engagement</p>
                        <h2 class="mt-3 font-display text-2xl font-bold leading-tight text-white sm:text-3xl">Il vous plaît ? Faites-en <span class="text-shimmer">votre</span> site, en quelques clics.</h2>
                        <p class="mt-2 text-sm text-slate-300">{{ fromStudio ? 'Le Studio s\'ouvre dès que votre email est enregistré. ' : '' }}Notre éditeur vous laisse tout retoucher directement sur la page, sans aucune compétence technique.</p>

                        <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                            <li class="flex items-start gap-3 rounded-2xl border border-white/[0.06] bg-white/[0.03] p-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-gradient text-base">✏️</span>
                                <span class="text-sm"><span class="block font-semibold text-white">Cliquez, modifiez, c'est fait</span><span class="text-slate-400">Textes, photos, couleurs et polices se changent directement sur le site.</span></span>
                            </li>
                            <li class="flex items-start gap-3 rounded-2xl border border-white/[0.06] bg-white/[0.03] p-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-gradient text-base">🗓️</span>
                                <span class="text-sm"><span class="block font-semibold text-white">Vos modules en un clic</span><span class="text-slate-400">Réservation, rendez-vous ou devis en ligne, avis Google, formulaire intelligent.</span></span>
                            </li>
                            <li class="flex items-start gap-3 rounded-2xl border border-white/[0.06] bg-white/[0.03] p-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-gradient text-base">🤖</span>
                                <span class="text-sm"><span class="block font-semibold text-white">Un assistant IA à vos ordres</span><span class="text-slate-400">« Ajoute une page Tarifs », « rends le ton plus chaleureux » : il le fait.</span></span>
                            </li>
                            <li class="flex items-start gap-3 rounded-2xl border border-white/[0.06] bg-white/[0.03] p-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-gradient text-base">💬</span>
                                <span class="text-sm"><span class="block font-semibold text-white">Un chatbot qui répond à vos clients</span><span class="text-slate-400">Horaires, tarifs, prise de contact : il connaît votre activité, 24h/24.</span></span>
                            </li>
                        </ul>

                        <a v-if="googleEnabled" :href="route('google.redirect', { redirect: route('sites.editor', site.slug) })" class="mt-5 flex w-full items-center justify-center gap-3 rounded-xl border border-white/15 bg-white px-4 py-3.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100">
                            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.3l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4 7.1-10 7.1-17z"/><path fill="#FBBC05" d="M10.5 28.6A14.5 14.5 0 0 1 9.7 24c0-1.6.3-3.1.8-4.6l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                            Continuer avec Google · 1 clic
                        </a>
                        <div v-if="googleEnabled" class="my-3 flex items-center gap-3 text-[11px] text-slate-500"><span class="h-px flex-1 bg-white/10"></span>ou avec votre email<span class="h-px flex-1 bg-white/10"></span></div>
                        <form class="flex flex-col gap-2 sm:flex-row" :class="googleEnabled ? '' : 'mt-5'" @submit.prevent="saveEmail('studio')">
                            <input ref="studioInput" v-model="lead.email" type="email" inputmode="email" autocomplete="email" class="field sm:flex-1 !py-3.5" :class="{ 'border-rose-400 ring-2 ring-rose-400/40': lead.error }" placeholder="Votre email pour ouvrir l'éditeur" @input="lead.error = ''" />
                            <button type="submit" class="btn-brand shrink-0 !px-6 !py-3.5 text-sm" :disabled="lead.sending">{{ lead.sending ? 'Ouverture…' : '✨ Tester l\'éditeur gratuitement' }}</button>
                        </form>
                        <p v-if="lead.error" class="mt-2 text-xs font-medium text-rose-300">{{ lead.error }}</p>
                        <p class="mt-3 text-xs text-slate-400">En continuant, vous créez votre espace Joow : vos sites, vos demandes et vos réservations au même endroit, accessible par Google, par lien envoyé par email, ou avec le mot de passe que vous choisirez. Vous recevrez quelques conseils pour votre site, désinscription en un clic. Vous ne payez que si vous décidez de le mettre en ligne.</p>
                        <p class="mt-1.5 text-[11px] text-slate-500">Sans email, cet aperçu est supprimé automatiquement dans {{ expires_in_days }} jour{{ expires_in_days > 1 ? 's' : '' }}.</p>
                    </div>
                    <p class="mt-3 text-center text-xs"><a :href="liveUrl" target="_blank" rel="noopener" class="font-semibold text-slate-400 underline-offset-2 hover:text-white hover:underline">Voir en plein écran ↗</a></p>
                </div>
            </div>

            <!-- Fenêtre navigateur : dévoilement animé, bascule ordinateur / mobile -->
            <div class="unveil relative mx-auto transition-[max-width] duration-500" :class="device === 'mobile' ? 'max-w-[420px]' : 'max-w-6xl'">
                <span v-for="n in 18" :key="n" class="confetti" :style="{ '--x': (Math.random() * 100) + '%', '--d': (0.2 + Math.random() * 0.8) + 's', '--r': (Math.random() * 360) + 'deg', background: ['#a78bfa','#f472b6','#34d399','#fbbf24','#60a5fa'][n % 5] }"></span>
                <div class="glass overflow-hidden" :class="device === 'mobile' ? 'rounded-[2.2rem] border-[6px] border-ink-800' : 'rounded-2xl'">
                    <div v-if="device !== 'mobile'" class="flex items-center gap-2 border-b border-white/[0.06] px-4 py-3">
                        <span class="h-3 w-3 rounded-full bg-rose-400/70"></span>
                        <span class="h-3 w-3 rounded-full bg-amber-400/70"></span>
                        <span class="h-3 w-3 rounded-full bg-emerald-400/70"></span>
                        <span class="ml-3 truncate rounded-md bg-white/5 px-3 py-1 text-xs text-slate-400">{{ site.slug }}.joow.fr</span>
                        <span class="ml-auto hidden rounded-full border border-emerald-400/30 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-300 sm:inline">● En direct</span>
                    </div>
                    <iframe :src="liveUrl" class="w-full bg-white" :class="device === 'mobile' ? 'h-[78vh]' : 'h-[70vh]'" loading="lazy"></iframe>
                </div>
                <div class="mt-3 flex items-center justify-center gap-2 text-xs">
                    <button type="button" @click="device = 'desktop'" class="rounded-full px-3 py-1.5 font-semibold transition" :class="device === 'desktop' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'">🖥 Ordinateur</button>
                    <button type="button" @click="device = 'mobile'" class="rounded-full px-3 py-1.5 font-semibold transition" :class="device === 'mobile' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'">📱 Mobile</button>
                </div>
            </div>

            <!-- Choix de la formule -->
            <div class="mx-auto mt-10 max-w-3xl">
                <div class="mb-5 text-center">
                    <p class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-semibold text-emerald-300">Essai gratuit · vous ne payez qu'en mettant en ligne</p>
                    <h3 class="mt-3 font-display text-2xl font-bold text-white">Mettez votre site en ligne</h3>
                </div>

                <div class="mb-5 rounded-2xl border p-4 transition-colors sm:p-5" :class="checkout.errors.email ? 'border-rose-400/60 bg-rose-500/[0.07]' : 'border-white/10 bg-white/[0.03]'">
                    <label for="checkout-email" class="flex items-center gap-2 text-sm font-semibold text-white">
                        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-gradient text-xs font-bold text-white">1</span>
                        Votre email
                        <span class="font-normal text-slate-400">· pour recevoir vos accès et votre facture</span>
                    </label>
                    <input id="checkout-email" ref="emailInput" v-model="checkout.email" type="email" required autocomplete="email" inputmode="email"
                        class="field mt-3" :class="{ 'border-rose-400 ring-2 ring-rose-400/40': checkout.errors.email, 'animate-shake': shake }"
                        :aria-invalid="!!checkout.errors.email" aria-describedby="checkout-email-error"
                        placeholder="prenom@exemple.fr" @input="checkout.clearErrors('email')" @animationend="shake = false" @keydown.enter.prevent="buy('pro')" />
                    <p v-if="checkout.errors.email" id="checkout-email-error" class="mt-3 flex items-start gap-2 rounded-xl bg-rose-500/15 px-3 py-2 text-sm font-medium text-rose-200" role="alert">
                        <svg class="mt-0.5 h-4 w-4 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                        <span>{{ checkout.errors.email }}</span>
                    </p>
                    <p v-else class="mt-2 text-xs text-slate-500">Puis choisissez votre formule ci-dessous (étape 2).</p>
                </div>

                <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-white">
                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-gradient text-xs font-bold text-white">2</span>
                    Choisissez votre formule
                </p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <!-- Pro -->
                    <div class="glass relative overflow-hidden rounded-2xl border-brand-500/40 p-6">
                        <span class="absolute right-4 top-4 rounded-full bg-brand-gradient px-3 py-1 text-xs font-bold text-white shadow-glow">Recommandé</span>
                        <h4 class="font-display text-lg font-bold text-white">Pro</h4>
                        <p class="mt-1 text-xs text-slate-400">Sans engagement · .joow.fr + domaine .com/.fr</p>
                        <p class="mt-4 font-display text-4xl font-bold text-white">39€<span class="text-base font-medium text-slate-400"> HT/mois</span></p>
                        <p class="mt-1 text-xs font-semibold text-emerald-300">{{ trialDays > 0 ? '🎉 ' + trialDays + ' jours d\'essai gratuit' : 'Mise en ligne immédiate · sans engagement' }}</p>
                        <ul class="mt-4 space-y-1.5 text-sm text-slate-300">
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Nom de domaine .com / .fr inclus</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur IA & modifications illimitées</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Certificat SSL + SEO avancé</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Support prioritaire 7j/7</li>
                        </ul>
                        <button @click="buy('pro')" class="btn-brand mt-6 w-full" :class="{ 'opacity-60': checkout.processing }" :disabled="checkout.processing">
                            {{ checkout.processing && checkout.plan==='pro' ? '…' : (trialDays > 0 ? 'Essai gratuit ' + trialDays + ' jours' : 'Souscrire · 39 € HT/mois') }}
                        </button>
                        <p class="mt-2 text-center text-xs text-slate-500">{{ trialDays > 0 ? '0 € aujourd\'hui · résiliez quand vous voulez' : 'Résiliable à tout moment · site en ligne dans la minute' }}</p>
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

<style scoped>
.text-shimmer { background: linear-gradient(100deg,#818cf8 20%,#e879f9 40%,#38bdf8 60%,#818cf8 80%); background-size: 200% auto; -webkit-background-clip: text; background-clip: text; color: transparent; animation: shimmer 5s linear infinite; }
@keyframes shimmer { to { background-position: 200% center; } }
/* Dévoilement du site : montée + ouverture en rideau, puis confettis discrets */
.unveil { animation: unveil 1.1s cubic-bezier(.22,1,.36,1) both; }
@keyframes unveil { 0% { opacity: 0; transform: translateY(28px) scale(.96); clip-path: inset(0 50% 0 50% round 1rem); } 55% { opacity: 1; clip-path: inset(0 0 0 0 round 1rem); } 100% { opacity: 1; transform: none; clip-path: inset(0 0 0 0 round 1rem); } }
.confetti { position: absolute; top: -6px; left: var(--x); width: 7px; height: 11px; border-radius: 2px; opacity: 0; transform: rotate(var(--r)); animation: confetti 1.9s ease-out var(--d) both; pointer-events: none; z-index: 5; }
@keyframes confetti { 0% { opacity: 0; transform: translateY(0) rotate(var(--r)); } 12% { opacity: 1; } 100% { opacity: 0; transform: translateY(260px) rotate(calc(var(--r) + 300deg)); } }
@media (prefers-reduced-motion: reduce) { .unveil, .confetti { animation: none; } .confetti { display: none; } }
</style>
