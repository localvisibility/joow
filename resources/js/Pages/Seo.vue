<script setup>
/* Référencement : audit noté du site, conseils concrets, réglages par page (expression clé, titre, description,
   images) et corrections par l'IA (1 crédit), ou demande guidée à l'assistant du Studio pour le contenu. */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({ sites: Array, current: String, audit: Object, credits: Object, suggestions: Object, cost: Number });

const audit = ref(props.audit);
const credits = ref(props.credits);
const sugg = ref(props.suggestions);
const err = ref('');
const done = ref('');
const busy = ref('');
const sel = ref(props.audit?.pages?.[0]?.key || 'index');
const page = computed(() => audit.value?.pages?.find((p) => p.key === sel.value) || audit.value?.pages?.[0]);
const form = reactive({ keyword: '', title: '', description: '' });
const alts = reactive({});
const fill = () => {
    if (!page.value) return;
    form.keyword = page.value.keyword || '';
    form.title = page.value.custom?.title || page.value.title || '';
    form.description = page.value.custom?.description || page.value.description || '';
    for (const k of Object.keys(alts)) delete alts[k];
    for (const i of page.value.images || []) alts[i.src] = i.alt || '';
};
watch(page, fill, { immediate: true });

const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const api = async (url, body) => {
    const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || d.message || (r.status >= 500 ? 'Le serveur n\'a pas répondu, réessayez.' : 'Erreur'));
    return d;
};
const flash = (m) => { done.value = m; setTimeout(() => (done.value = ''), 4000); };
const run = async (label, fn) => {
    busy.value = label; err.value = '';
    try { await fn(); } catch (e) { err.value = e.message; } finally { busy.value = ''; }
};
const save = (extra = {}) => run('save', async () => {
    const p = page.value;
    const same = (v, rendered, custom) => (!custom && v === rendered ? '' : v); // inchangé : on garde le titre automatique
    const d = await api(route('seo.save', props.current), { key: p.key, keyword: form.keyword, title: same(form.title, p.title, p.custom?.title), description: same(form.description, p.description, p.custom?.description), ...extra });
    audit.value = d.audit; flash('Enregistré ✓ (le site en ligne est mis à jour)');
});
const saveAlts = () => run('alts-save', async () => {
    const d = await api(route('seo.save', props.current), { key: page.value.key, alts: { ...alts } });
    audit.value = d.audit; flash('Descriptions des images enregistrées ✓');
});
const fix = (type, key = null) => run(type + (key || ''), async () => {
    const d = await api(route('seo.fix', props.current), { type, key });
    audit.value = d.audit; if (d.credits) credits.value = d.credits; if (d.suggestions) sugg.value = d.suggestions;
    fill(); flash((d.done || 'C\'est fait') + ' ✓');
});
const useKeyword = (k) => { form.keyword = k; };
const askUrl = (c) => route('sites.editor', props.current) + '?' + new URLSearchParams({ ia: c.ask, ...(page.value?.slug ? { page: page.value.slug } : {}) }).toString();
const ideaUrl = (i) => route('sites.editor', props.current) + '?' + new URLSearchParams({ ia: `Crée une page dédiée à « ${i} » : texte utile et détaillé, sous-titres clairs, FAQ et appel à l'action, sans inventer de prix, d'avis ni de certifications.` }).toString();
const selectSite = (slug) => router.get(route('seo.index'), { site: slug });

