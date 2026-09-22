<script setup>
// Nom de domaine d'un site : connecter un domaine existant (DNS) ou en acheter un (inclus).
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({ slug: { type: String, required: true } });
const st = ref(null);
const loading = ref(true);
const busy = ref(false);
const err = ref('');
const mode = ref('own');            // own | buy
const own = ref('');
const q = ref('');
const results = ref(null);
const searching = ref(false);
let timer = null;

const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const headers = () => ({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' });
const call = async (method, url, body) => {
    const r = await fetch(url, { method, headers: headers(), body: body ? JSON.stringify(body) : undefined });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.message || d.error || 'Erreur');
    return d;
};
const load = async () => { try { st.value = await call('GET', route('domains.state', props.slug)); err.value = ''; } catch (e) { err.value = e.message; } finally { loading.value = false; } };
const act = async (fn) => { busy.value = true; err.value = ''; try { st.value = await fn(); } catch (e) { err.value = e.message; } finally { busy.value = false; } };
const connect = () => own.value && act(() => call('POST', route('domains.connect', props.slug), { domain: own.value }));
const check = () => act(() => call('POST', route('domains.check', props.slug)));
const remove = () => confirm('Retirer ce domaine du site ?') && act(() => call('DELETE', route('domains.remove', props.slug)));
const order = (d) => confirm(`Commander ${d} ${st.value.included ? '(inclus dans votre formule)' : ''} ?`) && act(() => call('POST', route('domains.order', props.slug), { domain: d }));
const search = async () => {
    const v = q.value.trim(); if (v.length < 2) { results.value = null; return; }
    searching.value = true;
    try { const d = await call('GET', route('domains.search', props.slug) + '?q=' + encodeURIComponent(v)); results.value = d.results; }
    catch (e) { err.value = e.message; results.value = []; }
    finally { searching.value = false; }
};
watch(q, () => { clearTimeout(timer); timer = setTimeout(search, 500); });
const price = (r) => r.first_price != null ? (r.first_price / 100).toLocaleString('fr-FR', { style: 'currency', currency: r.currency || 'EUR' }) + ' / an' : '';
const copy = async (t) => { try { await navigator.clipboard.writeText(t); } catch { prompt('Copiez :', t); } };

const steps = computed(() => {
    if (!st.value?.domain) return [];
    const s = st.value;
    return [
        { label: s.source === 'hostinger' ? 'Domaine commandé' : 'Domaine enregistré', done: true },
        { label: 'DNS pointé vers le serveur', done: s.dns_ok, hint: s.dns_ok ? (s.www_ok ? 'Apex et www OK' : 'Apex OK · www pas encore') : 'En attente de propagation (jusqu\'à 24 h, souvent quelques minutes)' },
        { label: 'Certificat HTTPS', done: s.ssl, hint: s.ssl ? 'Let\'s Encrypt, renouvelé automatiquement' : (s.dns_ok ? 'Émission en cours (1 à 2 minutes)' : 'Après le DNS') },
        { label: 'Site en ligne sur votre domaine', done: s.status === 'active' },
    ];
});
let poll = null;
onMounted(() => { load(); poll = setInterval(() => { if (st.value?.domain && st.value.status !== 'active') load(); }, 20000); });
onUnmounted(() => clearInterval(poll));
</script>

<template>
    <div class="glass rounded-2xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="font-display text-lg font-bold text-white">🌐 Nom de domaine</h3>
                <p v-if="st" class="mt-1 text-sm text-slate-400">Votre site est en ligne sur <a :href="st.live_url" target="_blank" class="text-brand-400">{{ st.live_url.replace('https://', '') }}</a>. Un domaine .fr ou .com est inclus dans votre formule.</p>
            </div>
            <span v-if="st?.domain" class="chip" :class="st.status==='active' ? 'bg-emerald-500/15 text-emerald-300' : st.status==='error' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300'">{{ st.status==='active' ? 'Actif' : st.status==='error' ? 'Problème' : st.status==='registering' ? 'Commande en cours' : 'Configuration en cours' }}</span>
        </div>

        <p v-if="err" class="mt-3 rounded-lg bg-rose-500/10 px-3 py-2 text-sm text-rose-300">{{ err }}</p>
        <p v-if="loading" class="mt-4 text-sm text-slate-500">Chargement…</p>

        <!-- Pas encore en ligne -->
        <div v-else-if="st && !st.paid" class="mt-4 rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-slate-300">
            Mettez votre site en ligne (formule Pro ou Liberté) pour brancher votre nom de domaine. Le premier domaine est inclus.
        </div>

        <!-- Domaine en place : suivi -->
        <div v-else-if="st?.domain" class="mt-5">
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-display text-2xl font-bold text-white">{{ st.domain }}</p>
                <a v-if="st.status==='active'" :href="st.url" target="_blank" class="text-sm text-brand-400">Ouvrir ↗</a>
                <span v-if="st.source==='hostinger' && st.expires_at" class="text-xs text-slate-500">Renouvellement : {{ st.expires_at }}</span>
            </div>
            <ol class="mt-4 space-y-2">
                <li v-for="(s, i) in steps" :key="i" class="flex items-start gap-3 text-sm">
                    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold" :class="s.done ? 'bg-emerald-500/20 text-emerald-300' : 'bg-white/10 text-slate-400'">{{ s.done ? '✓' : i + 1 }}</span>
                    <span><span :class="s.done ? 'text-white' : 'text-slate-300'">{{ s.label }}</span><span v-if="s.hint && !s.done" class="block text-xs text-slate-500">{{ s.hint }}</span></span>
                </li>
            </ol>
            <p v-if="st.status==='error' && st.error" class="mt-3 rounded-lg bg-rose-500/10 px-3 py-2 text-xs text-rose-300">{{ st.error }}</p>

            <!-- Instructions DNS (domaine possédé) -->
            <div v-if="st.source!=='hostinger' && !st.dns_ok" class="mt-5 rounded-xl border border-white/10 bg-ink-900/60 p-4">
                <p class="text-sm font-semibold text-white">Chez votre registrar (OVH, Gandi, GoDaddy, Ionos…), dans la zone DNS de {{ st.domain }}, créez :</p>
                <table class="mt-3 w-full text-left text-sm">
                    <thead><tr class="text-xs uppercase tracking-wider text-slate-500"><th class="py-1 pr-3">Type</th><th class="py-1 pr-3">Nom</th><th class="py-1">Valeur</th></tr></thead>
                    <tbody>
                        <tr v-for="r in st.instructions" :key="r.type" class="border-t border-white/[0.06]">
                            <td class="py-2 pr-3 font-mono text-slate-200">{{ r.type }}</td>
                            <td class="py-2 pr-3 font-mono text-slate-200">{{ r.name }}</td>
                            <td class="py-2"><button @click="copy(r.value)" class="rounded bg-white/5 px-2 py-0.5 font-mono text-brand-300 hover:bg-white/10" title="Copier">{{ r.value }}</button></td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-slate-500">Supprimez les anciens enregistrements A ou CNAME sur « @ » et « www » s'il en existe. Vos emails ne sont pas concernés (enregistrements MX inchangés).</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button @click="check" :disabled="busy" class="btn-brand !py-2 text-sm">{{ busy ? 'Vérification…' : 'Vérifier maintenant' }}</button>
                <button v-if="st.source!=='hostinger' || st.status==='error'" @click="remove" :disabled="busy" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:border-white/25">Retirer le domaine</button>
                <span v-if="st.checked_at" class="self-center text-xs text-slate-500">Dernière vérification {{ st.checked_at }}</span>
            </div>
        </div>

        <!-- Choix : domaine existant ou nouveau -->
        <div v-else-if="st" class="mt-5">
            <div class="flex gap-1 rounded-xl bg-white/[0.04] p-1 text-sm font-semibold">
                <button @click="mode='buy'" class="flex-1 rounded-lg px-3 py-2" :class="mode==='buy' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'">✨ Nouveau domaine{{ st.included ? ' (inclus)' : '' }}</button>
                <button @click="mode='own'" class="flex-1 rounded-lg px-3 py-2" :class="mode==='own' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'">🔗 J'ai déjà un domaine</button>
            </div>

            <div v-if="mode==='own'" class="mt-4">
                <p class="text-sm text-slate-400">Vous gardez votre domaine chez votre registrar actuel. Nous vous indiquons deux enregistrements DNS à créer, le HTTPS est automatique. Vos emails ne bougent pas.</p>
                <form @submit.prevent="connect" class="mt-3 flex flex-wrap gap-2">
                    <input v-model="own" class="field flex-1" placeholder="mon-etablissement.fr" required />
                    <button class="btn-brand !py-2.5 text-sm" :disabled="busy">Connecter ce domaine</button>
                </form>
            </div>

            <div v-else class="mt-4">
                <template v-if="st.purchase_enabled">
                    <p class="text-sm text-slate-400">Tapez le nom souhaité : on vérifie la disponibilité en direct, on achète, on configure. Aucune manipulation de votre côté.</p>
                    <div class="relative mt-3">
                        <input v-model="q" class="field pr-10" placeholder="Ex : latabledemile" />
                        <span v-if="searching" class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-white/20 border-t-brand-500"></span>
                    </div>
                    <div v-if="results" class="mt-3 divide-y divide-white/[0.06] overflow-hidden rounded-xl border border-white/10">
                        <div v-for="r in results" :key="r.domain" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span class="font-semibold" :class="r.available ? 'text-white' : 'text-slate-500 line-through'">{{ r.domain }}</span>
                            <span v-if="!r.available" class="text-xs text-slate-500">Indisponible</span>
                            <span v-else class="flex items-center gap-3">
                                <span class="text-xs" :class="st.included ? 'text-emerald-300' : 'text-slate-400'">{{ st.included ? 'Inclus' : price(r) }}</span>
                                <button @click="order(r.domain)" :disabled="busy || !r.item_id" class="btn-brand !px-3 !py-1.5 text-xs disabled:opacity-50">Commander</button>
                            </span>
                        </div>
                        <p v-if="!results.length" class="px-4 py-3 text-sm text-slate-500">Aucun résultat.</p>
                    </div>
                </template>
                <div v-else class="rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-slate-300">
                    L'achat de domaine en un clic arrive très bientôt. En attendant, connectez un domaine existant ou écrivez-nous : nous commandons le domaine inclus dans votre formule pour vous.
                </div>
            </div>
        </div>
    </div>
</template>
