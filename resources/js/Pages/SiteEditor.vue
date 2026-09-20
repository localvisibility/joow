<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps({ site: Object });

const version = ref(Date.now());
const iframe = ref(null);
const device = ref('desktop');
const accent = ref(props.site.accent);
const modules = ref({ ...props.site.modules });

const messages = ref([
    { role: 'ai', text: `Bonjour 👋 Je suis votre assistant. Dites-moi ce que vous voulez changer sur le site de ${props.site.name} : textes, couleurs, services, horaires… je m'en occupe et l'aperçu se met à jour.` },
]);
const input = ref('');
const sending = ref(false);
const chatBox = ref(null);

const suggestions = [
    'Rends le ton plus chaleureux',
    'Ajoute un service de livraison',
    'Mets la couleur en bleu nuit',
    'Réécris l\'accroche pour attirer plus de clients',
];

const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const postJson = async (url, body) => {
    const r = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw data;
    return data;
};

const bust = (v) => { version.value = v || Date.now(); };
const scrollChat = () => nextTick(() => { if (chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight; });

const send = async (text) => {
    const msg = (text ?? input.value).trim();
    if (!msg || sending.value) return;
    input.value = '';
    messages.value.push({ role: 'user', text: msg });
    messages.value.push({ role: 'ai', text: '…', pending: true });
    scrollChat();
    sending.value = true;
    try {
        const d = await postJson(route('sites.editor.chat', props.site.slug), { message: msg });
        messages.value.pop();
        messages.value.push({ role: 'ai', text: d.reply || 'C\'est fait ✅' });
        if (d.accent) accent.value = d.accent;
        bust(d.version);
    } catch (e) {
        messages.value.pop();
        messages.value.push({ role: 'ai', text: e?.reply || 'Désolé, je n\'ai pas pu appliquer cette demande. Reformulez ?' });
    } finally {
        sending.value = false;
        scrollChat();
    }
};

const toggleModule = async (key) => {
    const enabled = !modules.value[key];
    modules.value[key] = enabled;
    try { const d = await postJson(route('sites.editor.module', props.site.slug), { key, enabled }); bust(d.version); }
    catch { modules.value[key] = !enabled; }
};

const palette = ['#4f46e5', '#e11d48', '#0d9488', '#db2777', '#ea580c', '#2563eb', '#7c3aed', '#059669', '#0f172a'];
const setAccent = async (c) => {
    accent.value = c;
    try { const d = await postJson(route('sites.editor.accent', props.site.slug), { accent: c }); bust(d.version); }
    catch { /* garde l'ancienne */ }
};

const regenerate = () => router.post(route('sites.editor.regenerate', props.site.slug), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="`Éditeur — ${site.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link :href="route('dashboard')" class="text-sm text-slate-400 hover:text-white">← Mes sites</Link>
                    <h1 class="font-display text-2xl font-bold text-white">{{ site.name }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <div class="glass flex rounded-xl p-1">
                        <button @click="device='desktop'" :class="device==='desktop' ? 'bg-white/10 text-white' : 'text-slate-400'" class="rounded-lg px-3 py-1.5 text-sm">🖥️</button>
                        <button @click="device='mobile'" :class="device==='mobile' ? 'bg-white/10 text-white' : 'text-slate-400'" class="rounded-lg px-3 py-1.5 text-sm">📱</button>
                    </div>
                    <a :href="`${site.live_url}?v=${version}`" target="_blank" rel="noopener" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/25">Ouvrir ↗</a>
                </div>
            </div>
        </template>

        <div v-if="!site.editable" class="glass mb-6 rounded-2xl border-amber-400/20 bg-amber-500/[0.06] p-5">
            <p class="font-semibold text-white">Édition IA à activer</p>
            <p class="mt-1 text-sm text-slate-400">Ce site a été importé sans contenu structuré. Régénérez-le à partir de sa fiche Google pour débloquer l'édition par chat.</p>
            <button v-if="site.has_place" @click="regenerate" class="btn-brand mt-4 text-sm">Régénérer avec l'IA</button>
        </div>

        <div class="grid gap-5 lg:grid-cols-[380px,1fr]">
            <!-- Panneau IA -->
            <div class="flex flex-col gap-4">
                <div class="glass flex h-[62vh] flex-col rounded-2xl">
                    <div class="border-b border-white/[0.06] px-5 py-4">
                        <p class="font-display font-bold text-white">✨ Assistant IA</p>
                        <p class="text-xs text-slate-500">Décrivez vos changements, ils s'appliquent en direct.</p>
                    </div>
                    <div ref="chatBox" class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                        <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role==='user' ? 'justify-end' : 'justify-start'">
                            <div :class="m.role==='user' ? 'bg-brand-gradient text-white' : 'bg-white/[0.05] text-slate-200'" class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm">
                                <span v-if="m.pending" class="inline-flex gap-1"><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style="animation-delay:.15s"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style="animation-delay:.3s"></span></span>
                                <span v-else>{{ m.text }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="messages.length <= 1" class="flex flex-wrap gap-2 px-5 pb-2">
                        <button v-for="s in suggestions" :key="s" @click="send(s)" class="rounded-full border border-white/10 px-3 py-1.5 text-xs text-slate-300 transition hover:border-white/25 hover:text-white">{{ s }}</button>
                    </div>
                    <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-white/[0.06] p-3">
                        <input v-model="input" :disabled="sending || !site.editable" type="text" placeholder="Ex : ajoute nos horaires du dimanche…" class="field flex-1 text-sm" />
                        <button type="submit" :disabled="sending || !site.editable" class="btn-brand shrink-0 px-4 py-2.5 text-sm">{{ sending ? '…' : '↑' }}</button>
                    </form>
                </div>

                <!-- Modules + couleur -->
                <div class="glass rounded-2xl p-5">
                    <p class="font-display font-bold text-white">Modules</p>
                    <label class="mt-3 flex cursor-pointer items-center justify-between">
                        <span class="text-sm text-slate-300">Réservation / RDV / devis</span>
                        <button type="button" @click="toggleModule('booking')" :class="modules.booking ? 'bg-brand-500' : 'bg-white/10'" class="relative h-6 w-11 rounded-full transition">
                            <span :class="modules.booking ? 'translate-x-5' : 'translate-x-0.5'" class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition"></span>
                        </button>
                    </label>
                    <p class="mt-5 font-display font-bold text-white">Couleur</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-for="c in palette" :key="c" @click="setAccent(c)" :style="{ background: c }"
                            class="h-8 w-8 rounded-full ring-2 transition" :class="accent?.toLowerCase()===c ? 'ring-white' : 'ring-transparent hover:ring-white/40'"></button>
                    </div>
                </div>
            </div>

            <!-- Aperçu live -->
            <div class="glass overflow-hidden rounded-2xl">
                <div class="flex items-center gap-2 border-b border-white/[0.06] px-4 py-3">
                    <span class="h-3 w-3 rounded-full bg-rose-400/70"></span><span class="h-3 w-3 rounded-full bg-amber-400/70"></span><span class="h-3 w-3 rounded-full bg-emerald-400/70"></span>
                    <span class="ml-3 truncate rounded-md bg-white/5 px-3 py-1 text-xs text-slate-400">{{ site.slug }}.joow.fr</span>
                </div>
                <div class="bg-white/[0.02] p-4" :class="device==='mobile' && 'flex justify-center'">
                    <iframe ref="iframe" :key="version" :src="`${site.live_url}?v=${version}`"
                        :class="device==='mobile' ? 'w-[390px] rounded-[2rem]' : 'w-full'"
                        class="h-[70vh] rounded-xl border border-white/5 bg-white" loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
