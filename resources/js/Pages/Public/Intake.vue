<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({ sectors: { type: Array, default: () => [] }, prefill: { type: Object, default: () => ({}) }, notice: { type: String, default: '' }, maxPhotos: { type: Number, default: 8 } });

const STEPS = [
    { key: 'who', title: 'Votre entreprise', q: 'Comment s\'appelle votre entreprise, et où est-elle ?' },
    { key: 'sector', title: 'Votre métier', q: 'Quel est votre métier ?' },
    { key: 'contact', title: 'Vos coordonnées', q: 'Comment vos clients vous joignent-ils ?' },
    { key: 'offer', title: 'Vos prestations', q: 'Que proposez-vous ? Cochez, ajoutez, ou passez.' },
    { key: 'photos', title: 'Vos photos', q: 'Ajoutez quelques photos, ou laissez-nous choisir des visuels de votre métier.' },
];
const step = ref(0);
const cur = computed(() => STEPS[step.value]);
const error = ref('');

const form = useForm({
    name: props.prefill?.query || '', city: '', address: '', zone: '',
    sector: '', sector_hint: '',
    phone: '', email: props.prefill?.email || '',
    services: [], strengths: [], description: '', since: '', hours: '',
    photos: [],
});

/* ── Étape 1 : recherche Google discrète en arrière-plan (« C'est vous ? ») ── */
const matches = ref([]);
const matching = ref(false);
const dismissed = ref(false);
let matchTimer = null;
const lookForProfile = () => {
    clearTimeout(matchTimer);
    const n = form.name.trim(), c = form.city.trim();
    if (n.length < 3 || c.length < 2 || dismissed.value) { matches.value = []; return; }
    matching.value = true;
    matchTimer = setTimeout(async () => {
        try {
            const r = await fetch(route('public.search', { q: `${n} ${c}` }), { headers: { Accept: 'application/json' } });
            const d = await r.json();
            // Seules les correspondances dont le nom ressemble à celui saisi sont proposées
            const norm = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
            const words = norm(n).split(' ').filter((w) => w.length > 2);
            matches.value = (d.results || []).filter((x) => { const m = norm(x.main); return words.length && words.filter((w) => m.includes(w)).length >= Math.ceil(words.length / 2); }).slice(0, 2);
        } catch (e) { matches.value = []; }
        finally { matching.value = false; }
    }, 650);
};
watch(() => [form.name, form.city], lookForProfile);
const useProfile = (r) => {
    const f = useForm({ query: r.place_id, email: form.email || '' });
    f.post(route('public.generate'));
};

/* ── Étape 2 : métier ── */
const sectorQuery = ref('');
const norm = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
const sectorMatches = computed(() => {
    const q = norm(sectorQuery.value.trim());
    const list = props.sectors.filter((s) => s.key !== 'service');
    if (!q) return list.slice(0, 12);
    return list.map((s) => ({ s, score: (norm(s.label).includes(q) ? 3 : 0) + (s.keywords || []).reduce((a, k) => a + (norm(k).includes(q) ? 1 : 0), 0) }))
        .filter((x) => x.score > 0).sort((a, b) => b.score - a.score).slice(0, 10).map((x) => x.s);
});
const otherSector = ref(false);
const pickSector = (s) => { form.sector = s.key; form.sector_hint = s.label; otherSector.value = false; error.value = ''; };

/* ── Étape 4 : suggestions IA ── */
const sugg = ref({ services: [], strengths: [], loading: false });
const loadSuggestions = async () => {
    sugg.value.loading = true;
    try {
        const r = await fetch(route('intake.suggestions', { sector: form.sector || 'service', hint: form.sector_hint || '' }), { headers: { Accept: 'application/json' } });
        const d = await r.json(); sugg.value.services = d.services || []; sugg.value.strengths = d.strengths || [];
    } catch (e) { /* on reste sur la saisie libre */ }
    finally { sugg.value.loading = false; }
};
const toggle = (arr, v) => { const i = arr.indexOf(v); if (i >= 0) arr.splice(i, 1); else if (arr.length < 12) arr.push(v); };
const customService = ref('');
const addService = () => { const v = customService.value.trim(); if (v && !form.services.includes(v) && form.services.length < 12) form.services.push(v); customService.value = ''; };

