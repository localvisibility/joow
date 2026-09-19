<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    stats: { type: Object, default: () => ({ sites: 0, rating: 4.8, sectors: 0 }) },
    examples: { type: Array, default: () => [] },
});

const form = useForm({ query: '', email: '' });

const q = ref('');
const results = ref([]);
const open = ref(false);
const selected = ref(null);
const searching = ref(false);
let debounce = null;

watch(q, (val) => {
    if (selected.value && val === selected.value.main) return;
    selected.value = null;
    form.query = '';
    clearTimeout(debounce);
    if (val.trim().length < 3) { results.value = []; open.value = false; return; }
    searching.value = true;
    debounce = setTimeout(async () => {
        try {
            const r = await fetch(route('public.search', { q: val }), { headers: { Accept: 'application/json' } });
            const d = await r.json();
            results.value = d.results || [];
            open.value = results.value.length > 0;
        } catch (e) { results.value = []; }
        finally { searching.value = false; }
    }, 300);
});

const pick = (r) => {
    selected.value = r;
    form.query = r.place_id;
    q.value = r.main;
    open.value = false;
    results.value = [];
};

const reset = () => {
    selected.value = null;
    form.query = '';
    q.value = '';
    results.value = [];
    open.value = false;
};

const submit = () => {
    if (!form.query) return;
    form.post(route('public.generate'));
};

const scrollToSearch = () => {
    document.getElementById('start')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => document.getElementById('search-input')?.focus(), 400);
};