const pageSugg = computed(() => sugg.value?.pages?.find((p) => p.key === page.value?.key));
const scoreColor = (s) => (s >= 80 ? 'text-emerald-300' : s >= 55 ? 'text-amber-300' : 'text-rose-300');
const ring = (s) => `conic-gradient(${s >= 80 ? '#34d399' : s >= 55 ? '#fbbf24' : '#fb7185'} ${s * 3.6}deg, rgba(255,255,255,.08) 0deg)`;
const pageScore = (p) => { const c = p.checks; const t = c.reduce((a, x) => a + x.weight, 0); const g = c.reduce((a, x) => a + (x.status === 'ok' ? x.weight : x.status === 'warn' ? x.weight * 0.35 : 0), 0); return t ? Math.round((g / t) * 100) : 0; };
const icon = { ok: '✓', warn: '!', error: '✕' };
const tone = { ok: 'bg-emerald-500/15 text-emerald-300', warn: 'bg-amber-500/15 text-amber-300', error: 'bg-rose-500/15 text-rose-300' };
const sorted = (list) => [...list].sort((a, b) => ({ error: 0, warn: 1, ok: 2 }[a.status] - { error: 0, warn: 1, ok: 2 }[b.status]));
const len = (s, [a, b]) => { const n = (s || '').length; return { n, cls: n === 0 ? 'text-rose-300' : n < a || n > b ? 'text-amber-300' : 'text-emerald-300' }; };
const host = computed(() => (page.value?.url || '').replace(/^https?:\/\//, '').replace(/\/$/, '').replace(/\//g, ' › '));
const missingAlts = computed(() => (page.value?.images || []).filter((i) => !i.alt).length);
const costLabel = computed(() => (credits.value?.free ? 'gratuit (admin)' : `${props.cost} crédit IA`));
</script>

<template>
    <Head title="Référencement" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-white">🔎 Référencement</h1>
                    <p class="mt-1 text-sm text-slate-400">Votre note Google, ce qu'il faut améliorer, et l'IA pour le faire à votre place.</p>
                </div>
                <select v-if="sites.length > 1" :value="current" @change="selectSite($event.target.value)" class="rounded-xl border border-white/10 bg-ink-900 px-4 py-2.5 text-sm text-white">
                    <option v-for="s in sites" :key="s.slug" :value="s.slug">{{ s.name }}{{ s.city ? ' · ' + s.city : '' }}{{ s.online ? '' : ' (aperçu)' }}</option>
                </select>
            </div>
        </template>

        <div v-if="!audit" class="glass rounded-2xl p-10 text-center text-slate-400">Créez d'abord un site.</div>
        <div v-else class="space-y-6">
            <p v-if="err" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ err }}</p>
            <p v-if="done" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ done }}</p>

            <!-- Note globale -->
            <div class="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
                <div class="glass flex flex-col items-center rounded-3xl p-6 text-center">
                    <div class="grid h-36 w-36 place-items-center rounded-full" :style="{ background: ring(audit.score) }">
                        <div class="grid h-28 w-28 place-items-center rounded-full bg-ink-950">
                            <div><p class="font-display text-4xl font-bold" :class="scoreColor(audit.score)">{{ audit.score }}</p><p class="text-xs text-slate-500">/ 100</p></div>
                        </div>
                    </div>
                    <p class="mt-4 font-semibold text-white">{{ audit.score >= 80 ? 'Très bon référencement' : audit.score >= 55 ? 'Bonne base, à améliorer' : 'Des points importants à corriger' }}</p>
                    <p class="mt-1 text-xs text-slate-400"><span class="text-rose-300">{{ audit.counts.error }} à corriger</span> · <span class="text-amber-300">{{ audit.counts.warn }} à améliorer</span> · <span class="text-emerald-300">{{ audit.counts.ok }} OK</span></p>
                    <button @click="fix('keywords')" :disabled="!!busy" class="btn-brand mt-5 w-full text-sm">{{ busy === 'keywords' ? 'L\'IA cherche…' : '✨ Trouver mes expressions clés' }}</button>
                    <p class="mt-1.5 text-[11px] text-slate-500">{{ costLabel }} · solde : {{ credits?.free ? '∞' : credits?.balance }}</p>
                </div>
                <div class="glass rounded-3xl p-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tout le site</p>
                    <ul class="mt-3 divide-y divide-white/[0.06]">
                        <li v-for="c in sorted(audit.site)" :key="c.id" class="flex items-start gap-3 py-2.5">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold" :class="tone[c.status]">{{ icon[c.status] }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-100">{{ c.label }}</p>
                                <p v-if="c.advice && c.status !== 'ok'" class="mt-0.5 text-xs text-slate-400">{{ c.advice }}</p>
                            </div>
                            <a v-if="c.status !== 'ok' && c.fix === 'publish'" :href="route('public.site', current)" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">Mettre en ligne</a>
                            <a v-else-if="c.status !== 'ok' && c.fix === 'domain'" :href="route('hosting.index')" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">Choisir un domaine</a>
                            <a v-else-if="c.status !== 'ok' && c.ask" :href="askUrl(c)" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">✨ Demander à l'IA</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Pages -->
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button v-for="p in audit.pages" :key="p.key" @click="sel = p.key" class="shrink-0 rounded-xl px-4 py-2 text-sm font-semibold"
                    :class="sel === p.key ? 'bg-brand-500 text-white' : 'bg-white/[0.05] text-slate-300 hover:bg-white/10'">
                    {{ p.label }} <span class="ml-1 text-xs" :class="sel === p.key ? 'text-white/80' : scoreColor(pageScore(p))">{{ pageScore(p) }}</span>
                </button>
            </div>

            <div v-if="page" class="grid gap-6 xl:grid-cols-[1.1fr_1fr]">
                <div class="space-y-6">
                    <!-- Aperçu Google + réglages -->
                    <div class="glass rounded-3xl p-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Aperçu dans Google</p>
                        <div class="mt-3 rounded-2xl bg-white p-4">
                            <p class="truncate text-xs text-[#4d5156]">{{ host }}</p>
                            <p class="mt-1 line-clamp-1 text-lg leading-snug text-[#1a0dab]">{{ form.title || 'Titre de la page' }}</p>
                            <p class="mt-1 line-clamp-2 text-sm text-[#4d5156]">{{ form.description || 'Ajoutez une description qui donne envie de cliquer.' }}</p>
                        </div>

                        <label class="mt-5 block text-xs font-semibold text-slate-400">Expression clé visée
                            <input v-model="form.keyword" maxlength="80" placeholder="ex. plombier Chartres" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" />
                        </label>
                        <div v-if="pageSugg" class="mt-2 flex flex-wrap gap-1.5">
                            <button v-for="k in [pageSugg.keyword, ...pageSugg.variants]" :key="k" @click="useKeyword(k)" class="rounded-full bg-brand-500/15 px-2.5 py-1 text-xs text-brand-200 hover:bg-brand-500/25">{{ k }}</button>
                        </div>
                        <label class="mt-4 block text-xs font-semibold text-slate-400">
                            <span class="flex justify-between"><span>Titre (balise title)</span><span :class="len(form.title, [30, 62]).cls">{{ len(form.title, [30, 62]).n }} / 60</span></span>
                            <input v-model="form.title" maxlength="80" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white" />
                        </label>
                        <label class="mt-4 block text-xs font-semibold text-slate-400">
                            <span class="flex justify-between"><span>Description (meta description)</span><span :class="len(form.description, [70, 158]).cls">{{ len(form.description, [70, 158]).n }} / 155</span></span>
                            <textarea v-model="form.description" rows="3" maxlength="200" class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"></textarea>
                        </label>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button @click="save()" :disabled="!!busy" class="btn-brand text-sm">{{ busy === 'save' ? 'Enregistrement…' : 'Enregistrer' }}</button>
                            <button @click="fix('meta', page.key)" :disabled="!!busy" class="rounded-xl border border-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">{{ busy === 'meta' + page.key ? 'L\'IA rédige…' : '✨ Rédiger avec l\'IA' }}</button>
                            <span class="self-center text-[11px] text-slate-500">{{ costLabel }}</span>
                        </div>
                    </div>

                    <!-- Images -->
                    <div v-if="page.images.length" class="glass rounded-3xl p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Images de la page ({{ page.images.length }})</p>
                            <button v-if="missingAlts" @click="fix('alts', page.key)" :disabled="!!busy" class="rounded-lg bg-brand-500/20 px-3 py-1.5 text-xs font-semibold text-brand-100 hover:bg-brand-500/30">{{ busy === 'alts' + page.key ? 'L\'IA regarde les photos…' : `✨ Décrire les ${missingAlts} image(s) avec l'IA` }}</button>
                        </div>
                        <div class="mt-3 max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                            <div v-for="i in page.images" :key="i.src" class="flex items-center gap-3">
                                <img :src="i.src" class="h-12 w-16 shrink-0 rounded-lg object-cover" alt="" loading="lazy" />
                                <input v-model="alts[i.src]" maxlength="160" placeholder="Décrivez ce qu'on voit (ex. plombier réparant un chauffe-eau à Chartres)" class="min-w-0 flex-1 rounded-lg border px-3 py-1.5 text-xs text-white" :class="alts[i.src] ? 'border-white/10 bg-white/5' : 'border-amber-400/40 bg-amber-500/5'" />
                            </div>
                        </div>
                        <button @click="saveAlts" :disabled="!!busy" class="mt-3 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">{{ busy === 'alts-save' ? 'Enregistrement…' : 'Enregistrer les descriptions' }}</button>
                    </div>
                </div>

                <!-- Analyse de la page -->
                <div class="space-y-6">
                    <div class="glass rounded-3xl p-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Analyse de « {{ page.label }} »</p>
                        <ul class="mt-3 divide-y divide-white/[0.06]">
                            <li v-for="c in sorted(page.checks)" :key="c.id" class="flex items-start gap-3 py-2.5">
                                <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold" :class="tone[c.status]">{{ icon[c.status] }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-100">{{ c.label }}</p>
                                    <p v-if="c.advice && c.status !== 'ok'" class="mt-0.5 text-xs text-slate-400">{{ c.advice }}</p>
                                </div>
                                <template v-if="c.status !== 'ok'">
                                    <button v-if="c.fix === 'meta'" @click="fix('meta', page.key)" :disabled="!!busy" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">✨ Corriger</button>
                                    <button v-else-if="c.fix === 'alts'" @click="fix('alts', page.key)" :disabled="!!busy" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">✨ Corriger</button>
                                    <button v-else-if="c.fix === 'keywords'" @click="fix('keywords')" :disabled="!!busy" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">✨ Proposer</button>
                                    <a v-else-if="c.ask" :href="askUrl(c)" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">✨ Demander à l'IA</a>
                                </template>
                            </li>
                        </ul>
                        <p class="mt-3 text-[11px] text-slate-500">« Demander à l'IA » ouvre l'assistant du Studio avec la demande déjà rédigée : vous relisez l'aperçu avant d'enregistrer.</p>
                    </div>

                    <div class="glass rounded-3xl p-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Structure des titres</p>
                        <p class="mt-3 text-sm text-white"><span class="mr-2 rounded bg-brand-500/25 px-1.5 py-0.5 text-[10px] font-bold text-brand-100">H1</span>{{ page.h1[0] || '—' }}</p>
                        <p v-for="h in page.h2" :key="h" class="mt-1.5 pl-4 text-sm text-slate-300"><span class="mr-2 rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-bold text-slate-300">H2</span>{{ h }}</p>
                        <p class="mt-3 text-xs text-slate-500">{{ page.words }} mots · <a :href="page.url" target="_blank" class="text-brand-300 hover:underline">voir la page</a></p>
                    </div>

                    <div v-if="sugg?.ideas?.length" class="glass rounded-3xl p-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recherches locales pas encore couvertes</p>
                        <p class="mt-1 text-xs text-slate-400">Une page dédiée par recherche augmente vos chances d'apparaître dessus.</p>
                        <ul class="mt-3 space-y-2">
                            <li v-for="i in sugg.ideas" :key="i" class="flex items-center justify-between gap-3 text-sm text-slate-200">
                                <span>« {{ i }} »</span>
                                <a :href="ideaUrl(i)" class="shrink-0 rounded-lg bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">✨ Créer la page</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
