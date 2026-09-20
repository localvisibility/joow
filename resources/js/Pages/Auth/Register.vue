<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Créer un compte" />

        <h2 class="font-display text-2xl font-bold text-white">Créer un compte</h2>
        <p class="mt-1 text-sm text-slate-400">Gérez vos sites depuis un seul espace.</p>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">Nom</label>
                <input id="name" type="text" v-model="form.name" required autofocus autocomplete="name" class="field" placeholder="Votre nom" />
                <p v-if="form.errors.name" class="mt-1.5 text-sm text-rose-400">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input id="email" type="email" v-model="form.email" required autocomplete="username" class="field" placeholder="vous@exemple.fr" />
                <p v-if="form.errors.email" class="mt-1.5 text-sm text-rose-400">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">Mot de passe</label>
                <input id="password" type="password" v-model="form.password" required autocomplete="new-password" class="field" placeholder="••••••••" />
                <p v-if="form.errors.password" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">Confirmer le mot de passe</label>
                <input id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" class="field" placeholder="••••••••" />
                <p v-if="form.errors.password_confirmation" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password_confirmation }}</p>
            </div>

            <button type="submit" class="btn-brand w-full" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">
                {{ form.processing ? 'Création…' : 'Créer mon compte' }}
            </button>

            <p class="text-center text-sm text-slate-400">
                Déjà un compte ?
                <Link :href="route('login')" class="font-semibold text-brand-400 hover:text-brand-300">Se connecter</Link>
            </p>
        </form>
    </GuestLayout>
</template>
