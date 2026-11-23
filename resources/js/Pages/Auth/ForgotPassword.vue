<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: String });

const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <GuestLayout>
        <Head title="Mot de passe oublié" />

        <h2 class="font-display text-2xl font-bold text-white">Mot de passe oublié</h2>
        <p class="mt-1 text-sm text-slate-400">Indiquez votre adresse e-mail : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>

        <div v-if="status" class="mt-4 rounded-lg bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-300">{{ status }}</div>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" class="field" placeholder="vous@exemple.fr" />
                <p v-if="form.errors.email" class="mt-1.5 text-sm text-rose-400">{{ form.errors.email }}</p>
            </div>
            <button type="submit" class="btn-brand w-full" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">
                {{ form.processing ? 'Envoi…' : 'Recevoir le lien' }}
            </button>
            <p class="text-center text-xs text-slate-500">Pensez à regarder dans vos spams. Le lien est valable 60 minutes.</p>
            <p class="text-center text-sm"><Link :href="route('login')" class="text-brand-400 hover:text-brand-300">← Retour à la connexion</Link></p>
        </form>
    </GuestLayout>
</template>
