<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-bold text-white">Informations du profil</h2>
            <p class="mt-1 text-sm text-slate-400">Mettez à jour votre nom et votre adresse email.</p>
        </header>

        <form @submit.prevent="form.patch(route('profile.update'))" class="mt-6 space-y-5">
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">Nom</label>
                <input id="name" type="text" v-model="form.name" required autofocus autocomplete="name" class="field" />
                <p v-if="form.errors.name" class="mt-1.5 text-sm text-rose-400">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input id="email" type="email" v-model="form.email" required autocomplete="username" class="field" />
                <p v-if="form.errors.email" class="mt-1.5 text-sm text-rose-400">{{ form.errors.email }}</p>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-xl bg-amber-500/10 p-4 text-sm text-amber-200">
                Votre adresse email n'est pas vérifiée.
                <Link :href="route('verification.send')" method="post" as="button" class="font-semibold underline hover:text-white">Renvoyer l'email de vérification.</Link>
                <div v-show="status === 'verification-link-sent'" class="mt-2 font-medium text-emerald-300">Un nouveau lien de vérification a été envoyé.</div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="btn-brand" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">Enregistrer</button>
                <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm text-emerald-300">Enregistré ✓</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
