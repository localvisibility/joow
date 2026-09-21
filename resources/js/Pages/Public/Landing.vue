<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({ sites: 0, rating: 4.8, sectors: 0 }) },
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
const reset = () => { selected.value = null; form.query = ''; q.value = ''; results.value = []; open.value = false; };
const submit = () => { if (form.query) form.post(route('public.generate')); };

const scrollToSearch = () => {
    document.getElementById('start')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => document.getElementById('search-input')?.focus(), 500);
};

// Compteur animé
const displaySites = ref(0);
onMounted(() => {
    const target = props.stats.sites || 0;
    if (target > 0) {
        const dur = 1400, start = performance.now();
        const tick = (now) => {
            const p = Math.min((now - start) / dur, 1);
            displaySites.value = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }
    // Reveal on scroll
    const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('on'); io.unobserve(e.target); } }), { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
    // Spotlight qui suit le curseur (hero)
    window.addEventListener('pointermove', onPointer, { passive: true });
});
onUnmounted(() => window.removeEventListener('pointermove', onPointer));
const glow = ref({ x: 50, y: 30 });
const onPointer = (e) => {
    const h = document.getElementById('hero-wrap'); if (!h) return;
    const r = h.getBoundingClientRect();
    if (e.clientY > r.bottom) return;
    glow.value = { x: (e.clientX / window.innerWidth) * 100, y: (e.clientY / r.height) * 100 };
};
const glowStyle = computed(() => `background: radial-gradient(600px circle at ${glow.value.x}% ${glow.value.y}%, rgba(129,140,248,0.16), transparent 45%)`);

// Vitrine : captures réelles de sites générés par Joow
const showcase = [
    { img: '/showcase/restaurant.webp', name: 'La Table d\'Émile', city: 'Lyon', sector: 'Restaurant', domain: 'latabledemile' },
    { img: '/showcase/beaute.webp', name: 'Studio Éclat', city: 'Paris', sector: 'Beauté', domain: 'studio-eclat' },
    { img: '/showcase/renovation.webp', name: 'ProRénov Bâtiment', city: 'Bordeaux', sector: 'Rénovation', domain: 'prorenov' },
    { img: '/showcase/immobilier.webp', name: 'Horizon Immobilier', city: 'Aix-en-Provence', sector: 'Immobilier', domain: 'horizon-immo' },
    { img: '/showcase/sante.webp', name: 'Ostéo Concorde', city: 'Nantes', sector: 'Santé', domain: 'osteo-concorde' },
    { img: '/showcase/hebergement.webp', name: 'Villa Belrose', city: 'Saint-Tropez', sector: 'Hôtellerie', domain: 'villa-belrose' },
];
const rowA = [...showcase, ...showcase];
const rowB = [...[...showcase].reverse(), ...[...showcase].reverse()];
const lightbox = ref(null);

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
    { q: 'Combien ça coûte ?', a: 'La création et l\'aperçu sont 100 % gratuits. Vous ne payez que si vous publiez : formule Pro à 39€ HT/mois (7 jours d\'essai gratuit, sans engagement) ou formule Liberté à 349€ HT en paiement unique, site à vie.' },
    { q: 'Ai-je besoin de compétences techniques ?', a: 'Aucune. Vous cherchez votre établissement, l\'IA fait le reste. Vous pouvez ensuite tout modifier depuis un éditeur simple.' },
    { q: 'D\'où viennent les textes et les photos ?', a: 'De votre fiche Google (avis, photos, horaires) enrichis par notre IA qui rédige des contenus adaptés à votre métier.' },
    { q: 'Puis-je utiliser mon propre nom de domaine ?', a: 'Oui. Votre site est livré sur une adresse joow.fr, et vous pouvez y brancher votre propre domaine à tout moment.' },
    { q: 'Et si je ne suis pas satisfait ?', a: 'L\'aperçu est gratuit et sans engagement : vous ne payez rien tant que vous n\'avez pas décidé de publier. Résiliable en un clic ensuite.' },
];
const openFaq = ref(null);
</script>

