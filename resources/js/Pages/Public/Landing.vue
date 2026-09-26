<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({ sites: 0, rating: 4.8, sectors: 0 }) },
});

/* ───────────────────────── Recherche d'établissement ───────────────────────── */
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

const pick = (r) => { selected.value = r; form.query = r.place_id; q.value = r.main; open.value = false; results.value = []; };
const reset = () => { selected.value = null; form.query = ''; q.value = ''; results.value = []; open.value = false; };
const submit = () => { if (form.query) form.post(route('public.generate')); };

const scrollToSearch = () => {
    document.getElementById('start')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => document.getElementById('search-input')?.focus({ preventScroll: true }), 600);
};
const goTo = (id) => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });

/* Placeholder animé (métiers qui défilent) */
/* Machine à écrire dans le champ : des exemples se tapent lettre à lettre, et s'arrêtent dès que la personne clique pour écrire. */
const placeholders = ['Le Bistrot de Léa, Lyon', 'Garage Martin, Toulouse', 'Studio Éclat coiffure, Paris', 'Plomberie Durand, Nantes', 'Cabinet Ostéo Concorde', 'Villa Belrose, Saint-Tropez'];
const typed = ref('');
const twStopped = ref(false);
const focused = ref(false);
let twTimer = null;
const showTypewriter = computed(() => !twStopped.value && !focused.value && !q.value);
const runTypewriter = () => {
    let i = 0, pos = 0, deleting = false, pause = 0;
    const tick = () => {
        if (twStopped.value) return;
        const word = placeholders[i];
        if (pause > 0) { pause--; }
        else if (!deleting) { pos++; typed.value = word.slice(0, pos); if (pos >= word.length) { deleting = true; pause = 22; } }
        else { pos--; typed.value = word.slice(0, pos); if (pos <= 0) { deleting = false; i = (i + 1) % placeholders.length; pause = 6; } }
        twTimer = setTimeout(tick, deleting ? 35 : 70);
    };
    tick();
};
const stopTypewriter = () => { twStopped.value = true; clearTimeout(twTimer); };

/* ───────────────────────── Vitrine ───────────────────────── */
const showcase = [
    { key: 'restaurant', img: '/showcase/restaurant.webp', full: '/showcase/restaurant-full.webp', mobile: '/showcase/restaurant-mobile.webp', name: "La Table d'Émile", city: 'Lyon', sector: 'Restaurant', domain: 'latabledemile', tags: ['Réservation de table', 'Carte & QR code', 'Avis Google'] },
    { key: 'beaute', img: '/showcase/beaute.webp', full: '/showcase/beaute-full.webp', mobile: '/showcase/beaute-mobile.webp', name: 'Studio Éclat', city: 'Paris', sector: 'Beauté', domain: 'studio-eclat', tags: ['Prise de RDV', 'WhatsApp', 'Galerie'] },
    { key: 'renovation', img: '/showcase/renovation.webp', full: '/showcase/renovation-full.webp', mobile: '/showcase/renovation-mobile.webp', name: 'ProRénov Bâtiment', city: 'Bordeaux', sector: 'Rénovation', domain: 'prorenov', tags: ['Devis multi-étapes', 'Avant / après', 'Zone d\'intervention'] },
    { key: 'immobilier', img: '/showcase/immobilier.webp', full: '/showcase/immobilier-full.webp', mobile: '/showcase/immobilier-mobile.webp', name: 'Horizon Immobilier', city: 'Aix-en-Provence', sector: 'Immobilier', domain: 'horizon-immo', tags: ['Estimation en ligne', 'Avis Google', 'Assistant IA'] },
    { key: 'sante', img: '/showcase/sante.webp', full: '/showcase/sante-full.webp', mobile: '/showcase/sante-mobile.webp', name: 'Ostéo Concorde', city: 'Nantes', sector: 'Santé', domain: 'osteo-concorde', tags: ['Prise de RDV', 'FAQ patients', 'Horaires en direct'] },
    { key: 'hebergement', img: '/showcase/hebergement.webp', full: '/showcase/hebergement-full.webp', mobile: '/showcase/hebergement-mobile.webp', name: 'Villa Belrose', city: 'Saint-Tropez', sector: 'Hôtellerie', domain: 'villa-belrose', tags: ['Chambres & séjours', 'Acompte Stripe', 'Sync Airbnb / Booking'] },
];
const rowA = [...showcase, ...showcase];
const rowB = [...[...showcase].reverse(), ...[...showcase].reverse()];
const lightbox = ref(null);

/* Scène du hero : rotation automatique des secteurs */
const stage = ref(0);
const stageHover = ref(false);
const current = computed(() => showcase[stage.value]);
let stageTimer = null;

/* Vitrine détaillée : onglets */
const tab = ref(0);
const tabSite = computed(() => showcase[tab.value]);

/* ───────────────────────── Simulation de génération ───────────────────────── */
const genSteps = [
    { t: 'Lecture de votre fiche Google', d: 'Nom, adresse, horaires, 33 photos, 327 avis' },
    { t: 'Rédaction des textes par l\'IA', d: 'Accroche, services, FAQ, parcours client' },
    { t: 'Sélection des meilleures photos', d: 'Cadrage, qualité HD, mise en scène' },
    { t: 'Design adapté à votre métier', d: 'Palette, typographie, animations' },
    { t: 'Modules & formulaire activés', d: 'Réservation, avis, assistant IA' },
    { t: 'Votre site est en ligne', d: 'votre-nom.joow.fr — prêt à partager' },
];
const genStep = ref(0);
let genTimer = null;

/* ───────────────────────── Contenu ───────────────────────── */
const sectors = ['Restaurant', 'Coiffure & beauté', 'Plombier', 'Électricien', 'Rénovation', 'Hôtel & gîte', 'Ostéopathe', 'Dentiste', 'Garage', 'Immobilier', 'Avocat', 'Expert-comptable', 'Architecte', 'Bien-être', 'Commerce', 'Assurance', 'Conseil', 'Photographe', 'Fleuriste', 'Boulangerie'];
const sectorRow = [...sectors, ...sectors];

const modules = [
    { key: 'resa', icon: 'calendar', t: 'Réservation de table', d: 'Créneaux, capacité par service, confirmation instantanée, rappel SMS la veille. Vos clients réservent à 23h, vous dormez.', big: true },
    { key: 'rooms', icon: 'bed', t: 'Chambres & séjours', d: 'Tarifs, disponibilités, demande de séjour. Synchronisation iCal Airbnb, Booking, Abritel : fini les doubles réservations.' },
    { key: 'form', icon: 'form', t: 'Formulaires intelligents', d: 'Un parcours en 3 étapes adapté à votre métier : devis travaux, prise de RDV, estimation. Les demandes arrivent dans votre espace.' },
    { key: 'bot', icon: 'chat', t: 'Assistant IA 24h/24', d: 'Formé sur votre établissement : horaires, carte, services, FAQ. Il répond, rassure et récupère les coordonnées des intéressés.' },
    { key: 'pay', icon: 'card', t: 'Paiement en ligne', d: 'Acomptes sur les séjours, empreinte bancaire anti no-show sur les tables. L\'argent arrive directement sur votre compte.' },
    { key: 'wa', icon: 'whatsapp', t: 'WhatsApp', d: 'Une bulle flottante, un message pré-rempli : vos visiteurs vous écrivent en un clic.' },
    { key: 'reviews', icon: 'star', t: 'Avis Google en direct', d: 'Vos meilleurs avis, votre note, mis à jour automatiquement. La preuve sociale qui convertit.' },
    { key: 'menu', icon: 'menu', t: 'Carte & QR code', d: 'Catégories, plats, prix, disponibilités. Consultable en ligne et via QR code sur vos tables.' },
    { key: 'stats', icon: 'chart', t: 'Statistiques', d: 'Visites, demandes, réservations : la performance de votre site, jour après jour.' },
    { key: 'notif', icon: 'bell', t: 'Emails & SMS brandés', d: 'Confirmations, rappels, invitations agenda, aux couleurs de votre établissement.' },
    { key: 'legal', icon: 'shield', t: 'RGPD & mentions légales', d: 'Mentions légales et politique de confidentialité conformes, générées automatiquement.' },
    { key: 'domain', icon: 'globe', t: 'Votre nom de domaine', d: 'votre-nom.fr branché en quelques minutes, certificat SSL inclus.' },
];

