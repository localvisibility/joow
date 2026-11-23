<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
    set: { type: Boolean, default: false },
});

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout>
        <Head :title="set ? 'Choisir mon mot de passe' : 'Nouveau mot de passe'" />

        <h2 class="font-display text-2xl font-bold text-white">{{ set ? 'Choisir mon mot de passe' : 'Nouveau mot de passe' }}</h2>
        <p class="mt-1 text-sm text-slate-400">Au moins 8 caractères. Vous serez connecté directement ensuite.</p>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input id="email" type="email" v-model="form.email" required autocomplete="username" class="field" />
                <p v-if="form.errors.email" class="mt-1.5 text-sm text-rose-400">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">Nouveau mot de passe</label>
                <input id="password" type="password" v-model="form.password" required autofocus autocomplete="new-password" class="field" placeholder="••••••••" />
                <p v-if="form.errors.password" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">Confirmer le mot de passe</label>
                <input id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" class="field" placeholder="••••••••" />
                <p v-if="form.errors.password_confirmation" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password_confirmation }}</p>
            </div>
            <button type="submit" class="btn-brand w-full" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">
                {{ form.processing ? 'Enregistrement…' : 'Enregistrer mon mot de passe' }}
            </button>
            <p class="text-center text-sm"><Link :href="route('password.request')" class="text-brand-400 hover:text-brand-300">Lien expiré ? En recevoir un nouveau</Link></p>
        </form>
    </GuestLayout>
</template>