<template>
    <Head title="Créez votre site pro en 30 secondes — Joow" />

    <div class="min-h-screen overflow-x-hidden bg-ink-950 text-slate-200">
        <!-- Aurora background -->
        <div class="pointer-events-none fixed inset-0 z-0">
            <div class="aurora aurora-1"></div>
            <div class="aurora aurora-2"></div>
            <div class="aurora aurora-3"></div>
            <div class="grain absolute inset-0"></div>
        </div>

        <div class="relative z-10">
            <header class="sticky top-0 z-40 border-b border-white/[0.04] bg-ink-950/60 backdrop-blur-xl">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
                    <a href="/" class="block" aria-label="Joow"><BrandLogo class="h-9 w-auto" /></a>
                    <div class="flex items-center gap-2">
                        <Link :href="route('login')" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 transition hover:text-white">Se connecter</Link>
                        <button @click="scrollToSearch" class="btn-brand hidden text-sm sm:inline-flex">Créer mon site</button>
                    </div>
                </div>
            </header>

            <!-- ═══ HERO ═══ -->
            <div id="hero-wrap" class="relative">
                <div class="pointer-events-none absolute inset-0" :style="glowStyle"></div>
                <section id="start" class="relative mx-auto max-w-3xl px-5 pb-14 pt-20 text-center sm:pt-28">
                    <p class="reveal mb-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400 backdrop-blur">
                        ⚡ Propulsé par l'IA · à partir de votre fiche Google
                    </p>
                    <h1 class="reveal font-display text-5xl font-bold leading-[1.02] text-white sm:text-7xl">
                        Le site pro<br>de votre métier,<br><span class="text-shimmer">en 30 secondes.</span>
                    </h1>
                    <p class="reveal mx-auto mt-6 max-w-xl text-lg text-slate-400">
                        Cherchez votre établissement : Joow génère un site complet — textes, photos, avis, design premium. Sans compte, sans attente.
                    </p>

                    <form @submit.prevent="submit" class="reveal mx-auto mt-10 max-w-xl">
                        <div class="relative" v-show="!selected">
                            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a1 1 0 01-1.42 1.42l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                            <input id="search-input" v-model="q" type="text" autocomplete="off" class="field pl-12 text-base shadow-glow"
                                placeholder="Nom de votre établissement…" @focus="open = results.length > 0" />
                            <div v-if="searching" class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-white/20 border-t-brand-500"></div>
                            <div v-if="open" class="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-white/10 bg-ink-800 text-left shadow-2xl">
                                <button v-for="r in results" :key="r.place_id" type="button" @click="pick(r)" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/5">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/5 text-slate-400"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6c0 4.314 4.686 9.44 5.29 10.08a1 1 0 001.42 0C11.314 17.44 16 12.314 16 8a6 6 0 00-6-6zm0 8a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg></span>
                                    <span class="min-w-0"><span class="block truncate font-semibold text-white">{{ r.main }}</span><span class="block truncate text-xs text-slate-500">{{ r.secondary }}</span></span>
                                </button>
                            </div>
                        </div>
                        <div v-if="selected" class="glass flex items-center gap-3 rounded-2xl border-emerald-400/20 bg-emerald-500/[0.06] p-4 text-left">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-500/15 text-xl">✓</span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-white">{{ selected.main }}</span><span class="block truncate text-xs text-slate-400">{{ selected.secondary }}</span></span>
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

                    <div class="reveal mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-slate-400">
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
                </section>
            </div>

            <!-- ═══ CARROUSEL DE CAPTURES ═══ -->
            <section class="relative py-16">
                <div class="mb-10 px-5 text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-400">La vitrine Joow</p>
                    <h2 class="mt-3 font-display text-3xl font-bold text-white sm:text-4xl">Des sites qui donnent envie de cliquer</h2>
                    <p class="mx-auto mt-3 max-w-xl text-slate-400">De vraies pages générées par Joow. Chaque métier, sa propre identité premium.</p>
                </div>

                <!-- Ligne 1 -->
                <div class="marquee-mask relative">
                    <div class="flex w-max gap-6 marquee-left">
                        <button v-for="(s, i) in rowA" :key="'a'+i" type="button" @click="lightbox = s" class="group w-[340px] shrink-0 sm:w-[440px]">
                            <div class="browser-frame">
                                <div class="browser-bar">
                                    <span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span>
                                    <span class="url">{{ s.domain }}.joow.fr</span>
                                </div>
                                <div class="relative aspect-[16/10] overflow-hidden">
                                    <img :src="s.img" :alt="s.name" loading="lazy" class="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.04]" />
                                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-black/70 to-transparent p-3">
                                        <span class="text-sm font-semibold text-white">{{ s.name }} · {{ s.city }}</span>
                                        <span class="chip bg-white/15 text-white backdrop-blur">{{ s.sector }}</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
                <!-- Ligne 2 -->
                <div class="marquee-mask relative mt-6">
                    <div class="flex w-max gap-6 marquee-right">
                        <button v-for="(s, i) in rowB" :key="'b'+i" type="button" @click="lightbox = s" class="group w-[340px] shrink-0 sm:w-[440px]">
                            <div class="browser-frame">
                                <div class="browser-bar">
                                    <span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span>
                                    <span class="url">{{ s.domain }}.joow.fr</span>
                                </div>
                                <div class="relative aspect-[16/10] overflow-hidden">
                                    <img :src="s.img" :alt="s.name" loading="lazy" class="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.04]" />
                                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-black/70 to-transparent p-3">
                                        <span class="text-sm font-semibold text-white">{{ s.name }} · {{ s.city }}</span>
                                        <span class="chip bg-white/15 text-white backdrop-blur">{{ s.sector }}</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="mt-10 text-center">
                    <button @click="scrollToSearch" class="btn-brand">Créer le mien maintenant →</button>
                </div>
            </section>

            <!-- ═══ ÉTAPES ═══ -->
            <section class="mx-auto max-w-5xl px-5 py-16">
                <div class="reveal mb-10 text-center">
                    <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Trois étapes, zéro effort</h2>
                    <p class="mt-3 text-slate-400">Pas de brief, pas de graphiste, pas de semaine d'attente.</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-3">
                    <div v-for="s in steps" :key="s.n" class="reveal glass rounded-2xl p-6">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand-gradient font-display font-bold text-white">{{ s.n }}</div>
                        <h3 class="mt-4 font-display text-lg font-bold text-white">{{ s.t }}</h3>
                        <p class="mt-1.5 text-sm text-slate-400">{{ s.d }}</p>
                    </div>
                </div>
            </section>

            <!-- ═══ BÉNÉFICES ═══ -->
            <section class="mx-auto max-w-6xl px-5 py-16">
                <div class="reveal mb-10 text-center">
                    <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Tout ce qu'un pro attend d'un site</h2>
                    <p class="mt-3 text-slate-400">Sans le prix ni les délais d'une agence.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="b in benefits" :key="b.t" class="reveal glass glass-hover rounded-2xl p-6">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-white/5 text-2xl">{{ b.icon }}</div>
                        <h3 class="mt-4 font-display text-lg font-bold text-white">{{ b.t }}</h3>
                        <p class="mt-1.5 text-sm text-slate-400">{{ b.d }}</p>
                    </div>
                </div>
            </section>

            <!-- ═══ TÉMOIGNAGES ═══ -->
            <section class="mx-auto max-w-6xl px-5 py-16">
                <div class="reveal mb-10 text-center">
                    <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Ils ont créé leur site en quelques secondes</h2>
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <figure v-for="t in testimonials" :key="t.a" class="reveal glass rounded-2xl p-6">
                        <div class="text-amber-400">★★★★★</div>
                        <blockquote class="mt-3 text-slate-200">« {{ t.q }} »</blockquote>
                        <figcaption class="mt-4 text-sm"><span class="font-semibold text-white">{{ t.a }}</span><span class="block text-slate-500">{{ t.r }}</span></figcaption>
                    </figure>
                </div>
            </section>

            <!-- ═══ TARIF ═══ -->
            <section class="mx-auto max-w-3xl px-5 py-16">
                <div class="reveal glass relative overflow-hidden rounded-3xl p-8 text-center sm:p-10">
                    <div class="pointer-events-none absolute -top-12 left-1/2 h-48 w-48 -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
                    <div class="relative">
                        <p class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-semibold text-emerald-300">Aperçu 100 % gratuit</p>
                        <h2 class="mt-4 font-display text-3xl font-bold text-white">Deux formules, tout compris</h2>
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-brand-500/40 bg-white/[0.03] p-5 text-left">
                                <p class="text-xs font-bold uppercase tracking-wide text-brand-400">Pro · recommandé</p>
                                <p class="mt-2 font-display text-3xl font-bold text-white">39€<span class="text-sm font-medium text-slate-400"> HT/mois</span></p>
                                <p class="mt-1 text-xs font-semibold text-emerald-300">🎉 7 jours d'essai gratuit</p>
                                <ul class="mt-3 space-y-1 text-xs text-slate-300">
                                    <li>✓ Domaine .com/.fr + hébergement</li>
                                    <li>✓ Éditeur IA illimité · SSL · SEO</li>
                                    <li>✓ Sans engagement</li>
                                </ul>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 text-left">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-300">Liberté · à vie</p>
                                <p class="mt-2 font-display text-3xl font-bold text-white">349€<span class="text-sm font-medium text-slate-400"> HT</span></p>
                                <p class="mt-1 text-xs font-semibold text-brand-400">Paiement unique</p>
                                <ul class="mt-3 space-y-1 text-xs text-slate-300">
                                    <li>✓ Domaine + hébergement 1 an</li>
                                    <li>✓ Éditeur illimité</li>
                                    <li>✓ Téléchargement du site</li>
                                </ul>
                            </div>
                        </div>
                        <button @click="scrollToSearch" class="btn-brand mt-8">Créer mon site gratuitement →</button>
                        <p class="mt-3 text-xs text-slate-500">Vous ne payez qu'au moment de publier. Rien avant.</p>
                    </div>
                </div>
            </section>

            <!-- ═══ FAQ ═══ -->
            <section class="mx-auto max-w-3xl px-5 py-16">
                <div class="reveal mb-10 text-center">
                    <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Questions fréquentes</h2>
                </div>
                <div class="space-y-3">
                    <div v-for="(f, i) in faqs" :key="i" class="reveal glass overflow-hidden rounded-2xl">
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
                <h2 class="reveal font-display text-3xl font-bold text-white sm:text-5xl">Votre site vous attend.</h2>
                <p class="reveal mx-auto mt-4 max-w-lg text-lg text-slate-400">Cherchez votre établissement et voyez le résultat en 30 secondes. C'est gratuit.</p>
                <button @click="scrollToSearch" class="btn-brand reveal mt-8 text-base">Créer mon site maintenant →</button>
            </section>

            <footer class="flex flex-col items-center gap-3 border-t border-white/[0.06] py-10 text-center text-xs text-slate-600">
                <BrandLogo class="h-7 w-auto opacity-80" />
                <span>© 2026 Joow — Vos sites, en mieux.</span>
            </footer>
        </div>

        <!-- Lightbox -->
        <Transition name="fade">
            <div v-if="lightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur" @click="lightbox = null">
                <div class="w-full max-w-5xl" @click.stop>
                    <div class="browser-frame shadow-2xl">
                        <div class="browser-bar">
                            <span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span>
                            <span class="url">{{ lightbox.domain }}.joow.fr</span>
                        </div>
                        <img :src="lightbox.img" :alt="lightbox.name" class="w-full" />
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-slate-300"><strong class="text-white">{{ lightbox.name }}</strong> · {{ lightbox.city }} — généré par Joow</p>
                        <button @click="scrollToSearch(); lightbox = null" class="btn-brand text-sm">Créer le mien →</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* Aurora */
