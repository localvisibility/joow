<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <h2 class="font-display text-2xl font-bold text-white">Connexion</h2>
        <p class="mt-1 text-sm text-slate-400">Accédez à votre espace.</p>

        <div v-if="status" class="mt-4 rounded-lg bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-300">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" class="field" placeholder="vous@exemple.fr" />
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