const icons = {
    calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    bed: 'M3 7v11m0-4h18m0 4v-7a2 2 0 00-2-2h-6v5M7 10a2 2 0 100-4 2 2 0 000 4z',
    form: 'M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2zm2 0V2m6 2V2',
    chat: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
    card: 'M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z',
    whatsapp: 'M12 3a9 9 0 00-7.8 13.5L3 21l4.6-1.2A9 9 0 1012 3zm-3 6.5c.2-.5.5-.5.8-.5h.5c.2 0 .4 0 .5.4l.7 1.6c.1.2 0 .4-.1.5l-.5.6c-.1.1-.1.3 0 .4.6 1 1.4 1.8 2.5 2.4.2.1.3 0 .4-.1l.6-.7c.2-.2.3-.2.5-.1l1.6.7c.2.1.4.2.4.4 0 .6-.3 1.4-.9 1.7-.7.4-1.5.4-2.3.1-2.2-.8-3.8-2.4-4.7-4.6-.4-1-.4-2 0-2.8z',
    star: 'M11.5 3.5l2.4 5 5.5.8-4 3.9.9 5.5-4.8-2.6-4.8 2.6.9-5.5-4-3.9 5.5-.8z',
    menu: 'M4 6h16M4 12h10M4 18h13',
    chart: 'M4 19h16M6 16l4-5 3 3 5-7',
    bell: 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1',
    shield: 'M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3zm-3 9l2 2 4-4',
    globe: 'M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c2.5-2.5 3.5-5.5 3.5-9S14.5 5.5 12 3m0 18c-2.5-2.5-3.5-5.5-3.5-9S9.5 5.5 12 3M3 12h18',
    check: 'M5 13l4 4L19 7',
    bolt: 'M13 3L4 14h7l-1 7 9-11h-7l1-7z',
    sparkle: 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3zm7 11l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14zM5 15l.6 1.4L7 17l-1.4.6L5 19l-.6-1.4L3 17l1.4-.6L5 15z',
    search: 'M21 21l-4.3-4.3M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z',
    pin: 'M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11zm0-9a2 2 0 100-4 2 2 0 000 4z',
};

const compare = [
    { k: 'Délai de mise en ligne', j: '30 secondes', a: '3 à 8 semaines', w: 'Plusieurs jours de travail' },
    { k: 'Prix', j: '39 € HT/mois ou 349 € une fois', a: '1 500 à 5 000 €', w: '20 à 40 €/mois + votre temps' },
    { k: 'Textes rédigés pour votre métier', j: true, a: true, w: false },
    { k: 'Photos & avis Google intégrés', j: true, a: 'Parfois', w: false },
    { k: 'Réservation, paiement, assistant IA', j: 'Inclus', a: 'Sur devis', w: 'Extensions payantes' },
    { k: 'Référencement local', j: true, a: true, w: 'À configurer' },
    { k: 'Modifications', j: 'Studio + IA, 2 clics', a: 'Sur demande, facturées', w: 'Vous-même' },
    { k: 'Design premium adapté au secteur', j: true, a: true, w: false },
];

const testimonials = [
    { q: 'En 2 minutes j\'avais un site plus beau que celui que je payais 1 500 €. Mes clientes réservent en ligne, je ne rate plus un appel.', a: 'Sophie M.', r: 'Institut de beauté, Lyon', i: 'S' },
    { q: 'Je pensais que ce serait compliqué. J\'ai cherché mon restaurant, cliqué, c\'était fait. Les réservations tombent toutes seules.', a: 'Karim B.', r: 'Restaurant, Marseille', i: 'K' },
    { q: 'Les avis Google intégrés, le formulaire de devis, tout y était. J\'ai publié le soir même et reçu 3 demandes dans la semaine.', a: 'Laurent D.', r: 'Rénovation, Toulouse', i: 'L' },
];

const faqs = [
    { q: 'Combien ça coûte ?', a: 'La création et l\'aperçu sont 100 % gratuits. Vous ne payez que si vous publiez : formule Pro à 39 € HT/mois (7 jours d\'essai gratuit, sans engagement) ou formule Liberté à 349 € HT en paiement unique, site à vie.' },
    { q: 'Les modules (réservation, paiement, assistant IA…) sont-ils inclus ?', a: 'Oui. Tous les modules sont inclus dans les deux formules et s\'activent en un clic depuis votre espace. Les paiements en ligne passent par Stripe : l\'argent arrive directement sur votre compte.' },
    { q: 'Ai-je besoin de compétences techniques ?', a: 'Aucune. Vous cherchez votre établissement, l\'IA fait le reste. Ensuite, vous cliquez sur un texte ou une image pour le modifier, ou vous demandez à l\'assistant : « rends le ton plus chaleureux ».' },
    { q: 'D\'où viennent les textes et les photos ?', a: 'De votre fiche Google (avis, photos, horaires), enrichis par notre IA qui rédige des contenus adaptés à votre métier. Vous pouvez tout remplacer par vos propres photos et textes.' },
    { q: 'Puis-je utiliser mon propre nom de domaine ?', a: 'Oui. Votre site est livré sur une adresse joow.fr, et vous branchez votre propre domaine (.fr, .com…) à tout moment, certificat SSL inclus.' },
    { q: 'Et si je ne suis pas satisfait ?', a: 'L\'aperçu est gratuit et sans engagement : vous ne payez rien tant que vous n\'avez pas décidé de publier. La formule Pro est résiliable en un clic.' },
];
const openFaq = ref(0);

/* ───────────────────────── Effets ───────────────────────── */
const displaySites = ref(0);
const showBar = ref(false);
const glow = ref({ x: 50, y: 30 });
const onPointer = (e) => {
    const h = document.getElementById('hero-wrap'); if (!h) return;
    const r = h.getBoundingClientRect();
    if (e.clientY > r.bottom) return;
    glow.value = { x: (e.clientX / window.innerWidth) * 100, y: (e.clientY / r.height) * 100 };
};
const glowStyle = computed(() => `background: radial-gradient(700px circle at ${glow.value.x}% ${glow.value.y}%, rgba(129,140,248,0.16), transparent 45%)`);
const onScroll = () => { showBar.value = window.scrollY > 700; };

/* Tilt 3D sur la scène */
const tilt = ref('');
const onTilt = (e) => {
    const el = e.currentTarget; const r = el.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
    tilt.value = `transform: perspective(1400px) rotateY(${x * 8}deg) rotateX(${-y * 8}deg)`;
};
const offTilt = () => { tilt.value = ''; };

onMounted(() => {
    const target = props.stats.sites || 0;
    if (target > 0) {
        const dur = 1400, start = performance.now();
        const tick = (now) => { const p = Math.min((now - start) / dur, 1); displaySites.value = Math.round(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick); };
        requestAnimationFrame(tick);
    }
    const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('on'); io.unobserve(e.target); } }), { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
    window.addEventListener('pointermove', onPointer, { passive: true });
    window.addEventListener('scroll', onScroll, { passive: true });
    stageTimer = setInterval(() => { if (!stageHover.value) stage.value = (stage.value + 1) % showcase.length; }, 4200);
    runTypewriter();
    genTimer = setInterval(() => { genStep.value = (genStep.value + 1) % (genSteps.length + 2); }, 1300);
});
onUnmounted(() => {
    window.removeEventListener('pointermove', onPointer);
    window.removeEventListener('scroll', onScroll);
    clearInterval(stageTimer); clearTimeout(twTimer); clearInterval(genTimer);
});
</script>