.aurora { position: absolute; border-radius: 9999px; filter: blur(90px); opacity: .5; }
.aurora-1 { top: -10%; left: -5%; width: 45vw; height: 45vw; background: radial-gradient(circle, rgba(99,102,241,.5), transparent 70%); animation: drift1 22s ease-in-out infinite; }
.aurora-2 { top: 20%; right: -10%; width: 40vw; height: 40vw; background: radial-gradient(circle, rgba(168,85,247,.4), transparent 70%); animation: drift2 26s ease-in-out infinite; }
.aurora-3 { bottom: -10%; left: 25%; width: 42vw; height: 42vw; background: radial-gradient(circle, rgba(56,189,248,.28), transparent 70%); animation: drift3 30s ease-in-out infinite; }
@keyframes drift1 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(6vw,4vw)} }
@keyframes drift2 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-5vw,5vw)} }
@keyframes drift3 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(4vw,-4vw)} }
.grain { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E"); mix-blend-mode: overlay; opacity: .35; }

/* Shimmer titre */
.text-shimmer { background: linear-gradient(100deg,#818cf8 20%,#e879f9 40%,#38bdf8 60%,#818cf8 80%); background-size: 200% auto; -webkit-background-clip: text; background-clip: text; color: transparent; animation: shimmer 5s linear infinite; }
@keyframes shimmer { to { background-position: 200% center; } }

/* Reveal */
.reveal { opacity: 0; transform: translateY(26px); transition: opacity .8s cubic-bezier(.22,1,.36,1), transform .8s cubic-bezier(.22,1,.36,1); }
.reveal.on { opacity: 1; transform: none; }

/* Marquee */
.marquee-mask { -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent); mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent); }
.marquee-left { animation: ml 46s linear infinite; }
.marquee-right { animation: mr 46s linear infinite; }
.marquee-left:hover, .marquee-right:hover { animation-play-state: paused; }
@keyframes ml { to { transform: translateX(-50%); } }
@keyframes mr { from { transform: translateX(-50%); } to { transform: translateX(0); } }

/* Browser frame */
.browser-frame { overflow: hidden; border-radius: 1rem; border: 1px solid rgba(255,255,255,.08); background: #14141f; box-shadow: 0 30px 60px -25px rgba(0,0,0,.7); }
.browser-bar { display: flex; align-items: center; gap: .35rem; border-bottom: 1px solid rgba(255,255,255,.06); padding: .55rem .8rem; }
.dot { height: .55rem; width: .55rem; border-radius: 9999px; display: inline-block; }
.url { margin-left: .6rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-radius: .4rem; background: rgba(255,255,255,.05); padding: .15rem .6rem; font-size: .7rem; color: #94a3b8; }

.fade-enter-active, .fade-leave-active { transition: opacity .25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (prefers-reduced-motion: reduce) {
  .aurora, .text-shimmer, .marquee-left, .marquee-right { animation: none; }
  .reveal { opacity: 1; transform: none; transition: none; }
}
</style>
