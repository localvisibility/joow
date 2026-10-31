<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    sites: Array, current: String, active: Boolean, online: Boolean, price: Number, ready: Boolean,
    goals: Object, packs: Array, pages: Array, site_url: String,
});

const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const api = async (method, url, body) => {
    const r = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined });
    const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.error || d.message || 'Erreur'); return d;
};
const selectSite = (slug) => router.get(route('ads.index'), { site: slug });
const justPaid = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('ok') === '1';
if (justPaid && !props.active) setTimeout(() => router.reload({ only: ['active'] }), 4000);

const tab = ref('pack');
const err = ref('');
const copied = ref('');
const copy = async (t, k) => { try { await navigator.clipboard.writeText(t); copied.value = k; setTimeout(() => (copied.value = ''), 1800); } catch { prompt('Copiez :', t); } };

/* ───── Stratégie & pubs ───── */
const packs = ref([...(props.packs || [])]);
const packIdx = ref(0);
const pack = computed(() => packs.value[packIdx.value]?.pack);
const form = reactive({ goal: 'leads', offer: '', budget: '' });
const generating = ref(false);
const generate = async () => {
    generating.value = true; err.value = '';
    try { const d = await api('POST', route('ads.pack', props.current), { goal: form.goal, offer: form.offer || null, budget: form.budget ? Number(form.budget) : null }); packs.value.unshift(d); packIdx.value = 0; }
    catch (e) { err.value = e.message; } finally { generating.value = false; }
};

/* ───── Pages d'offre ───── */
const pages = ref([...(props.pages || [])]);
const newPage = reactive({ goal: 'leads', offer: '' });
const creating = ref(false);
const replacePage = (p) => { const i = pages.value.findIndex((x) => x.id === p.id); i >= 0 ? pages.value.splice(i, 1, p) : pages.value.unshift(p); };
const createPage = async (fromPack = null) => {
    creating.value = true; err.value = '';
    try {
        const body = fromPack
            ? { goal: packs.value[packIdx.value].goal, offer: packs.value[packIdx.value].offer, title: fromPack.title, path: fromPack.path, variants: fromPack.variants }
            : { goal: newPage.goal, offer: newPage.offer };
        const d = await api('POST', route('ads.pages.store', props.current), body);
        replacePage(d.page); tab.value = 'pages'; newPage.offer = '';
    } catch (e) { err.value = e.message; } finally { creating.value = false; }
};
const busy = ref(null);
const act = async (p, action, variant = null) => {
    if (action === 'reset' && !confirm('Remettre les compteurs du test à zéro ?')) return;
    busy.value = p.id;
    try { replacePage((await api('POST', route('ads.pages.action', [props.current, p.id]), { action, variant })).page); } catch (e) { err.value = e.message; } finally { busy.value = null; }
};
const removePage = async (p) => {
    if (!confirm(`Supprimer la page « ${p.title} » et ses statistiques ?`)) return;
    await api('DELETE', route('ads.pages.destroy', [props.current, p.id])); pages.value = pages.value.filter((x) => x.id !== p.id);
};
const editing = ref(null);
const draft = ref(null);
const startEdit = (p) => { editing.value = p.id; draft.value = JSON.parse(JSON.stringify({ title: p.title, goal: p.goal, variants: p.variants.map((v) => ({ ...v, bulletsText: (v.bullets || []).join('\n') })) })); };
const saveEdit = async (p) => {
    busy.value = p.id;
    try {
        const variants = draft.value.variants.map((v) => ({ ...v, bullets: v.bulletsText.split('\n').map((s) => s.trim()).filter(Boolean) }));
        replacePage((await api('PATCH', route('ads.pages.update', [props.current, p.id]), { title: draft.value.title, goal: draft.value.goal, variants })).page); editing.value = null;
    } catch (e) { err.value = e.message; } finally { busy.value = null; }
};
const addVariant = () => { if (draft.value.variants.length < 3) draft.value.variants.push({ ...draft.value.variants[0], key: '', bulletsText: draft.value.variants[0].bulletsText }); };
const verdictText = (r) => {
    const v = r.verdict || {};
    if (v.status === 'single') return 'Une seule variante : pas de test A/B.';
    if (v.status === 'collecting') return `Test en cours : il faut au moins ${v.needed} visites par variante pour conclure.`;
    if (v.status === 'winner') return `🏆 La variante ${v.winner} gagne (${v.confidence} % de confiance${v.uplift ? `, +${v.uplift} % de conversions` : ''}).`;
    return `Pas encore de différence nette${v.leader ? ` (${v.leader} en tête, ${v.confidence} % de confiance)` : ''}. Le test continue.`;
};