// Compteur animé sur la preuve sociale
const displaySites = ref(0);
const targetSites = computed(() => props.stats.sites || 0);
onMounted(() => {
    const target = targetSites.value;
    if (target <= 0) return;
    const dur = 1200, start = performance.now();
    const tick = (now) => {
        const p = Math.min((now - start) / dur, 1);
        displaySites.value = Math.round(target * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
});

const sectorLabels = {
    restaurant: 'Restaurant', boulangerie: 'Boulangerie', coiffeur: 'Coiffure', beaute: 'Beauté',
    sante: 'Santé', batiment: 'Bâtiment', auto: 'Automobile', immobilier: 'Immobilier',
    avocat: 'Juridique', fleuriste: 'Fleuriste', hotel: 'Hôtellerie', sport: 'Sport & bien-être',
};
const labelOf = (s) => sectorLabels[s] || (s ? s.charAt(0).toUpperCase() + s.slice(1) : 'Local');
const emojiOf = (s) => ({
    restaurant: '🍽️', boulangerie: '🥐', coiffeur: '💇', beaute: '💅', sante: '⚕️',
    batiment: '🏗️', auto: '🚗', immobilier: '🏠', avocat: '⚖️', fleuriste: '💐', hotel: '🏨', sport: '🏋️',
}[s] || '📍');

const steps = [
    { n: '1', t: 'Trouvez votre établissement', d: 'Tapez son nom — on le retrouve instantanément sur Google.' },
    { n: '2', t: "L'IA construit tout", d: 'Textes, photos, avis, design premium — assemblés automatiquement.' },
    { n: '3', t: 'En ligne en un clic', d: 'Votre site est prêt. Vous le publiez sur votre domaine immédiatement.' },
];

const benefits = [
    { icon: '⚡', t: 'Prêt en 30 secondes', d: 'Pas de rendez-vous, pas de devis. Votre site apparaît sous vos yeux, tout de suite.' },
    { icon: '🎨', t: 'Design digne d\'une agence', d: 'Une architecture pensée pour votre métier — chaque secteur a son identité visuelle premium.' },
    { icon: '⭐', t: 'Vos vrais avis Google', d: 'Vos notes et avis clients sont intégrés automatiquement pour rassurer vos visiteurs.' },
    { icon: '📱', t: 'Parfait sur mobile', d: '8 visiteurs sur 10 vous cherchent sur leur téléphone. Votre site est impeccable partout.' },
    { icon: '🔍', t: 'Visible sur Google', d: 'Optimisé pour le référencement local dès la première seconde. Vos clients vous trouvent.' },
    { icon: '✏️', t: 'Modifiable à volonté', d: 'Un éditeur simple pour tout changer quand vous voulez. Aucune compétence requise.' },
];

const testimonials = [
    { q: 'En 2 minutes j\'avais un site plus beau que celui que je payais 1 500 €. Mes clients me le disent.', a: 'Sophie M.', r: 'Institut de beauté, Lyon' },
    { q: 'Je pensais que ce serait compliqué. J\'ai cherché mon restaurant, cliqué, c\'était fait. Bluffant.', a: 'Karim B.', r: 'Restaurant, Marseille' },
    { q: 'Les avis Google intégrés directement, le design, tout y était. J\'ai publié le soir même.', a: 'Laurent D.', r: 'Garage automobile, Toulouse' },
];

const faqs = [
    { q: 'Combien ça coûte ?', a: 'La création et l\'aperçu sont 100 % gratuits. Vous ne payez que si vous décidez de mettre votre site en ligne : 9,90 €/mois, hébergement inclus, sans engagement.' },
    { q: 'Ai-je besoin de compétences techniques ?', a: 'Aucune. Vous cherchez votre établissement, l\'IA fait le reste. Vous pouvez ensuite tout modifier depuis un éditeur simple.' },
    { q: 'D\'où viennent les textes et les photos ?', a: 'De votre fiche Google (avis, photos, horaires) enrichis par notre IA qui rédige des contenus adaptés à votre métier.' },
    { q: 'Puis-je utiliser mon propre nom de domaine ?', a: 'Oui. Votre site est livré sur une adresse joow.fr, et vous pouvez y brancher votre propre domaine à tout moment.' },
    { q: 'Et si je ne suis pas satisfait ?', a: 'L\'aperçu est gratuit et sans engagement : vous ne payez rien tant que vous n\'avez pas décidé de publier. Résiliable en un clic ensuite.' },
];
const openFaq = ref(null);
</script>

<template>
    <Head title="Créez votre site pro en 30 secondes — Joow" />

    <div class="min-h-screen bg-ink-950 bg-mesh text-slate-200">
        <header class="sticky top-0 z-40 border-b border-white/[0.04] bg-ink-950/70 backdrop-blur-lg">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-gradient font-display text-lg font-bold text-white shadow-glow">J</span>
                    <span class="font-display text-xl font-bold text-white">joow</span>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('login')" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 transition hover:text-white">Se connecter</Link>
                    <button @click="scrollToSearch" class="btn-brand hidden text-sm sm:inline-flex">Créer mon site</button>
                </div>
            </div>
        </header>

        <!-- ═══ HERO + RECHERCHE ═══ -->
        <section id="start" class="relative mx-auto max-w-3xl px-5 pb-16 pt-16 text-center sm:pt-24">
            <div class="pointer-events-none absolute -top-10 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="relative">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400">
                    ⚡ Propulsé par l'IA · à partir de votre fiche Google
                </p>
                <h1 class="font-display text-4xl font-bold leading-[1.05] text-white sm:text-6xl">
                    Votre site professionnel<br><span class="bg-brand-gradient bg-clip-text text-transparent">en 30 secondes.</span>
                </h1>
                <p class="mx-auto mt-5 max-w-xl text-lg text-slate-400">
                    Cherchez votre établissement : Joow génère un site complet — textes, photos, avis, design premium. Sans compte, sans attente.
                </p>

                <form @submit.prevent="submit" class="mx-auto mt-10 max-w-xl">
                    <!-- Recherche intelligente -->
                    <div class="relative" v-show="!selected">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a1 1 0 01-1.42 1.42l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                        <input id="search-input" v-model="q" type="text" autocomplete="off" class="field pl-12 text-base"
                            placeholder="Nom de votre établissement…" @focus="open = results.length > 0" />
                        <div v-if="searching" class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-white/20 border-t-brand-500"></div>

                        <!-- Dropdown résultats -->
                        <div v-if="open" class="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-white/10 bg-ink-800 text-left shadow-2xl">
                            <button v-for="r in results" :key="r.place_id" type="button" @click="pick(r)"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/5">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/5 text-slate-400"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6c0 4.314 4.686 9.44 5.29 10.08a1 1 0 001.42 0C11.314 17.44 16 12.314 16 8a6 6 0 00-6-6zm0 8a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg></span>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-white">{{ r.main }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ r.secondary }}</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Confirmation de l'établissement sélectionné -->
                    <div v-if="selected" class="glass flex items-center gap-3 rounded-2xl border-emerald-400/20 bg-emerald-500/[0.06] p-4 text-left">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-500/15 text-xl">✓</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-white">{{ selected.main }}</span>
                            <span class="block truncate text-xs text-slate-400">{{ selected.secondary }}</span>
                        </span>
                        <button type="button" @click="reset" class="shrink-0 rounded-lg px-2 py-1 text-xs text-slate-400 transition hover:text-white">Changer</button>
                    </div>

                    <p v-if="form.errors.query" class="mt-2 text-sm text-rose-400">{{ form.errors.query }}</p>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input v-model="form.email" type="email" class="field sm:flex-1" placeholder="Votre email (optionnel)" />
                        <button type="submit" class="btn-brand shrink-0" :class="{ 'opacity-50': !form.query || form.processing }" :disabled="!form.query || form.processing">
                            {{ form.processing ? 'Création…' : selected ? 'Générer mon site →' : 'Créer mon site' }}
                        </button>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Gratuit · sans engagement · aperçu immédiat</p>
                </form>

                <!-- Preuve sociale rapide -->
                <div class="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-slate-400">
                    <span class="flex items-center gap-2">
                        <span class="flex -space-x-1.5">
                            <span class="h-6 w-6 rounded-full border-2 border-ink-950 bg-brand-gradient"></span>
                            <span class="h-6 w-6 rounded-full border-2 border-ink-950 bg-emerald-500"></span>
                            <span class="h-6 w-6 rounded-full border-2 border-ink-950 bg-amber-500"></span>
                        </span>
                        <strong class="text-white">{{ displaySites.toLocaleString('fr-FR') }}+</strong> sites créés
                    </span>
                    <span class="flex items-center gap-1.5"><span class="text-amber-400">★★★★★</span> <strong class="text-white">{{ stats.rating }}</strong>/5</span>
                    <span class="hidden items-center gap-1.5 sm:flex"><span class="text-brand-400">✦</span> {{ stats.sectors }} secteurs couverts</span>
                </div>
            </div>
        </section>

        <!-- ═══ EXEMPLES DE SITES ═══ -->
        <section v-if="examples.length" class="mx-auto max-w-6xl px-5 py-16">
            <div class="mb-10 text-center">
                <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Des sites déjà en ligne</h2>
                <p class="mt-3 text-slate-400">Voici quelques établissements qui ont créé leur site avec Joow.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <a v-for="ex in examples" :key="ex.slug" :href="ex.url" target="_blank" rel="noopener"
                    class="glass group relative overflow-hidden rounded-2xl p-5 transition hover:border-white/20 hover:shadow-glow">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/5 text-xl">{{ emojiOf(ex.sector) }}</span>
                        <span class="min-w-0">
                            <span class="block truncate font-display font-bold text-white">{{ ex.name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ ex.city || labelOf(ex.sector) }}</span>
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-300">{{ labelOf(ex.sector) }}</span>
                        <span class="text-sm text-slate-500 transition group-hover:text-brand-400">Voir le site ↗</span>
                    </div>
                </a>
            </div>
        </section>

        <!-- ═══ COMMENT ÇA MARCHE ═══ -->
        <section class="mx-auto max-w-5xl px-5 py-16">
            <div class="mb-10 text-center">
                <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Trois étapes, zéro effort</h2>
                <p class="mt-3 text-slate-400">Pas de brief, pas de graphiste, pas de semaine d'attente.</p>
            </div>
            <div class="grid gap-6 sm:grid-cols-3">
                <div v-for="s in steps" :key="s.n" class="glass relative rounded-2xl p-6">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand-gradient font-display font-bold text-white">{{ s.n }}</div>
                    <h3 class="mt-4 font-display text-lg font-bold text-white">{{ s.t }}</h3>
                    <p class="mt-1.5 text-sm text-slate-400">{{ s.d }}</p>
                </div>
            </div>
        </section>

        <!-- ═══ BÉNÉFICES ═══ -->
        <section class="mx-auto max-w-6xl px-5 py-16">
            <div class="mb-10 text-center">
                <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Tout ce qu'un pro attend d'un site</h2>
                <p class="mt-3 text-slate-400">Sans le prix ni les délais d'une agence.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="b in benefits" :key="b.t" class="glass rounded-2xl p-6">
                    <div class="grid h-12 w-12 place-items-center rounded-xl bg-white/5 text-2xl">{{ b.icon }}</div>
                    <h3 class="mt-4 font-display text-lg font-bold text-white">{{ b.t }}</h3>
                    <p class="mt-1.5 text-sm text-slate-400">{{ b.d }}</p>
                </div>
            </div>
        </section>

        <!-- ═══ TÉMOIGNAGES ═══ -->
        <section class="mx-auto max-w-6xl px-5 py-16">
            <div class="mb-10 text-center">
                <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Ils ont créé leur site en quelques secondes</h2>
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <figure v-for="t in testimonials" :key="t.a" class="glass rounded-2xl p-6">
                    <div class="text-amber-400">★★★★★</div>
                    <blockquote class="mt-3 text-slate-200">« {{ t.q }} »</blockquote>
                    <figcaption class="mt-4 text-sm">
                        <span class="font-semibold text-white">{{ t.a }}</span>
                        <span class="block text-slate-500">{{ t.r }}</span>
                    </figcaption>
                </figure>
            </div>
        </section>

        <!-- ═══ TARIF ═══ -->
        <section class="mx-auto max-w-3xl px-5 py-16">
            <div class="glass relative overflow-hidden rounded-3xl p-8 text-center sm:p-10">
                <div class="pointer-events-none absolute -top-12 left-1/2 h-48 w-48 -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
                <div class="relative">
                    <p class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-semibold text-emerald-300">Aperçu 100 % gratuit</p>
                    <h2 class="mt-4 font-display text-3xl font-bold text-white">Un seul tarif, tout compris</h2>
                    <p class="mt-6 font-display text-5xl font-bold text-white">9,90€<span class="text-lg font-medium text-slate-400">/mois</span></p>
                    <ul class="mx-auto mt-6 max-w-sm space-y-2 text-left text-sm text-slate-300">
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Site en ligne, hébergement inclus</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Éditeur pour tout modifier vous-même</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Avis Google, photos et référencement local</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Sans engagement, résiliable en un clic</li>
                    </ul>
                    <button @click="scrollToSearch" class="btn-brand mt-8">Créer mon site gratuitement →</button>
                    <p class="mt-3 text-xs text-slate-500">Vous ne payez qu'au moment de publier. Rien avant.</p>
                </div>
            </div>
        </section>

        <!-- ═══ FAQ ═══ -->
        <section class="mx-auto max-w-3xl px-5 py-16">
            <div class="mb-10 text-center">
                <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Questions fréquentes</h2>
            </div>
            <div class="space-y-3">
                <div v-for="(f, i) in faqs" :key="i" class="glass overflow-hidden rounded-2xl">
                    <button type="button" @click="openFaq = openFaq === i ? null : i" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                        <span class="font-semibold text-white">{{ f.q }}</span>
                        <span class="shrink-0 text-brand-400 transition" :class="{ 'rotate-45': openFaq === i }">+</span>
                    </button>
                    <div v-show="openFaq === i" class="px-5 pb-4 text-sm text-slate-400">{{ f.a }}</div>
                </div>
            </div>
        </section>

        <!-- ═══ CTA FINAL ═══ -->
        <section class="mx-auto max-w-4xl px-5 py-20 text-center">
            <h2 class="font-display text-3xl font-bold text-white sm:text-5xl">Votre site vous attend.</h2>
            <p class="mx-auto mt-4 max-w-lg text-lg text-slate-400">Cherchez votre établissement et voyez le résultat en 30 secondes. C'est gratuit.</p>
            <button @click="scrollToSearch" class="btn-brand mt-8 text-base">Créer mon site maintenant →</button>
        </section>

        <footer class="border-t border-white/[0.06] py-8 text-center text-xs text-slate-600">© 2026 Joow — Vos sites, en mieux.</footer>
    </div>
</template>