<template>
    <Head title="Créez votre site pro en 30 secondes — Joow" />

    <div class="min-h-screen overflow-x-clip bg-ink-950 text-slate-200">
        <!-- Fond aurora -->
        <div class="pointer-events-none fixed inset-0 z-0">
            <div class="aurora aurora-1"></div>
            <div class="aurora aurora-2"></div>
            <div class="aurora aurora-3"></div>
            <div class="grain absolute inset-0"></div>
        </div>

        <div class="relative z-10">
            <!-- ═══ HEADER ═══ -->
            <header class="sticky top-0 z-40 border-b border-white/[0.04] bg-ink-950/60 backdrop-blur-xl">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5">
                    <a href="/" class="block" aria-label="Joow"><BrandLogo class="h-9 w-auto" /></a>
                    <nav class="hidden items-center gap-1 text-sm font-medium text-slate-400 lg:flex">
                        <button @click="goTo('exemples')" class="rounded-lg px-3 py-2 transition hover:text-white">Exemples</button>
                        <button @click="goTo('modules')" class="rounded-lg px-3 py-2 transition hover:text-white">Modules</button>
                        <button @click="goTo('studio')" class="rounded-lg px-3 py-2 transition hover:text-white">Studio</button>
                        <button @click="goTo('tarifs')" class="rounded-lg px-3 py-2 transition hover:text-white">Tarifs</button>
                        <button @click="goTo('faq')" class="rounded-lg px-3 py-2 transition hover:text-white">FAQ</button>
                    </nav>
                    <div class="flex items-center gap-2">
                        <Link :href="route('login')" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 transition hover:text-white">Se connecter</Link>
                        <button @click="scrollToSearch" class="btn-brand hidden text-sm sm:inline-flex">Créer mon site</button>
                    </div>
                </div>
            </header>

            <!-- ═══ HERO ═══ -->
            <div id="hero-wrap" class="relative">
                <div class="pointer-events-none absolute inset-0" :style="glowStyle"></div>
                <section id="start" class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-16 pt-14 lg:grid-cols-[1.05fr_1fr] lg:pt-20">
                    <div class="text-center lg:text-left">
                        <p class="reveal inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-400 backdrop-blur">
                            <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span></span>
                            IA + votre fiche Google · aperçu gratuit
                        </p>
                        <h1 class="reveal mt-5 font-display text-5xl font-bold leading-[1.02] text-white sm:text-6xl xl:text-7xl">
                            Le site pro<br>de votre métier,<br><span class="text-shimmer">en 30 secondes.</span>
                        </h1>
                        <p class="reveal mx-auto mt-6 max-w-xl text-lg text-slate-400 lg:mx-0">
                            Tapez le nom de votre établissement. Joow rédige, choisit vos photos, intègre vos avis Google et active réservation, paiement et assistant IA. Sans compte, sans attente.
                        </p>

                        <form @submit.prevent="submit" class="reveal mx-auto mt-8 max-w-xl lg:mx-0">
                            <label v-show="!selected" for="search-input" class="mb-2 flex items-center justify-center gap-2 text-sm font-semibold text-white lg:justify-start">
                                <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-gradient text-[11px] font-bold">1</span>
                                Tapez le nom de votre établissement
                                <span class="hidden font-normal text-slate-500 sm:inline">· tel qu'il apparaît sur Google</span>
                            </label>
                            <div class="search-shell" v-show="!selected">
                                <svg class="pointer-events-none absolute left-4 top-1/2 z-10 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.search"/></svg>
                                <!-- Machine à écrire : exemples tapés en blanc, stoppée au premier clic -->
                                <div v-if="showTypewriter" class="typewriter pointer-events-none absolute inset-y-0 left-12 right-32 flex items-center text-base text-white" aria-hidden="true">
                                    <span class="truncate">{{ typed }}</span><span class="tw-cursor"></span>
                                </div>
                                <input id="search-input" v-model="q" type="text" autocomplete="off" class="field !rounded-2xl !border-white/20 !bg-white/[0.06] !py-4 pl-12 pr-32 text-base text-white" :placeholder="twStopped || focused ? 'Nom de votre établissement…' : ''" @focus="focused = true; stopTypewriter(); open = results.length > 0" @blur="focused = false" @input="stopTypewriter" />
                                <button type="button" @click="q.trim().length >= 3 ? null : scrollToSearch()" class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold text-white sm:block">Rechercher</button>
                                <div v-if="searching" class="absolute right-28 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-white/20 border-t-brand-500"></div>
                                <div v-if="open" class="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-white/10 bg-ink-800 text-left shadow-2xl">
                                    <button v-for="r in results" :key="r.place_id" type="button" @click="pick(r)" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/5">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/5 text-slate-400"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path :d="icons.pin"/></svg></span>
                                        <span class="min-w-0"><span class="block truncate font-semibold text-white">{{ r.main }}</span><span class="block truncate text-xs text-slate-500">{{ r.secondary }}</span></span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="selected" class="glass flex items-center gap-3 rounded-2xl border-emerald-400/20 bg-emerald-500/[0.06] p-4 text-left">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-500/15 text-emerald-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.check"/></svg></span>
                                <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-white">{{ selected.main }}</span><span class="block truncate text-xs text-slate-400">{{ selected.secondary }}</span></span>
                                <button type="button" @click="reset" class="shrink-0 rounded-lg px-2 py-1 text-xs text-slate-400 transition hover:text-white">Changer</button>
                            </div>
                            <p v-if="form.errors.query" class="mt-2 text-sm text-rose-400">{{ form.errors.query }}</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input v-model="form.email" type="email" class="field sm:flex-1" placeholder="Votre email (optionnel, pour recevoir le lien)" />
                                <button type="submit" class="btn-brand shrink-0 !px-6" :class="{ 'opacity-50': !form.query || form.processing }" :disabled="!form.query || form.processing">
                                    {{ form.processing ? 'Création…' : selected ? 'Générer mon site →' : 'Créer mon site' }}
                                </button>
                            </div>
                            <p class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-slate-500 lg:justify-start">
                                <span>✓ Gratuit</span><span>✓ Sans carte bancaire</span><span>✓ Aperçu immédiat</span><span>✓ Modifiable en 2 clics</span>
                            </p>
                        </form>

                        <div class="reveal mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-slate-400 lg:justify-start">
                            <span v-if="stats.sites >= 100" class="flex items-center gap-2">
                                <span class="flex -space-x-2">
                                    <span class="grid h-7 w-7 place-items-center rounded-full border-2 border-ink-950 bg-brand-gradient text-[10px] font-bold text-white">S</span>
                                    <span class="grid h-7 w-7 place-items-center rounded-full border-2 border-ink-950 bg-emerald-500 text-[10px] font-bold text-white">K</span>
                                    <span class="grid h-7 w-7 place-items-center rounded-full border-2 border-ink-950 bg-amber-500 text-[10px] font-bold text-white">L</span>
                                    <span class="grid h-7 w-7 place-items-center rounded-full border-2 border-ink-950 bg-sky-500 text-[10px] font-bold text-white">M</span>
                                </span>
                                <strong class="text-white">{{ displaySites.toLocaleString('fr-FR') }}+</strong> sites créés
                            </span>
                            <span v-else class="flex items-center gap-1.5"><span class="text-emerald-400">✓</span> Aperçu gratuit, sans carte bancaire</span>
                            <span class="flex items-center gap-1.5"><span class="text-amber-400">★★★★★</span> <strong class="text-white">{{ stats.rating }}</strong>/5 <span class="text-slate-500">note moyenne des établissements</span></span>
                            <span class="flex items-center gap-1.5"><span class="text-brand-400">✦</span> {{ stats.sectors || 17 }} métiers couverts</span>
                        </div>
                    </div>

                    <!-- Scène 3D : site desktop + mobile qui tournent par secteur -->
                    <div class="reveal relative mx-auto w-full max-w-[640px] lg:max-w-none" @pointerenter="stageHover = true" @pointerleave="stageHover = false; offTilt()" @pointermove="onTilt">
                        <div class="stage" :style="tilt">
                            <div class="browser-frame stage-desktop">
                                <div class="browser-bar">
                                    <span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span>
                                    <span class="url">{{ current.domain }}.joow.fr</span>
                                    <span class="ml-auto hidden items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-300 sm:flex"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> En ligne</span>
                                </div>
                                <div class="relative aspect-[16/10] overflow-hidden bg-ink-900">
                                    <TransitionGroup name="xfade">
                                        <img v-for="s in showcase" v-show="s.key === current.key" :key="s.key" :src="s.img" :alt="s.name" class="absolute inset-0 h-full w-full object-cover object-top" :loading="s.key === showcase[0].key ? 'eager' : 'lazy'" />
                                    </TransitionGroup>
                                </div>
                            </div>
                            <div class="phone-frame stage-phone">
                                <div class="phone-notch"></div>
                                <TransitionGroup name="xfade">
                                    <img v-for="s in showcase" v-show="s.key === current.key" :key="s.key" :src="s.mobile" :alt="s.name" class="absolute inset-0 h-full w-full object-cover object-top" loading="lazy" />
                                </TransitionGroup>
                            </div>
                            <!-- Puces flottantes -->
                            <div class="float-chip chip-a"><svg class="h-4 w-4 text-amber-300" viewBox="0 0 24 24" fill="currentColor"><path :d="icons.star"/></svg><span><strong>4.8</strong> · 327 avis Google</span></div>
                            <div class="float-chip chip-b"><svg class="h-4 w-4 text-emerald-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path :d="icons.check"/></svg><span>Nouvelle réservation · 20h30 · 4 couv.</span></div>
                            <div class="float-chip chip-c"><svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="currentColor"><path :d="icons.bolt"/></svg><span>Généré en <strong>28 s</strong></span></div>
                        </div>
                        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                            <button v-for="(s, i) in showcase" :key="s.key" type="button" @click="stage = i" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition" :class="i === stage ? 'border-brand-500/60 bg-brand-500/15 text-white' : 'border-white/10 bg-white/[0.03] text-slate-400 hover:text-white'">{{ s.sector }}</button>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ═══ BANDE MÉTIERS ═══ -->
            <section class="border-y border-white/[0.05] bg-white/[0.015] py-4">
                <div class="marquee-mask">
                    <div class="flex w-max gap-3 marquee-left-slow">
                        <span v-for="(s, i) in sectorRow" :key="i" class="whitespace-nowrap rounded-full border border-white/[0.07] bg-white/[0.03] px-4 py-1.5 text-sm text-slate-300"><span class="mr-1.5 text-brand-400">✦</span>{{ s }}</span>
                    </div>
                </div>
            </section>

            <!-- ═══ COMMENT ÇA MARCHE (simulation) ═══ -->
            <section class="mx-auto max-w-7xl px-5 py-24">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Comment ça marche</p>
                        <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Pas de brief. Pas de graphiste.<br class="hidden sm:block"> Pas d'attente.</h2>
                        <p class="reveal mt-4 max-w-lg text-lg text-slate-400">Votre fiche Google contient déjà tout : vos photos, vos avis, vos horaires. Joow s'en sert pour construire un site complet, puis vous laisse la main.</p>
                        <div class="mt-8 space-y-4">
                            <div class="reveal flex gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-gradient font-display font-bold text-white">1</span><div><h3 class="font-display text-lg font-bold text-white">Trouvez votre établissement</h3><p class="text-sm text-slate-400">Tapez son nom, on le retrouve instantanément sur Google.</p></div></div>
                            <div class="reveal flex gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-gradient font-display font-bold text-white">2</span><div><h3 class="font-display text-lg font-bold text-white">L'IA construit tout</h3><p class="text-sm text-slate-400">Textes, photos, avis, design sectoriel, modules : assemblés en 30 secondes.</p></div></div>
                            <div class="reveal flex gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-gradient font-display font-bold text-white">3</span><div><h3 class="font-display text-lg font-bold text-white">Ajustez, publiez</h3><p class="text-sm text-slate-400">Un clic sur un texte pour le changer, un mot à l'IA pour le reste. Puis votre domaine.</p></div></div>
                        </div>
                        <button @click="scrollToSearch" class="btn-brand reveal mt-8">Essayer gratuitement →</button>
                    </div>
                    <!-- Terminal de génération -->
                    <div class="reveal glass relative overflow-hidden rounded-3xl p-6 sm:p-8">
                        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-brand-600/20 blur-3xl"></div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-sm font-semibold text-white"><svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="currentColor"><path :d="icons.sparkle"/></svg> Génération en cours</div>
                            <span class="font-mono text-xs text-slate-500">{{ Math.min(genStep, genSteps.length) * 5 }} s</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full rounded-full bg-brand-gradient transition-all duration-700" :style="{ width: Math.min(genStep / genSteps.length, 1) * 100 + '%' }"></div></div>
                        <ul class="mt-6 space-y-3">
                            <li v-for="(s, i) in genSteps" :key="i" class="flex items-start gap-3 transition-all duration-500" :class="i <= genStep ? 'opacity-100' : 'opacity-30'">
                                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-[11px]" :class="i < genStep ? 'bg-emerald-500/20 text-emerald-300' : i === genStep ? 'bg-brand-500/25 text-brand-300' : 'bg-white/5 text-slate-500'">
                                    <svg v-if="i < genStep" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.check"/></svg>
                                    <span v-else-if="i === genStep" class="h-2 w-2 animate-pulse rounded-full bg-brand-400"></span>
                                    <span v-else>{{ i + 1 }}</span>
                                </span>
                                <span><span class="block text-sm font-semibold text-white">{{ s.t }}</span><span class="block text-xs text-slate-500">{{ s.d }}</span></span>
                            </li>
                        </ul>
                        <div class="mt-6 flex items-center justify-between rounded-2xl border border-emerald-400/20 bg-emerald-500/[0.06] px-4 py-3 transition-all duration-500" :class="genStep >= genSteps.length ? 'opacity-100' : 'opacity-0'">
                            <span class="text-sm font-semibold text-emerald-300">✓ Site prêt · latabledemile.joow.fr</span>
                            <span class="text-xs text-slate-400">28 s</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ VITRINE ═══ -->
            <section id="exemples" class="relative scroll-mt-20 py-20">
                <div class="mx-auto mb-10 max-w-7xl px-5 text-center">
                    <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">La vitrine Joow</p>
                    <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Des sites qui donnent envie de cliquer</h2>
                    <p class="reveal mx-auto mt-4 max-w-xl text-lg text-slate-400">Chaque métier a sa propre identité : typographie, palette, animations, sections. Survolez pour faire défiler.</p>
                </div>

                <!-- Onglets + grand aperçu qui défile -->
                <div class="mx-auto max-w-6xl px-5">
                    <div class="reveal mb-6 flex flex-wrap justify-center gap-2">
                        <button v-for="(s, i) in showcase" :key="s.key" type="button" @click="tab = i" class="rounded-full border px-4 py-2 text-sm font-semibold transition" :class="i === tab ? 'border-brand-500/60 bg-brand-500/15 text-white shadow-glow' : 'border-white/10 bg-white/[0.03] text-slate-400 hover:text-white'">{{ s.sector }}</button>
                    </div>
                    <div class="reveal grid gap-6 lg:grid-cols-[1fr_300px]">
                        <button type="button" @click="lightbox = tabSite" class="browser-frame group text-left">
                            <div class="browser-bar">
                                <span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span>
                                <span class="url">{{ tabSite.domain }}.joow.fr</span>
                                <span class="ml-auto text-[10px] text-slate-500">Survolez pour défiler ↓</span>
                            </div>
                            <div class="scroller relative aspect-[16/10] overflow-hidden bg-ink-900">
                                <img :key="tabSite.key" :src="tabSite.full" :alt="tabSite.name" class="w-full" loading="lazy" />
                            </div>
                        </button>
                        <div class="glass flex flex-col rounded-3xl p-6">
                            <span class="chip w-max bg-brand-500/15 text-brand-300">{{ tabSite.sector }}</span>
                            <h3 class="mt-3 font-display text-2xl font-bold text-white">{{ tabSite.name }}</h3>
                            <p class="text-sm text-slate-400">{{ tabSite.city }}</p>
                            <p class="mt-4 text-xs font-bold uppercase tracking-wider text-slate-500">Modules activés</p>
                            <ul class="mt-2 space-y-2">
                                <li v-for="t in tabSite.tags" :key="t" class="flex items-center gap-2 text-sm text-slate-200"><span class="grid h-5 w-5 place-items-center rounded-full bg-emerald-500/15 text-emerald-300"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.check"/></svg></span>{{ t }}</li>
                            </ul>
                            <div class="mt-auto pt-6">
                                <div class="phone-frame mx-auto h-64 w-32"><div class="phone-notch"></div><img :src="tabSite.mobile" :alt="tabSite.name" class="absolute inset-0 h-full w-full object-cover object-top" loading="lazy" /></div>
                                <p class="mt-3 text-center text-xs text-slate-500">Parfait sur mobile, là où 8 clients sur 10 vous cherchent.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Double carrousel -->
                <div class="marquee-mask relative mt-14">
                    <div class="flex w-max gap-6 marquee-left">
                        <button v-for="(s, i) in rowA" :key="'a'+i" type="button" @click="lightbox = s" class="group w-[320px] shrink-0 sm:w-[420px]">
                            <div class="browser-frame">
                                <div class="browser-bar"><span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span><span class="url">{{ s.domain }}.joow.fr</span></div>
                                <div class="relative aspect-[16/10] overflow-hidden">
                                    <img :src="s.img" :alt="s.name" loading="lazy" class="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.04]" />
                                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-black/70 to-transparent p-3"><span class="text-sm font-semibold text-white">{{ s.name }} · {{ s.city }}</span><span class="chip bg-white/15 text-white backdrop-blur">{{ s.sector }}</span></div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
                <div class="marquee-mask relative mt-6">
                    <div class="flex w-max gap-6 marquee-right">
                        <button v-for="(s, i) in rowB" :key="'b'+i" type="button" @click="lightbox = s" class="group w-[320px] shrink-0 sm:w-[420px]">
                            <div class="browser-frame">
                                <div class="browser-bar"><span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span><span class="url">{{ s.domain }}.joow.fr</span></div>
                                <div class="relative aspect-[16/10] overflow-hidden">
                                    <img :src="s.img" :alt="s.name" loading="lazy" class="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.04]" />
                                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-black/70 to-transparent p-3"><span class="text-sm font-semibold text-white">{{ s.name }} · {{ s.city }}</span><span class="chip bg-white/15 text-white backdrop-blur">{{ s.sector }}</span></div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
                <div class="mt-12 text-center"><button @click="scrollToSearch" class="btn-brand">Voir le mien en 30 secondes →</button></div>
            </section>

            <!-- ═══ MODULES ═══ -->
            <section id="modules" class="mx-auto max-w-7xl scroll-mt-20 px-5 py-24">
                <div class="mb-12 text-center">
                    <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Modules inclus</p>
                    <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Un site qui travaille pour vous,<br class="hidden sm:block"> même quand vous dormez</h2>
                    <p class="reveal mx-auto mt-4 max-w-2xl text-lg text-slate-400">Réservations, paiements, assistant IA, WhatsApp, avis… Tous les modules sont inclus et s'activent en un clic depuis votre espace. Les demandes arrivent dans votre boîte de réception Joow, par email et par SMS.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Réservation de table (grande carte avec maquette) -->
                    <div class="reveal glass glass-hover relative overflow-hidden rounded-3xl p-6 md:col-span-2 lg:row-span-2">
                        <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-brand-600/20 blur-3xl"></div>
                        <div class="relative flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-gradient text-white"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.calendar"/></svg></span>
                            <div><h3 class="font-display text-xl font-bold text-white">Réservation de table</h3><p class="mt-1 text-sm text-slate-400">Créneaux, capacité par service, confirmation instantanée, rappel SMS la veille. Vos clients réservent à 23h, vous dormez.</p></div>
                        </div>
                        <!-- Maquette widget -->
                        <div class="mock relative mt-6 rounded-2xl border border-white/10 bg-ink-900/80 p-4">
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div><p class="mb-1 text-slate-500">Date</p><div class="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2 text-white">Ven. 26 sept.</div></div>
                                <div><p class="mb-1 text-slate-500">Couverts</p><div class="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2 text-white">4 personnes</div></div>
                            </div>
                            <p class="mb-1.5 mt-3 text-xs text-slate-500">Créneau</p>
                            <div class="grid grid-cols-4 gap-2 text-center text-xs font-semibold">
                                <span class="rounded-lg border border-white/10 py-2 text-slate-300">19:00</span>
                                <span class="rounded-lg border border-white/10 py-2 text-slate-300">19:30</span>
                                <span class="rounded-lg bg-brand-gradient py-2 text-white shadow-glow">20:30</span>
                                <span class="rounded-lg border border-white/10 py-2 text-slate-600 line-through">21:00</span>
                            </div>
                            <div class="mt-3 rounded-xl bg-brand-gradient py-2.5 text-center text-sm font-bold text-white">Réserver ma table</div>
                            <div class="mock-toast"><span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-500/20 text-emerald-300"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.check"/></svg></span><span><strong class="block text-white">Table confirmée</strong><span class="text-slate-400">SMS envoyé · rappel demain 10h</span></span></div>
                        </div>
                        <ul class="relative mt-5 grid grid-cols-2 gap-2 text-xs text-slate-300">
                            <li>✓ Empreinte bancaire anti no-show</li><li>✓ Compatible ZenChef</li><li>✓ Boîte de réception + statuts</li><li>✓ Ajout à l'agenda en 1 clic</li>
                        </ul>
                    </div>

                    <!-- Assistant IA (maquette chat) -->
                    <div class="reveal glass glass-hover relative overflow-hidden rounded-3xl p-6 lg:col-span-2">
                        <div class="flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-gradient text-white"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.chat"/></svg></span>
                            <div><h3 class="font-display text-xl font-bold text-white">Assistant IA 24h/24</h3><p class="mt-1 text-sm text-slate-400">Formé sur votre établissement. Il répond, rassure et récupère les coordonnées des intéressés.</p></div>
                        </div>
                        <div class="mt-5 space-y-2 text-sm">
                            <div class="ml-auto w-max max-w-[85%] rounded-2xl rounded-br-md bg-brand-gradient px-3.5 py-2 text-white">Vous avez une terrasse pour 6 ce samedi ?</div>
                            <div class="w-max max-w-[85%] rounded-2xl rounded-bl-md border border-white/10 bg-white/[0.05] px-3.5 py-2 text-slate-200">Oui ! La terrasse est ouverte jusqu'à 23h. Il reste de la place samedi à 20h et 20h30. Je vous réserve ?</div>
                            <div class="typing w-max rounded-2xl rounded-bl-md border border-white/10 bg-white/[0.05] px-3.5 py-2.5"><span></span><span></span><span></span></div>
                        </div>
                    </div>

                    <!-- Paiement -->
                    <div class="reveal glass glass-hover rounded-3xl p-6">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/[0.06] text-brand-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.card"/></svg></span>
                        <h3 class="mt-4 font-display text-lg font-bold text-white">Paiement en ligne</h3>
                        <p class="mt-1.5 text-sm text-slate-400">Acomptes sur les séjours, empreinte anti no-show sur les tables. L'argent arrive sur votre compte.</p>
                        <div class="mt-4 flex items-center justify-between rounded-xl border border-white/10 bg-ink-900/80 px-3 py-2 text-xs"><span class="text-slate-400">Acompte 30 %</span><span class="font-bold text-white">87,00 €</span><span class="rounded-md bg-emerald-500/15 px-1.5 py-0.5 font-semibold text-emerald-300">Payé</span></div>
                    </div>

                    <!-- WhatsApp -->
                    <div class="reveal glass glass-hover rounded-3xl p-6">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#25D366]/15 text-[#25D366]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path :d="icons.whatsapp"/></svg></span>
                        <h3 class="mt-4 font-display text-lg font-bold text-white">WhatsApp</h3>
                        <p class="mt-1.5 text-sm text-slate-400">Une bulle flottante, un message pré-rempli : vos visiteurs vous écrivent en un clic.</p>
                        <div class="mt-4 w-max max-w-full rounded-2xl rounded-bl-md bg-[#25D366]/15 px-3 py-2 text-xs text-slate-200">Bonjour, je souhaite un devis pour… ✍️</div>
                    </div>

                    <!-- Chambres -->
                    <div class="reveal glass glass-hover rounded-3xl p-6 lg:col-span-2">
                        <div class="flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/[0.06] text-brand-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.bed"/></svg></span>
                            <div><h3 class="font-display text-lg font-bold text-white">Chambres & séjours</h3><p class="mt-1 text-sm text-slate-400">Tarifs, disponibilités, demande de séjour. Synchronisation iCal Airbnb, Booking, Abritel : fini les doubles réservations.</p></div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-xl border border-white/10 bg-ink-900/80 p-3"><p class="font-semibold text-white">Suite Belrose</p><p class="text-slate-500">2 pers.</p><p class="mt-1 font-bold text-brand-300">290 €<span class="font-normal text-slate-500">/nuit</span></p></div>
                            <div class="rounded-xl border border-white/10 bg-ink-900/80 p-3"><p class="font-semibold text-white">Chambre Jardin</p><p class="text-slate-500">2 pers.</p><p class="mt-1 font-bold text-brand-300">180 €<span class="font-normal text-slate-500">/nuit</span></p></div>
                            <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/[0.06] p-3"><p class="font-semibold text-emerald-300">Sync iCal</p><p class="text-slate-400">Airbnb · Booking</p><p class="mt-1 text-emerald-300">✓ à jour · 08:00</p></div>
                        </div>
                    </div>

                    <!-- Formulaires -->
                    <div class="reveal glass glass-hover rounded-3xl p-6 lg:col-span-2">
                        <div class="flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/[0.06] text-brand-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.form"/></svg></span>
                            <div><h3 class="font-display text-lg font-bold text-white">Formulaires intelligents</h3><p class="mt-1 text-sm text-slate-400">Un parcours en 3 étapes adapté à votre métier : devis travaux, prise de RDV, estimation immobilière. Modifiable dans le Studio.</p></div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 text-xs">
                            <span class="flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 font-semibold text-emerald-300">✓ Projet</span><span class="h-px flex-1 bg-white/10"></span>
                            <span class="flex items-center gap-1.5 rounded-full bg-brand-500/20 px-3 py-1 font-semibold text-white">2 · Détails</span><span class="h-px flex-1 bg-white/10"></span>
                            <span class="rounded-full bg-white/5 px-3 py-1 text-slate-500">3 · Contact</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                            <span class="rounded-xl border border-brand-500/50 bg-brand-500/10 py-2.5 font-semibold text-white">Rénovation complète</span><span class="rounded-xl border border-white/10 py-2.5 text-slate-300">Isolation</span><span class="rounded-xl border border-white/10 py-2.5 text-slate-300">Cuisine / SDB</span>
                        </div>
                    </div>

                    <!-- Petits modules -->
                    <div v-for="m in modules.filter(m => ['reviews','menu','stats','notif','legal','domain'].includes(m.key))" :key="m.key" class="reveal glass glass-hover rounded-3xl p-6">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/[0.06] text-brand-300"><svg class="h-5 w-5" viewBox="0 0 24 24" :fill="m.icon === 'star' ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="icons[m.icon]"/></svg></span>
                        <h3 class="mt-4 font-display text-lg font-bold text-white">{{ m.t }}</h3>
                        <p class="mt-1.5 text-sm text-slate-400">{{ m.d }}</p>
                        <svg v-if="m.key === 'stats'" class="mt-4 h-12 w-full text-brand-400" viewBox="0 0 200 48" fill="none" preserveAspectRatio="none"><path d="M0 40 L20 34 L40 36 L60 26 L80 28 L100 18 L120 22 L140 12 L160 14 L180 6 L200 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M0 40 L20 34 L40 36 L60 26 L80 28 L100 18 L120 22 L140 12 L160 14 L180 6 L200 8 V48 H0Z" fill="url(#spark)" opacity=".35"/><defs><linearGradient id="spark" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#818cf8"/><stop offset="1" stop-color="#818cf8" stop-opacity="0"/></linearGradient></defs></svg>
                        <div v-else-if="m.key === 'reviews'" class="mt-4 flex items-center gap-3"><span class="font-display text-3xl font-bold text-white">4.8</span><span><span class="block text-amber-400">★★★★★</span><span class="text-xs text-slate-500">327 avis · mis à jour ce matin</span></span></div>
                    </div>
                </div>

                <p class="reveal mt-8 text-center text-sm text-slate-500">Toutes les demandes, réservations et messages arrivent dans <strong class="text-slate-300">votre espace Joow</strong>, par email et par SMS. Rien ne se perd.</p>
            </section>

            <!-- ═══ STUDIO ═══ -->
            <section id="studio" class="mx-auto max-w-7xl scroll-mt-20 px-5 py-24">
                <div class="grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr]">
                    <div>
                        <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Le Studio</p>
                        <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Modifiez tout en 2 clics.<br>Ou demandez à l'IA.</h2>
                        <p class="reveal mt-4 max-w-lg text-lg text-slate-400">Cliquez un texte, il devient modifiable. Cliquez une photo, remplacez-la. Réordonnez les sections, changez les couleurs, construisez votre formulaire. Et pour le reste, un mot à l'assistant.</p>
                        <div class="reveal mt-6 flex flex-wrap gap-2">
                            <span class="ai-chip">« Rends le ton plus chaleureux »</span>
                            <span class="ai-chip">« Ajoute une FAQ sur les allergènes »</span>
                            <span class="ai-chip">« Passe en bleu nuit »</span>
                            <span class="ai-chip">« Mets la galerie avant les avis »</span>
                        </div>
                        <ul class="reveal mt-8 grid gap-3 text-sm text-slate-300 sm:grid-cols-2">
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Édition en place, aperçu instantané</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Constructeur de formulaire</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Sections, ordre, couleurs, police</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Publication en un clic</li>
                        </ul>
                    </div>
                    <div class="reveal">
                        <div class="browser-frame tilt-hover">
                            <div class="browser-bar"><span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span><span class="url">app.joow.fr/studio</span></div>
                            <img src="/showcase/studio.webp" alt="Le Studio Joow" class="w-full" loading="lazy" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ COMPARATIF ═══ -->
            <section class="mx-auto max-w-5xl px-5 py-20">
                <div class="mb-10 text-center">
                    <p class="reveal text-xs font-bold uppercase tracking-[0.3em] text-brand-400">Pourquoi Joow</p>
                    <h2 class="reveal mt-3 font-display text-3xl font-bold text-white sm:text-5xl">Le résultat d'une agence.<br class="hidden sm:block"> Sans le prix, sans l'attente.</h2>
                </div>
                <div class="reveal glass overflow-hidden rounded-3xl">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead><tr class="border-b border-white/[0.06] text-left text-xs uppercase tracking-wider text-slate-500">
                                <th class="px-5 py-4 font-semibold"></th>
                                <th class="bg-brand-500/10 px-5 py-4 font-bold text-white"><span class="flex items-center gap-2"><BrandLogo variant="mark" class="h-5 w-5" /> Joow</span></th>
                                <th class="px-5 py-4 font-semibold">Agence web</th>
                                <th class="px-5 py-4 font-semibold">Constructeur de site</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="r in compare" :key="r.k" class="border-b border-white/[0.05] last:border-0">
                                    <td class="px-5 py-3.5 text-slate-300">{{ r.k }}</td>
                                    <td v-for="(v, col) in [r.j, r.a, r.w]" :key="col" class="px-5 py-3.5" :class="col === 0 ? 'bg-brand-500/10 font-semibold text-white' : 'text-slate-400'">
                                        <span v-if="v === true" class="grid h-6 w-6 place-items-center rounded-full bg-emerald-500/15 text-emerald-300"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.check"/></svg></span>
                                        <span v-else-if="v === false" class="grid h-6 w-6 place-items-center rounded-full bg-rose-500/10 text-rose-300">✕</span>
                                        <span v-else>{{ v }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ═══ TÉMOIGNAGES ═══ -->
            <section class="mx-auto max-w-6xl px-5 py-20">
                <div class="reveal mb-10 text-center">
                    <h2 class="font-display text-3xl font-bold text-white sm:text-5xl">Ils ont créé leur site en quelques secondes</h2>
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <figure v-for="t in testimonials" :key="t.a" class="reveal glass glass-hover rounded-3xl p-6">
                        <div class="text-amber-400">★★★★★</div>
                        <blockquote class="mt-3 text-slate-200">« {{ t.q }} »</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3 text-sm"><span class="grid h-10 w-10 place-items-center rounded-full bg-brand-gradient font-display font-bold text-white">{{ t.i }}</span><span><span class="block font-semibold text-white">{{ t.a }}</span><span class="block text-slate-500">{{ t.r }}</span></span></figcaption>
                    </figure>
                </div>
            </section>

            <!-- ═══ TARIFS ═══ -->
            <section id="tarifs" class="mx-auto max-w-5xl scroll-mt-20 px-5 py-20">
                <div class="mb-10 text-center">
                    <p class="reveal inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-1.5 text-xs font-semibold text-emerald-300">Aperçu 100 % gratuit · vous ne payez qu'au moment de publier</p>
                    <h2 class="reveal mt-4 font-display text-3xl font-bold text-white sm:text-5xl">Deux formules, tous les modules inclus</h2>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="reveal relative overflow-hidden rounded-3xl border border-brand-500/50 bg-white/[0.03] p-8 shadow-glow">
                        <div class="pointer-events-none absolute -top-16 right-0 h-48 w-48 rounded-full bg-brand-600/25 blur-3xl"></div>
                        <span class="chip bg-brand-gradient text-white">Recommandé</span>
                        <p class="mt-4 font-display text-xl font-bold text-white">Pro</p>
                        <p class="mt-2 font-display text-5xl font-bold text-white">39 €<span class="text-base font-medium text-slate-400"> HT / mois</span></p>
                        <p class="mt-2 text-sm font-semibold text-emerald-300">7 jours d'essai gratuit · sans engagement</p>
                        <ul class="mt-6 space-y-2.5 text-sm text-slate-300">
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Nom de domaine .fr / .com + hébergement + SSL</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Tous les modules : réservation, paiement, assistant IA, WhatsApp…</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Studio + éditeur IA illimités</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Emails & SMS de confirmation</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Statistiques, SEO local, mises à jour</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Résiliable en un clic</li>
                        </ul>
                        <button @click="scrollToSearch" class="btn-brand mt-8 w-full">Commencer gratuitement →</button>
                    </div>
                    <div class="reveal rounded-3xl border border-white/10 bg-white/[0.03] p-8">
                        <span class="chip bg-white/10 text-slate-200">Paiement unique</span>
                        <p class="mt-4 font-display text-xl font-bold text-white">Liberté</p>
                        <p class="mt-2 font-display text-5xl font-bold text-white">349 €<span class="text-base font-medium text-slate-400"> HT, une fois</span></p>
                        <p class="mt-2 text-sm font-semibold text-brand-300">Votre site, à vie</p>
                        <ul class="mt-6 space-y-2.5 text-sm text-slate-300">
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Domaine + hébergement offerts la première année</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Tous les modules inclus</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Studio + éditeur IA illimités</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Téléchargement du site (vous en êtes propriétaire)</li>
                            <li class="flex gap-2"><span class="text-emerald-400">✓</span> Hébergement ensuite : 9 € / mois</li>
                        </ul>
                        <button @click="scrollToSearch" class="mt-8 w-full rounded-xl border border-white/15 px-5 py-3 font-semibold text-white transition hover:bg-white/5">Créer mon site →</button>
                    </div>
                </div>
                <p class="reveal mt-6 text-center text-xs text-slate-500">Prix HT. Paiement sécurisé par Stripe. Aucun frais caché, aucune commission sur vos réservations.</p>
            </section>

            <!-- ═══ FAQ ═══ -->
            <section id="faq" class="mx-auto max-w-3xl scroll-mt-20 px-5 py-20">
                <div class="reveal mb-10 text-center"><h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Questions fréquentes</h2></div>
                <div class="space-y-3">
                    <div v-for="(f, i) in faqs" :key="i" class="reveal glass overflow-hidden rounded-2xl">
                        <button type="button" @click="openFaq = openFaq === i ? null : i" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                            <span class="font-semibold text-white">{{ f.q }}</span>
                            <span class="shrink-0 text-xl leading-none text-brand-400 transition" :class="{ 'rotate-45': openFaq === i }">+</span>
                        </button>
                        <div v-show="openFaq === i" class="px-5 pb-5 text-sm leading-relaxed text-slate-400">{{ f.a }}</div>
                    </div>
                </div>
            </section>

            <!-- ═══ CTA FINAL ═══ -->
            <section class="mx-auto max-w-5xl px-5 pb-24 pt-10">
                <div class="reveal cta-final relative overflow-hidden rounded-[2rem] p-10 text-center sm:p-16">
                    <div class="relative">
                        <h2 class="font-display text-3xl font-bold text-white sm:text-5xl">Votre site vous attend.</h2>
                        <p class="mx-auto mt-4 max-w-lg text-lg text-slate-300">Cherchez votre établissement et voyez le résultat en 30 secondes. C'est gratuit, sans carte bancaire.</p>
                        <button @click="scrollToSearch" class="btn-brand mt-8 !px-8 !py-4 text-base">Créer mon site maintenant →</button>
                        <p class="mt-4 text-xs text-slate-400"><span v-if="stats.sites >= 100">{{ displaySites.toLocaleString('fr-FR') }}+ sites créés · </span>Aperçu gratuit · résiliable en un clic</p>
                    </div>
                </div>
            </section>

            <footer class="border-t border-white/[0.06] py-12">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-5 text-center text-xs text-slate-500 sm:flex-row sm:text-left">
                    <div class="flex flex-col items-center gap-3 sm:items-start"><BrandLogo class="h-7 w-auto" /><span>© 2026 Joow — Vos sites, en mieux.</span></div>
                    <div class="flex flex-wrap justify-center gap-4">
                        <button @click="goTo('exemples')" class="transition hover:text-white">Exemples</button>
                        <button @click="goTo('modules')" class="transition hover:text-white">Modules</button>
                        <button @click="goTo('tarifs')" class="transition hover:text-white">Tarifs</button>
                        <button @click="goTo('faq')" class="transition hover:text-white">FAQ</button>
                        <Link :href="route('login')" class="transition hover:text-white">Connexion</Link>
                    </div>
                </div>
            </footer>
        </div>

        <!-- Barre CTA mobile -->
        <Transition name="slide-up">
            <div v-if="showBar" class="fixed inset-x-4 bottom-4 z-40 sm:hidden">
                <button @click="scrollToSearch" class="btn-brand w-full !py-4 shadow-2xl">Créer mon site gratuitement →</button>
            </div>
        </Transition>

        <!-- Lightbox -->
        <Transition name="fade">
            <div v-if="lightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur" @click="lightbox = null">
                <div class="w-full max-w-5xl" @click.stop>
                    <div class="browser-frame shadow-2xl">
                        <div class="browser-bar"><span class="dot bg-rose-400/70"></span><span class="dot bg-amber-400/70"></span><span class="dot bg-emerald-400/70"></span><span class="url">{{ lightbox.domain }}.joow.fr</span></div>
                        <div class="max-h-[75vh] overflow-y-auto"><img :src="lightbox.full" :alt="lightbox.name" class="w-full" /></div>
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

/* Titre */
.text-shimmer { background: linear-gradient(100deg,#818cf8 20%,#e879f9 40%,#38bdf8 60%,#818cf8 80%); background-size: 200% auto; -webkit-background-clip: text; background-clip: text; color: transparent; animation: shimmer 5s linear infinite; }
@keyframes shimmer { to { background-position: 200% center; } }

/* Recherche */
.search-shell { position: relative; }
.typewriter { z-index: 5; font-weight: 500; letter-spacing: .01em; }
.tw-cursor { display: inline-block; width: 2px; height: 1.15em; margin-left: 2px; background: #a5b4fc; animation: twblink 1s steps(1) infinite; }
@keyframes twblink { 50% { opacity: 0; } }
.search-shell::before { content: ""; position: absolute; inset: -3px; border-radius: 1.2rem; background: linear-gradient(100deg, #6366f1, #a855f7, #38bdf8, #6366f1); background-size: 300% 100%; opacity: .5; filter: blur(10px); animation: glowshift 6s linear infinite; z-index: -1; }
@keyframes glowshift { to { background-position: 300% 0; } }
@keyframes spin { to { transform: rotate(360deg); } }

/* Reveal */
.reveal { opacity: 0; transform: translateY(26px); transition: opacity .8s cubic-bezier(.22,1,.36,1), transform .8s cubic-bezier(.22,1,.36,1); }
.reveal.on { opacity: 1; transform: none; }

/* Scène hero */
.stage { position: relative; transition: transform .25s ease-out; transform-style: preserve-3d; }
.stage-desktop { position: relative; z-index: 1; transform: translateZ(0); }
.stage-phone { position: absolute; right: -6px; bottom: -28px; z-index: 2; width: 27%; aspect-ratio: 9/19.5; transform: translateZ(60px); }
@media (min-width: 640px) { .stage-phone { right: -18px; bottom: -34px; } }
.float-chip { position: absolute; z-index: 3; display: flex; align-items: center; gap: .5rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,.12); background: rgba(13,13,20,.85); backdrop-filter: blur(12px); padding: .5rem .85rem; font-size: .75rem; color: #e2e8f0; box-shadow: 0 20px 40px -20px rgba(0,0,0,.8); transform: translateZ(80px); animation: floaty 6s ease-in-out infinite; }
.float-chip strong { color: #fff; }
.chip-a { top: 8%; left: -6%; animation-delay: -1s; }
.chip-b { bottom: 14%; left: -4%; animation-delay: -3s; }
.chip-c { top: -4%; right: 12%; animation-delay: -5s; }
@media (max-width: 639px) { .chip-a, .chip-b { left: 2%; } .chip-c { right: 2%; } .float-chip { font-size: .68rem; padding: .4rem .65rem; } }
@keyframes floaty { 0%,100% { translate: 0 0; } 50% { translate: 0 -8px; } }
.xfade-enter-active, .xfade-leave-active { transition: opacity .8s ease; }
.xfade-enter-from, .xfade-leave-to { opacity: 0; }

/* Cadres */
.browser-frame { overflow: hidden; border-radius: 1rem; border: 1px solid rgba(255,255,255,.08); background: #14141f; box-shadow: 0 30px 60px -25px rgba(0,0,0,.7); }
.browser-bar { display: flex; align-items: center; gap: .35rem; border-bottom: 1px solid rgba(255,255,255,.06); padding: .55rem .8rem; }
.dot { height: .55rem; width: .55rem; border-radius: 9999px; display: inline-block; }
.url { margin-left: .6rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-radius: .4rem; background: rgba(255,255,255,.05); padding: .15rem .6rem; font-size: .7rem; color: #94a3b8; }
.phone-frame { position: relative; overflow: hidden; border-radius: 1.6rem; border: 6px solid #1c1c2b; background: #0d0d14; box-shadow: 0 30px 60px -20px rgba(0,0,0,.8), 0 0 0 1px rgba(255,255,255,.08); }
.phone-notch { position: absolute; left: 50%; top: 6px; z-index: 2; height: 14px; width: 40%; transform: translateX(-50%); border-radius: 9999px; background: #1c1c2b; }
.tilt-hover { transition: transform .5s cubic-bezier(.22,1,.36,1); }
.tilt-hover:hover { transform: perspective(1200px) rotateY(-4deg) rotateX(2deg) scale(1.01); }

/* Aperçu qui défile au survol */
.scroller img { transition: transform 10s linear; will-change: transform; }
.scroller:hover img { transform: translateY(calc(-100% + 17.3%)); }

/* Marquee */
.marquee-mask { -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent); mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent); overflow: hidden; }
.marquee-left { animation: ml 46s linear infinite; }
.marquee-left-slow { animation: ml 60s linear infinite; }
.marquee-right { animation: mr 46s linear infinite; }
.marquee-left:hover, .marquee-right:hover { animation-play-state: paused; }
@keyframes ml { to { transform: translateX(-50%); } }
@keyframes mr { from { transform: translateX(-50%); } to { transform: translateX(0); } }

/* Maquettes modules */
.mock-toast { position: absolute; right: -10px; top: -14px; display: flex; align-items: center; gap: .6rem; border-radius: 1rem; border: 1px solid rgba(255,255,255,.12); background: rgba(13,13,20,.95); padding: .55rem .8rem; font-size: .72rem; box-shadow: 0 20px 40px -20px rgba(0,0,0,.8); animation: toast 5s ease-in-out infinite; }
@keyframes toast { 0%, 20% { opacity: 0; transform: translateY(8px); } 30%, 85% { opacity: 1; transform: translateY(0); } 95%, 100% { opacity: 0; transform: translateY(8px); } }
.typing { display: flex; gap: 4px; align-items: center; }
.typing span { height: 6px; width: 6px; border-radius: 9999px; background: #94a3b8; animation: blink 1.2s infinite; }
.typing span:nth-child(2) { animation-delay: .2s; } .typing span:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
.ai-chip { border-radius: 9999px; border: 1px solid rgba(129,140,248,.35); background: rgba(99,102,241,.1); padding: .4rem .85rem; font-size: .8rem; color: #c7d2fe; }

/* CTA final */
.cta-final { background: radial-gradient(80% 120% at 50% 0%, rgba(99,102,241,.35), rgba(13,13,20,.9) 60%), #0d0d14; border: 1px solid rgba(255,255,255,.08); }
.cta-final::before { content: ""; position: absolute; inset: -40%; background: conic-gradient(from 180deg at 50% 50%, transparent 0 60%, rgba(168,85,247,.35) 75%, transparent 90%); animation: spin 14s linear infinite; }

.fade-enter-active, .fade-leave-active { transition: opacity .25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-up-enter-active, .slide-up-leave-active { transition: transform .3s, opacity .3s; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(20px); opacity: 0; }

@media (prefers-reduced-motion: reduce) {
  .aurora, .text-shimmer, .marquee-left, .marquee-right, .marquee-left-slow, .float-chip, .search-shell::before, .cta-final::before, .mock-toast { animation: none; }
  .reveal { opacity: 1; transform: none; transition: none; }
}
</style>