/* ───── Liens de suivi ───── */
const link = reactive({ target: '', source: 'meta', campaign: '' });
const trackUrl = computed(() => {
    const base = link.target || props.site_url || '';
    if (!base) return '';
    const p = new URLSearchParams({ utm_source: link.source, utm_medium: 'paid', utm_campaign: (link.campaign || 'joow-ads').toLowerCase().replace(/\s+/g, '-') });
    if (link.source === 'meta') p.set('utm_content', '{{ad.name}}');
    return base + (base.includes('?') ? '&' : '?') + p.toString().replace('%7B%7Bad.name%7D%7D', '{{ad.name}}');
});
const goalLabel = (g) => ({ calls: 'Appels', leads: 'Demandes', bookings: 'Réservations', visits: 'Visites en boutique' }[g] || g);
</script>

<template>
    <Head title="Joow Ads" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">📣 Joow Ads</h1>
                    <p class="mt-1 text-sm text-slate-400">Votre stratégie, vos publicités prêtes à publier et des pages d'offre qui se testent toutes seules.</p>
                </div>
                <select v-if="sites.length > 1" :value="current" @change="selectSite($event.target.value)" class="rounded-xl border border-white/10 bg-ink-900 px-4 py-2.5 text-sm text-white">
                    <option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}{{ s.city ? ' · ' + s.city : '' }}{{ s.online ? '' : ' (aperçu)' }}</option>
                </select>
            </div>
        </template>

        <div v-if="!current" class="glass rounded-2xl p-10 text-center text-slate-400">Créez d'abord un site.</div>

        <!-- Offre du module -->
        <div v-else-if="!active" class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
            <div class="glass rounded-3xl p-8">
                <p v-if="justPaid" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">Paiement reçu, activation en cours… la page se met à jour toute seule.</p>
                <h2 class="font-display text-2xl font-bold text-white">Des clients, pas seulement un site</h2>
                <p class="mt-2 text-slate-400">Joow Ads prépare tout ce qu'il faut pour faire de la publicité locale qui rapporte, à partir de vos vraies informations.</p>
                <ul class="mt-6 space-y-4 text-sm text-slate-300">
                    <li class="flex gap-3"><span class="text-xl">🧭</span><span><b class="text-white">Votre stratégie</b> : qui cibler, avec quel message, quelle offre et quel budget.</span></li>
                    <li class="flex gap-3"><span class="text-xl">✍️</span><span><b class="text-white">Vos publicités prêtes à publier</b> : 5 pubs Facebook / Instagram avec vos photos, et vos annonces Google (titres, textes, mots-clés).</span></li>
                    <li class="flex gap-3"><span class="text-xl">🎯</span><span><b class="text-white">Des pages d'offre</b> sur votre site, pensées pour faire appeler ou demander un devis.</span></li>
                    <li class="flex gap-3"><span class="text-xl">🧪</span><span><b class="text-white">Un test A/B automatique</b> : deux versions de la page, le trafic partagé, la gagnante désignée avec un vrai calcul statistique.</span></li>
                </ul>
            </div>
            <div class="glass flex flex-col rounded-3xl border-brand-500/40 p-8">
                <p class="font-display text-5xl font-bold text-white">{{ price }} €<span class="text-lg font-medium text-slate-400"> HT/mois</span></p>
                <p class="mt-2 text-sm text-emerald-300">En plus de votre abonnement · sans engagement</p>
                <p class="mt-4 text-sm text-slate-400">Le budget publicitaire (Facebook, Google) reste à part : vous le réglez directement à la plateforme, vous gardez la main.</p>
                <div class="mt-auto pt-6">
                    <template v-if="!online">
                        <p class="mb-3 text-sm text-amber-300">Votre site doit d'abord être en ligne : vos publicités ont besoin d'une adresse.</p>
                        <Link :href="route('hosting.index')" class="btn-brand w-full text-center">Mettre mon site en ligne</Link>
                    </template>
                    <button v-else-if="!ready" disabled class="w-full rounded-xl border border-white/10 px-4 py-3 text-sm text-slate-500">Disponible très bientôt</button>
                    <Link v-else :href="route('ads.subscribe', current)" method="post" as="button" class="btn-brand w-full">Activer Joow Ads · {{ price }} € HT/mois</Link>
                    <p class="mt-2 text-center text-xs text-slate-500">🔒 Paiement Stripe · résiliable en un clic</p>
                </div>
            </div>
        </div>

        <template v-else>
            <div class="flex gap-1 border-b border-white/[0.06]">
                <button v-for="[k, l] in [['pack', '1 · Stratégie & publicités'], ['pages', '2 · Pages d\'offre (A/B)'], ['link', '3 · Liens de suivi']]" :key="k" @click="tab = k"
                    class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition" :class="tab === k ? 'border-brand-400 text-white' : 'border-transparent text-slate-400 hover:text-white'">{{ l }}</button>
            </div>
            <p v-if="err" class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ err }}</p>

            <!-- 1. Stratégie & pubs -->
            <div v-if="tab === 'pack'" class="mt-6 space-y-6">
                <div class="glass rounded-2xl p-6">
                    <h2 class="font-semibold text-white">Nouvelle campagne</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_2fr_1fr_auto] sm:items-end">
                        <label class="text-xs font-semibold text-slate-400">Objectif<select v-model="form.goal" class="mt-1 w-full rounded-xl border border-white/10 bg-ink-900 px-3 py-2.5 text-sm text-white"><option v-for="(l, k) in goals" :key="k" :value="k">{{ goalLabel(k) }}</option></select></label>
                        <label class="text-xs font-semibold text-slate-400">Offre à mettre en avant (facultatif)<input v-model="form.offer" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white" placeholder="-20 % sur la première prestation, devis gratuit sous 24 h…" /></label>
                        <label class="text-xs font-semibold text-slate-400">Budget pub / mois<input v-model="form.budget" type="number" min="0" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white" placeholder="300 €" /></label>
                        <button @click="generate" :disabled="generating" class="btn-brand text-sm">{{ generating ? 'Rédaction… (≈ 30 s)' : '✨ Générer' }}</button>
                    </div>
                </div>

                <div v-if="packs.length > 1" class="flex flex-wrap gap-2 text-xs">
                    <span class="text-slate-500">Historique :</span>
                    <button v-for="(p, i) in packs" :key="p.id" @click="packIdx = i" class="rounded-lg px-2.5 py-1" :class="i === packIdx ? 'bg-brand-500/25 text-white' : 'bg-white/5 text-slate-400'">{{ goalLabel(p.goal) }}{{ p.offer ? ' · ' + p.offer.slice(0, 24) : '' }} · {{ new Date(p.at).toLocaleDateString('fr-FR') }}</button>
                </div>

                <template v-if="pack">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="glass rounded-2xl p-6">
                            <h3 class="font-semibold text-white">🧭 La stratégie</h3>
                            <p class="mt-2 text-sm text-slate-300">{{ pack.strategy?.summary }}</p>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Qui cibler</p>
                            <div v-for="(a, i) in pack.strategy?.audiences" :key="i" class="mt-2 rounded-xl bg-white/[0.03] p-3 text-sm"><p class="font-semibold text-white">{{ a.name }}</p><p class="text-slate-400">{{ a.targeting }}</p><p class="mt-1 text-xs text-slate-500">{{ a.why }}</p></div>
                        </div>
                        <div class="glass rounded-2xl p-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Angles de message</p>
                            <div v-for="(a, i) in pack.strategy?.angles" :key="i" class="mt-2 text-sm"><span class="font-semibold text-white">{{ a.name }}</span> <span class="text-slate-400">· {{ a.idea }}</span></div>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Idées d'offre</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-300"><li v-for="(o, i) in pack.strategy?.offer_ideas" :key="i">🎁 {{ o }}</li></ul>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Budget</p><p class="mt-1 text-sm text-slate-300">{{ pack.strategy?.budget }}</p>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">À surveiller</p><p class="mt-1 text-sm text-slate-300">{{ pack.strategy?.kpi }}</p>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 font-semibold text-white">📱 Publicités Facebook / Instagram</h3>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div v-for="(ad, i) in pack.meta_ads" :key="i" class="glass flex flex-col overflow-hidden rounded-2xl">
                                <img v-if="ad.image" :src="ad.image" class="aspect-square w-full object-cover" alt="" />
                                <div class="flex flex-1 flex-col p-4 text-sm">
                                    <span class="chip self-start bg-white/10 text-slate-300">{{ ad.angle }}</span>
                                    <p class="mt-2 font-semibold text-white">{{ ad.hook }}</p>
                                    <p class="mt-2 whitespace-pre-line text-slate-300">{{ ad.primary_text }}</p>
                                    <p class="mt-3 rounded-lg bg-white/[0.04] px-3 py-2"><span class="block font-semibold text-white">{{ ad.headline }}</span><span class="text-xs text-slate-400">{{ ad.description }} · bouton « {{ ad.cta }} »</span></p>
                                    <p class="mt-2 text-xs text-slate-500">🖼 {{ ad.visual }}</p>
                                    <div class="mt-auto flex gap-2 pt-3">
                                        <button @click="copy(ad.primary_text, 'pt' + i)" class="flex-1 rounded-lg bg-white/[0.06] px-2 py-1.5 text-xs font-semibold text-slate-200">{{ copied === 'pt' + i ? 'Copié ✓' : 'Copier le texte' }}</button>
                                        <button @click="copy(ad.headline, 'hl' + i)" class="rounded-lg bg-white/[0.06] px-2 py-1.5 text-xs font-semibold text-slate-200">{{ copied === 'hl' + i ? '✓' : 'Titre' }}</button>
                                        <a v-if="ad.image" :href="ad.image" target="_blank" download class="rounded-lg bg-white/[0.06] px-2 py-1.5 text-xs font-semibold text-slate-200">Photo</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="pack.google_ads" class="glass rounded-2xl p-6">
                        <h3 class="font-semibold text-white">🔎 Annonces Google</h3>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div v-for="[k, l] in [['headlines', 'Titres (≤ 30 caractères)'], ['descriptions', 'Descriptions (≤ 90 caractères)'], ['keywords', 'Mots-clés'], ['negative_keywords', 'Mots-clés à exclure']]" :key="k">
                                <div class="flex items-center justify-between"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ l }}</p><button @click="copy((pack.google_ads[k] || []).join('\n'), k)" class="text-xs text-brand-400">{{ copied === k ? 'Copié ✓' : 'Tout copier' }}</button></div>
                                <ul class="mt-2 space-y-1 text-sm text-slate-300"><li v-for="(x, i) in pack.google_ads[k]" :key="i">{{ x }}</li></ul>
                            </div>
                        </div>
                    </div>

                    <div v-if="pack.landing?.variants?.length" class="glass flex flex-wrap items-center justify-between gap-3 rounded-2xl border-brand-500/30 p-5">
                        <p class="text-sm text-slate-300"><b class="text-white">Page d'offre proposée</b> : « {{ pack.landing.variants[0].headline }} » et une 2e version pour le test A/B.</p>
                        <button @click="createPage(pack.landing)" :disabled="creating" class="btn-brand text-sm">{{ creating ? '…' : 'Créer cette page d\'offre →' }}</button>
                    </div>
                </template>
                <p v-else class="text-sm text-slate-500">Choisissez un objectif et générez votre première campagne.</p>
            </div>

            <!-- 2. Pages d'offre -->
            <div v-else-if="tab === 'pages'" class="mt-6 space-y-5">
                <div class="glass rounded-2xl p-6">
                    <h2 class="font-semibold text-white">Nouvelle page d'offre</h2>
                    <p class="mt-1 text-sm text-slate-400">Décrivez votre offre : l'IA rédige deux versions de la page et le test A/B démarre à la publication.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
                        <label class="text-xs font-semibold text-slate-400">Objectif<select v-model="newPage.goal" class="mt-1 w-full rounded-xl border border-white/10 bg-ink-900 px-3 py-2.5 text-sm text-white"><option v-for="(l, k) in goals" :key="k" :value="k">{{ goalLabel(k) }}</option></select></label>
                        <label class="text-xs font-semibold text-slate-400">Offre<input v-model="newPage.offer" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white" placeholder="Diagnostic offert pour toute installation avant le 30 novembre" /></label>
                        <button @click="createPage()" :disabled="creating || !newPage.offer.trim()" class="btn-brand text-sm">{{ creating ? 'Rédaction…' : '✨ Créer la page' }}</button>
                    </div>
                </div>

                <div v-for="p in pages" :key="p.id" class="glass rounded-2xl p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2"><span class="font-display text-lg font-bold text-white">{{ p.title }}</span>
                                <span class="chip" :class="p.status === 'live' ? 'bg-emerald-500/15 text-emerald-300' : p.status === 'paused' ? 'bg-amber-500/15 text-amber-300' : 'bg-white/10 text-slate-400'">{{ { live: 'En ligne', paused: 'En pause', draft: 'Brouillon' }[p.status] }}</span>
                                <span class="chip bg-white/10 text-slate-300">{{ goalLabel(p.goal) }}</span></p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm"><a :href="p.status === 'live' ? p.url : route('ads.pages.preview', [current, p.id])" target="_blank" class="truncate text-brand-400">{{ p.url }}</a><button v-if="p.status === 'live'" @click="copy(p.url, 'u' + p.id)" class="text-xs text-slate-400">{{ copied === 'u' + p.id ? 'Copié ✓' : 'Copier' }}</button></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a :href="route('ads.pages.preview', [current, p.id])" target="_blank" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200">Aperçu</a>
                            <button @click="startEdit(p)" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200">Modifier</button>
                            <button v-if="p.status !== 'live'" @click="act(p, 'publish')" :disabled="busy === p.id" class="btn-brand !px-3 !py-1.5 text-xs">🚀 Publier</button>
                            <button v-else @click="act(p, 'pause')" :disabled="busy === p.id" class="rounded-lg border border-amber-400/30 px-3 py-1.5 text-xs font-semibold text-amber-300">Mettre en pause</button>
                            <button @click="removePage(p)" class="rounded-lg px-2 py-1.5 text-xs text-rose-300 hover:bg-rose-500/10">🗑</button>
                        </div>
                    </div>

                    <div v-if="editing === p.id" class="mt-5 space-y-4 rounded-xl border border-white/[0.06] p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-xs font-semibold text-slate-400">Nom interne<input v-model="draft.title" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                            <label class="text-xs font-semibold text-slate-400">Objectif<select v-model="draft.goal" class="mt-1 w-full rounded-xl border border-white/10 bg-ink-900 px-3 py-2 text-sm text-white"><option v-for="(l, k) in goals" :key="k" :value="k">{{ goalLabel(k) }}</option></select></label>
                        </div>
                        <div class="grid gap-4" :class="draft.variants.length > 1 ? 'lg:grid-cols-2' : ''">
                            <div v-for="(v, i) in draft.variants" :key="i" class="space-y-2 rounded-xl bg-white/[0.03] p-3">
                                <div class="flex items-center justify-between"><p class="text-sm font-bold text-white">Variante {{ String.fromCharCode(65 + i) }}</p><button v-if="draft.variants.length > 1" @click="draft.variants.splice(i, 1)" class="text-xs text-rose-300">Retirer</button></div>
                                <label class="block text-xs text-slate-400">Titre<input v-model="v.headline" class="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                                <label class="block text-xs text-slate-400">Sous-titre<textarea v-model="v.subheadline" rows="2" class="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"></textarea></label>
                                <label class="block text-xs text-slate-400">Offre<input v-model="v.offer" class="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                                <label class="block text-xs text-slate-400">Bénéfices (un par ligne)<textarea v-model="v.bulletsText" rows="3" class="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"></textarea></label>
                                <label class="block text-xs text-slate-400">Bouton<input v-model="v.cta" class="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" /></label>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="draft.variants.length < 3" @click="addVariant" class="rounded-lg border border-dashed border-white/15 px-3 py-1.5 text-xs text-slate-300">+ Variante</button>
                            <span class="flex-1"></span>
                            <button @click="editing = null" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-300">Annuler</button>
                            <button @click="saveEdit(p)" :disabled="busy === p.id" class="btn-brand !px-4 !py-1.5 text-xs">Enregistrer{{ p.status === 'live' ? ' et republier' : '' }}</button>
                        </div>
                    </div>

                    <!-- Résultats A/B -->
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="py-2">Variante</th><th class="py-2 text-right">Visites</th><th class="py-2 text-right">Demandes</th><th class="py-2 text-right">dont appels</th><th class="py-2 text-right">Taux</th><th></th></tr></thead>
                            <tbody class="divide-y divide-white/[0.04]">
                                <tr v-for="v in p.variants" :key="v.key">
                                    <td class="py-2 pr-3"><span class="font-bold text-white">{{ v.key }}</span> <span class="text-slate-400">· {{ v.headline }}</span><span v-if="p.winner === v.key" class="chip ml-2 bg-emerald-500/15 text-emerald-300">100 % du trafic</span></td>
                                    <td class="py-2 text-right text-slate-300">{{ p.results.variants[v.key]?.views ?? 0 }}</td>
                                    <td class="py-2 text-right font-semibold text-white">{{ p.results.variants[v.key]?.conversions ?? 0 }}</td>
                                    <td class="py-2 text-right text-slate-400">{{ p.results.variants[v.key]?.calls ?? 0 }}</td>
                                    <td class="py-2 text-right font-semibold" :class="p.results.verdict?.winner === v.key ? 'text-emerald-300' : 'text-white'">{{ (p.results.variants[v.key]?.rate ?? 0).toString().replace('.', ',') }} %</td>
                                    <td class="py-2 text-right"><button v-if="p.variants.length > 1 && p.winner !== v.key" @click="act(p, 'winner', v.key)" class="text-xs text-brand-400">Garder {{ v.key }}</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <p :class="p.results.verdict?.status === 'winner' ? 'text-emerald-300' : 'text-slate-400'">{{ verdictText(p.results) }}</p>
                            <button v-if="p.winner || Object.values(p.results.variants).some((r) => r.views)" @click="act(p, 'reset')" class="text-xs text-slate-500 hover:text-white">Relancer le test</button>
                        </div>
                    </div>
                </div>
                <p v-if="!pages.length" class="text-sm text-slate-500">Aucune page d'offre pour le moment.</p>
            </div>

            <!-- 3. Liens de suivi -->
            <div v-else class="mt-6 glass max-w-3xl rounded-2xl p-6">
                <h2 class="font-semibold text-white">Lien à mettre dans votre publicité</h2>
                <p class="mt-1 text-sm text-slate-400">Ce lien permet de savoir quelles publicités vous amènent des demandes. Collez-le dans le champ « URL du site web » de votre pub.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <label class="text-xs font-semibold text-slate-400 sm:col-span-3">Page de destination
                        <select v-model="link.target" class="mt-1 w-full rounded-xl border border-white/10 bg-ink-900 px-3 py-2.5 text-sm text-white">
                            <option value="">Mon site ({{ site_url }})</option>
                            <option v-for="p in pages.filter((x) => x.status === 'live')" :key="p.id" :value="p.url">Page d'offre · {{ p.title }}</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-400">Plateforme<select v-model="link.source" class="mt-1 w-full rounded-xl border border-white/10 bg-ink-900 px-3 py-2.5 text-sm text-white"><option value="meta">Facebook / Instagram</option><option value="google">Google</option><option value="tiktok">TikTok</option><option value="flyer">Flyer / QR code</option></select></label>
                    <label class="text-xs font-semibold text-slate-400 sm:col-span-2">Nom de la campagne<input v-model="link.campaign" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white" placeholder="offre-novembre" /></label>
                </div>
                <div class="mt-4 flex gap-2"><input :value="trackUrl" readonly class="flex-1 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 font-mono text-xs text-slate-200" @focus="$event.target.select()" /><button @click="copy(trackUrl, 'track')" class="btn-brand text-sm">{{ copied === 'track' ? 'Copié ✓' : 'Copier' }}</button></div>
            </div>
        </template>
    </AuthenticatedLayout>
</template>
