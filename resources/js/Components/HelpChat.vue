<script setup>
import { Link } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

/* Assistant d'aide Joow (questions sur le Studio, la publication, le domaine, la facturation…). */
const props = defineProps({ compact: Boolean });
const emit = defineEmits(['contact']);

const KEY = 'joow-help-chat';
const load = () => { try { return JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch { return []; } };
const msgs = ref(load());
const save = () => { try { sessionStorage.setItem(KEY, JSON.stringify(msgs.value.slice(-30))); } catch {} };
const input = ref('');
const busy = ref(false);
const box = ref(null);
const SUGGEST = ['Comment modifier un texte ou une photo ?', 'Comment mettre mon site en ligne ?', 'Comment avoir mon propre nom de domaine ?', 'Comment fonctionnent les crédits IA ?', 'Comment résilier mon abonnement ?'];

const cookie = (n) => document.cookie.split('; ').find((c) => c.startsWith(n + '='))?.split('=')[1];
const post = (url, body) => fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie('XSRF-TOKEN') || ''), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
const scroll = () => nextTick(() => { if (box.value) box.value.scrollTop = box.value.scrollHeight; });

const ask = async (q = null) => {
    const text = (q ?? input.value).trim();
    if (!text || busy.value) return;
    const history = msgs.value.map((m) => ({ role: m.role, text: m.text }));
    msgs.value.push({ role: 'user', text }); input.value = ''; busy.value = true; scroll();
    try {
        const r = await post(route('support.ask'), { message: text, history });
        const d = await r.json();
        if (!r.ok) throw new Error(d.message || '');
        msgs.value.push({ role: 'ai', text: d.answer, escalate: d.escalate, id: d.id });
    } catch {
        msgs.value.push({ role: 'ai', text: 'Je n\'arrive pas à répondre pour le moment. Vous pouvez écrire à l\'équipe, nous répondons sous 24 h ouvrées.', escalate: true });
    } finally { busy.value = false; save(); scroll(); }
};
const rate = async (m, helpful) => { if (!m.id || m.rated) return; m.rated = helpful ? 'up' : 'down'; save(); try { await post(route('support.feedback', m.id), { helpful }); } catch {} if (!helpful) m.escalate = true; };
const reset = () => { msgs.value = []; save(); };

// Markdown minimal et sûr : échappement puis **gras**, listes, retours à la ligne
const md = (t) => {
    const esc = String(t || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return esc.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/^\s*[-•]\s+(.*)$/gm, '<span class="block pl-3">• $1</span>').replace(/^\s*(\d+)\.\s+(.*)$/gm, '<span class="block pl-3">$1. $2</span>').replace(/\n/g, '<br>').replace(/<\/span><br>/g, '</span>').replace(/(<br>){3,}/g, '<br><br>');
};
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div ref="box" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4" :class="compact ? 'max-h-[55vh]' : 'max-h-[60vh] min-h-[320px]'">
            <div class="rounded-2xl bg-white/[0.05] px-4 py-3 text-sm text-slate-200">
                Bonjour 👋 Je suis l'assistant Joow. Posez-moi vos questions sur le Studio, la mise en ligne, votre nom de domaine, vos factures, les modules…
            </div>
            <div v-if="!msgs.length" class="flex flex-wrap gap-2">
                <button v-for="s in SUGGEST" :key="s" @click="ask(s)" class="rounded-full border border-white/10 px-3 py-1.5 text-left text-xs text-slate-300 transition hover:border-brand-400/50 hover:text-white">{{ s }}</button>
            </div>
            <div v-for="(m, i) in msgs" :key="i" :class="m.role === 'user' ? 'ml-8 flex justify-end' : 'mr-4'">
                <div v-if="m.role === 'user'" class="rounded-2xl bg-brand-500/25 px-4 py-2.5 text-sm text-white">{{ m.text }}</div>
                <div v-else class="rounded-2xl bg-white/[0.05] px-4 py-3 text-sm leading-relaxed text-slate-200">
                    <div v-html="md(m.text)"></div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <template v-if="m.id">
                            <button @click="rate(m, true)" :class="m.rated === 'up' ? 'text-emerald-300' : 'text-slate-500 hover:text-white'" title="Utile">👍</button>
                            <button @click="rate(m, false)" :class="m.rated === 'down' ? 'text-rose-300' : 'text-slate-500 hover:text-white'" title="Pas utile">👎</button>
                        </template>
                        <button v-if="m.escalate" @click="emit('contact', msgs.filter((x) => x.role === 'user').map((x) => x.text).slice(-1)[0])" class="ml-auto rounded-lg bg-brand-500/20 px-2.5 py-1 font-semibold text-brand-200 hover:bg-brand-500/30">✉ Écrire à l'équipe</button>
                    </div>
                </div>
            </div>
            <div v-if="busy" class="mr-4 inline-flex gap-1 rounded-2xl bg-white/[0.05] px-4 py-3"><span class="h-2 w-2 animate-bounce rounded-full bg-slate-400"></span><span class="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:.15s]"></span><span class="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:.3s]"></span></div>
        </div>
        <form @submit.prevent="ask()" class="flex gap-2 border-t border-white/[0.06] p-3">
            <input v-model="input" maxlength="1000" class="flex-1 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-brand-400 focus:ring-0" placeholder="Votre question…" />
            <button :disabled="busy || !input.trim()" class="btn-brand !px-4 text-sm">Envoyer</button>
        </form>
        <div v-if="compact" class="flex items-center justify-between px-4 pb-3 text-xs text-slate-500">
            <button v-if="msgs.length" @click="reset" class="hover:text-white">Nouvelle conversation</button><span v-else></span>
            <Link :href="route('support.index')" class="hover:text-white">Centre d'aide →</Link>
        </div>
    </div>
</template>