/* ── Étape 5 : photos ── */
const photos = ref([]); // { id, url }
const uploading = ref(false);
const fileInput = ref(null);
const upload = async (e) => {
    const files = Array.from(e.target.files || []).slice(0, props.maxPhotos - photos.value.length);
    if (!files.length) return;
    uploading.value = true; error.value = '';
    const fd = new FormData(); files.forEach((f) => fd.append('photos[]', f));
    try {
        const r = await fetch(route('intake.photos'), { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' } });
        const d = await r.json();
        if (!r.ok) throw new Error(d.message || 'Envoi impossible.');
        photos.value.push(...(d.photos || [])); form.photos = photos.value.map((p) => p.id);
    } catch (err) { error.value = err.message; }
    finally { uploading.value = false; if (fileInput.value) fileInput.value.value = ''; }
};
const removePhoto = (id) => { photos.value = photos.value.filter((p) => p.id !== id); form.photos = photos.value.map((p) => p.id); };

/* ── Navigation ── */
const emailOk = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v || '');
const validate = () => {
    switch (cur.value.key) {
        case 'who': if (form.name.trim().length < 2) return 'Indiquez le nom de votre entreprise.'; if (form.city.trim().length < 2) return 'Indiquez votre ville.'; return '';
        case 'sector': if (!form.sector && form.sector_hint.trim().length < 3) return 'Choisissez votre métier ou décrivez-le en quelques mots.'; return '';
        case 'contact': if (form.phone.trim().length < 6) return 'Un numéro de téléphone est indispensable : c\'est ainsi que vos clients vous appellent.'; if (!emailOk(form.email)) return 'Entrez un email valide : il sert à retrouver votre site.'; return '';
        default: return '';
    }
};
const next = async () => {
    const e = validate(); if (e) { error.value = e; return; }
    error.value = '';
    if (step.value < STEPS.length - 1) {
        step.value++;
        if (cur.value.key === 'offer' && !sugg.value.services.length) loadSuggestions();
        await nextTick(); window.scrollTo({ top: 0, behavior: 'smooth' }); document.querySelector('[data-focus]')?.focus({ preventScroll: true });
    }
};
const back = () => { error.value = ''; if (step.value > 0) step.value--; };
const submit = () => {
    const e = validate(); if (e) { error.value = e; return; }
    form.since = form.since || null;
    form.post(route('intake.store'), { onError: (errs) => { error.value = Object.values(errs)[0] || 'Vérifiez vos réponses.'; } });
};
onMounted(() => { document.querySelector('[data-focus]')?.focus(); if (form.name) lookForProfile(); });
</script>

<template>
    <Head title="Créer mon site sans fiche Google" />
    <div class="min-h-[100dvh] bg-ink-950 bg-mesh text-slate-200">
        <header class="mx-auto flex max-w-3xl items-center justify-between px-5 py-5">
            <Link :href="route('home')" class="block" aria-label="Joow"><BrandLogo class="h-9 w-auto" /></Link>
            <span class="text-xs font-semibold text-slate-400">Étape {{ step + 1 }} / {{ STEPS.length }}</span>
        </header>

        <!-- Progression -->
        <div class="mx-auto flex max-w-3xl gap-1.5 px-5">
            <span v-for="(s, i) in STEPS" :key="s.key" class="h-1.5 flex-1 rounded-full transition-colors" :class="i < step ? 'bg-emerald-400' : (i === step ? 'bg-brand-gradient' : 'bg-white/10')"></span>
        </div>

        <main class="mx-auto max-w-3xl px-5 pb-32 pt-8 sm:pt-12">
            <p v-if="notice && step === 0" class="mb-6 rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">{{ notice }}<span v-if="prefill.from_url" class="block text-xs text-amber-200/70">Site concerné : {{ prefill.from_url }}</span></p>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brand-400">{{ cur.title }}</p>
            <h1 class="mt-2 font-display text-2xl font-bold leading-tight text-white sm:text-4xl">{{ cur.q }}</h1>

            <!-- 1. Nom + ville -->
            <section v-if="cur.key === 'who'" class="mt-8 space-y-4">
                <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Nom de l'entreprise</span><input v-model="form.name" data-focus type="text" class="field !py-4 text-base" placeholder="Ex : Plomberie Martin" autocomplete="organization" @keydown.enter.prevent="next" /></label>
                <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Ville</span><input v-model="form.city" type="text" class="field !py-4 text-base" placeholder="Ex : Toulouse" autocomplete="address-level2" @keydown.enter.prevent="next" /></label>

                <!-- Fiche Google trouvée en arrière-plan -->
                <div v-if="matches.length && !dismissed" class="rounded-2xl border border-emerald-400/25 bg-emerald-500/[0.07] p-4">
                    <p class="text-sm font-semibold text-emerald-200">Nous avons trouvé une fiche Google qui ressemble à la vôtre. C'est vous ?</p>
                    <p class="mt-1 text-xs text-slate-400">En l'utilisant, vos avis, votre note et vos photos Google sont repris automatiquement. Plus rien à remplir.</p>
                    <div class="mt-3 space-y-2">
                        <button v-for="r in matches" :key="r.place_id" type="button" @click="useProfile(r)" class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-ink-900/60 px-4 py-3 text-left transition hover:border-emerald-400/50">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/5 text-base">📍</span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-white">{{ r.main }}</span><span class="block truncate text-xs text-slate-400">{{ r.secondary }}</span></span>
                            <span class="shrink-0 text-xs font-semibold text-emerald-300">Utiliser →</span>
                        </button>
                    </div>
                    <button type="button" @click="dismissed = true; matches = []" class="mt-3 text-xs text-slate-400 underline-offset-2 hover:text-white hover:underline">Non, ce n'est pas moi · continuer sans fiche</button>
                </div>
                <p v-else-if="matching" class="text-xs text-slate-500">Vérification d'une éventuelle fiche Google…</p>
            </section>

            <!-- 2. Métier -->
            <section v-else-if="cur.key === 'sector'" class="mt-8">
                <input v-model="sectorQuery" data-focus type="text" class="field !py-4 text-base" placeholder="Tapez votre métier : plombier, coiffeur, restaurant…" />
                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-for="s in sectorMatches" :key="s.key" type="button" @click="pickSector(s)" class="rounded-full border px-4 py-2 text-sm font-semibold transition" :class="form.sector === s.key ? 'border-brand-400 bg-brand-500/20 text-white' : 'border-white/10 text-slate-300 hover:border-white/30'">{{ s.label }}</button>
                </div>
                <p v-if="!sectorMatches.length" class="mt-3 text-sm text-slate-400">Aucun métier ne correspond exactement : décrivez-le ci-dessous, l'IA s'adapte.</p>
                <button type="button" @click="otherSector = !otherSector" class="mt-5 text-sm font-semibold text-slate-400 underline-offset-2 hover:text-white hover:underline">{{ otherSector ? 'Revenir à la liste' : 'Mon métier n\'est pas dans la liste' }}</button>
                <label v-if="otherSector || (!sectorMatches.length)" class="mt-3 block"><span class="mb-1.5 block text-sm font-semibold text-white">Décrivez votre activité en quelques mots</span><input v-model="form.sector_hint" type="text" class="field" placeholder="Ex : réparation de trottinettes électriques" @input="form.sector = ''" @keydown.enter.prevent="next" /></label>
                <p v-if="form.sector" class="mt-4 text-sm text-emerald-300">✓ {{ form.sector_hint }} · modèle de site dédié</p>
            </section>

            <!-- 3. Coordonnées -->
            <section v-else-if="cur.key === 'contact'" class="mt-8 space-y-4">
                <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Téléphone <span class="text-brand-400">*</span></span><input v-model="form.phone" data-focus type="tel" inputmode="tel" class="field !py-4 text-base" placeholder="06 12 34 56 78" autocomplete="tel" @keydown.enter.prevent="next" /></label>
                <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Email <span class="text-brand-400">*</span></span><input v-model="form.email" type="email" inputmode="email" class="field !py-4 text-base" placeholder="prenom@exemple.fr" autocomplete="email" @keydown.enter.prevent="next" /><span class="mt-1 block text-xs text-slate-500">Pour retrouver votre site et recevoir votre lien privé. Aucun compte à créer.</span></label>
                <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Adresse <span class="font-normal text-slate-500">(facultatif, si vous recevez du public)</span></span><input v-model="form.address" type="text" class="field" placeholder="12 rue des Lilas, 31000 Toulouse" autocomplete="street-address" /></label>
                <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Zone d'intervention <span class="font-normal text-slate-500">(facultatif)</span></span><input v-model="form.zone" type="text" class="field" placeholder="Ex : Toulouse et 30 km alentour" /></label>
            </section>

            <!-- 4. Prestations & points forts -->
            <section v-else-if="cur.key === 'offer'" class="mt-8 space-y-7">
                <div>
                    <p class="text-sm font-semibold text-white">Vos prestations principales <span class="font-normal text-slate-500">· {{ form.services.length }} choisie{{ form.services.length > 1 ? 's' : '' }}</span></p>
                    <p v-if="sugg.loading" class="mt-2 text-xs text-slate-500">L'IA prépare des suggestions pour votre métier…</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-for="s in [...new Set([...form.services, ...sugg.services])]" :key="s" type="button" @click="toggle(form.services, s)" class="rounded-full border px-3.5 py-2 text-sm transition" :class="form.services.includes(s) ? 'border-brand-400 bg-brand-500/20 font-semibold text-white' : 'border-white/10 text-slate-300 hover:border-white/30'">{{ form.services.includes(s) ? '✓ ' : '+ ' }}{{ s }}</button>
                    </div>
                    <div class="mt-3 flex gap-2"><input v-model="customService" type="text" class="field flex-1 !py-2.5 text-sm" placeholder="Ajouter une prestation…" @keydown.enter.prevent="addService" /><button type="button" @click="addService" class="rounded-xl border border-white/15 px-4 text-sm font-semibold text-white hover:bg-white/5">Ajouter</button></div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">Vos points forts</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-for="s in sugg.strengths" :key="s" type="button" @click="toggle(form.strengths, s)" class="rounded-full border px-3.5 py-2 text-sm transition" :class="form.strengths.includes(s) ? 'border-emerald-400 bg-emerald-500/15 font-semibold text-white' : 'border-white/10 text-slate-300 hover:border-white/30'">{{ form.strengths.includes(s) ? '✓ ' : '' }}{{ s }}</button>
                    </div>
                </div>
                <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Deux phrases sur vous <span class="font-normal text-slate-500">(facultatif, mais ça rend le site plus vrai)</span></span><textarea v-model="form.description" rows="3" class="field text-sm" placeholder="Ex : Artisan depuis 2009, je travaille seul, j'interviens vite et j'explique toujours ce que je fais avant de commencer."></textarea></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Année de création <span class="font-normal text-slate-500">(facultatif)</span></span><input v-model="form.since" type="number" inputmode="numeric" min="1900" :max="new Date().getFullYear()" class="field" placeholder="2015" /></label>
                    <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Horaires <span class="font-normal text-slate-500">(facultatif)</span></span><input v-model="form.hours" type="text" class="field" placeholder="Lun-ven 8h-19h, sam 9h-12h" /></label>
                </div>
            </section>

            <!-- 5. Photos -->
            <section v-else-if="cur.key === 'photos'" class="mt-8">
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed border-white/15 bg-white/[0.03] px-6 py-10 text-center transition hover:border-brand-400/60" :class="{ 'opacity-60': uploading || photos.length >= maxPhotos }">
                    <span class="text-3xl">📷</span>
                    <span class="mt-3 text-sm font-semibold text-white">{{ uploading ? 'Envoi en cours…' : (photos.length ? 'Ajouter d\'autres photos' : 'Choisir des photos') }}</span>
                    <span class="mt-1 text-xs text-slate-500">Jusqu'à {{ maxPhotos }} · votre devanture, votre équipe, vos réalisations, vos produits</span>
                    <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" :disabled="uploading || photos.length >= maxPhotos" @change="upload" />
                </label>
                <div v-if="photos.length" class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <div v-for="ph in photos" :key="ph.id" class="group relative aspect-square overflow-hidden rounded-xl bg-white/5">
                        <img :src="ph.url" class="h-full w-full object-cover" alt="" />
                        <button type="button" @click="removePhoto(ph.id)" class="absolute right-1.5 top-1.5 grid h-7 w-7 place-items-center rounded-full bg-black/70 text-sm text-white" aria-label="Retirer">✕</button>
                    </div>
                </div>
                <p class="mt-4 text-xs text-slate-500">{{ photos.length ? 'L\'IA lira vos photos pour choisir le visuel principal et illustrer chaque prestation.' : 'Pas de photo sous la main ? Des visuels professionnels de votre métier seront utilisés, vous pourrez les remplacer dans le Studio.' }}</p>
            </section>

            <p v-if="error" class="mt-5 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm font-medium text-rose-200" role="alert">{{ error }}</p>
        </main>

        <!-- Barre d'actions fixe (mobile d'abord) -->
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-white/[0.06] bg-ink-950/90 backdrop-blur" style="padding-bottom: env(safe-area-inset-bottom)">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-5 py-3">
                <button v-if="step > 0" type="button" @click="back" class="rounded-xl px-3 py-3 text-sm font-semibold text-slate-400 hover:text-white">← Retour</button>
                <Link v-else :href="route('home')" class="rounded-xl px-3 py-3 text-sm font-semibold text-slate-400 hover:text-white">← Accueil</Link>
                <button v-if="step < STEPS.length - 1" type="button" @click="next" class="btn-brand !px-7 !py-3.5">{{ cur.key === 'offer' && !form.services.length ? 'Passer →' : 'Continuer →' }}</button>
                <button v-else type="button" @click="submit" :disabled="form.processing || uploading" class="btn-brand !px-7 !py-3.5 disabled:opacity-60">{{ form.processing ? 'Création…' : (photos.length ? '✨ Générer mon site' : '✨ Générer sans photo') }}</button>
            </div>
        </div>
    </div>
</template>
