<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: Boolean,
    canGoogle: Boolean,
    status: String,
});

// Par défaut : lien de connexion par email (sans mot de passe). Le mot de passe reste possible.
const mode = ref('magic');
const magic = useForm({ email: '' });
const sendMagic = () => magic.post(route('magic.send'), { preserveScroll: true });

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <h2 class="font-display text-2xl font-bold text-white">Connexion</h2>
        <p class="mt-1 text-sm text-slate-400">Retrouvez vos sites, vos demandes et votre Studio.</p>

        <div v-if="status" class="mt-4 rounded-lg bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-300">{{ status }}</div>

        <a v-if="canGoogle" :href="route('google.redirect')" class="mt-6 flex w-full items-center justify-center gap-3 rounded-xl border border-white/15 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">
            <svg class="h-5 w-5" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.5l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.3l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-2.8-.4-4H24v8.1h12.7c-.3 2.1-1.7 5.3-4.8 7.4l7.4 5.7c4.4-4.1 7.2-10.1 7.2-17.2z"/><path fill="#FBBC05" d="M10.5 28.6A14.5 14.5 0 0 1 9.7 24c0-1.6.3-3.2.8-4.6l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.4-5.7c-2 1.4-4.7 2.4-8.5 2.4-6.3 0-11.6-4.1-13.5-9.9l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
            Continuer avec Google
        </a>
        <div v-if="canGoogle" class="my-5 flex items-center gap-3 text-xs text-slate-500"><span class="h-px flex-1 bg-white/10"></span>ou<span class="h-px flex-1 bg-white/10"></span></div>

        <div class="mt-5 flex gap-1 rounded-xl bg-white/[0.04] p-1 text-sm font-semibold">
            <button type="button" @click="mode='magic'" class="flex-1 rounded-lg px-3 py-2 transition" :class="mode==='magic' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'">✉️ Lien par email</button>
            <button type="button" @click="mode='password'" class="flex-1 rounded-lg px-3 py-2 transition" :class="mode==='password' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'">🔑 Mot de passe</button>
        </div>

        <!-- Lien magique -->
        <form v-if="mode==='magic'" @submit.prevent="sendMagic" class="mt-5 space-y-4">
            <div>
                <label for="magic-email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input id="magic-email" type="email" v-model="magic.email" required autofocus autocomplete="email" class="field" placeholder="vous@exemple.fr" />
                <p v-if="magic.errors.email" class="mt-1.5 text-sm text-rose-400">{{ magic.errors.email }}</p>
            </div>
            <button type="submit" class="btn-brand w-full" :class="{ 'opacity-60': magic.processing }" :disabled="magic.processing">
                {{ magic.processing ? 'Envoi…' : 'Recevoir mon lien de connexion' }}
            </button>
            <p class="text-center text-xs text-slate-500">Pas de mot de passe à retenir. Le lien est valable 30 minutes. Pas encore de compte ? Il sera créé automatiquement.</p>
        </form>

        <!-- Mot de passe -->
        <form v-else @submit.prevent="submit" class="mt-5 space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input id="email" type="email" v-model="form.email" required autocomplete="username" class="field" placeholder="vous@exemple.fr" />
                <p v-if="form.errors.email" class="mt-1.5 text-sm text-rose-400">{{ form.errors.email }}</p>
            </div>
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="text-sm font-medium text-slate-300">Mot de passe</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-brand-400 hover:text-brand-300">Oublié ?</Link>
                </div>
                <input id="password" type="password" v-model="form.password" required autocomplete="current-password" class="field" placeholder="••••••••" />
                <p v-if="form.errors.password" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password }}</p>
            </div>
            <label class="flex items-center gap-2.5 text-sm text-slate-400">
                <input type="checkbox" v-model="form.remember" class="h-4 w-4 rounded border-white/20 bg-white/5 text-brand-500 focus:ring-brand-500/40" />
                Se souvenir de moi
            </label>
            <button type="submit" class="btn-brand w-full" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">
                {{ form.processing ? 'Connexion…' : 'Se connecter' }}
            </button>
        </form>
    </GuestLayout>
</template>
