<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-bold text-white">Mot de passe</h2>
            <p class="mt-1 text-sm text-slate-400">Utilisez un mot de passe long et unique pour sécuriser votre compte.</p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-5">
            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-300">Mot de passe actuel</label>
                <input id="current_password" ref="currentPasswordInput" v-model="form.current_password" type="password" autocomplete="current-password" class="field" />
                <p v-if="form.errors.current_password" class="mt-1.5 text-sm text-rose-400">{{ form.errors.current_password }}</p>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">Nouveau mot de passe</label>
                <input id="password" ref="passwordInput" v-model="form.password" type="password" autocomplete="new-password" class="field" />
                <p v-if="form.errors.password" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">Confirmer le mot de passe</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="field" />
                <p v-if="form.errors.password_confirmation" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password_confirmation }}</p>
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
