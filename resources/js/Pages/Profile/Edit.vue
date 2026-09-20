<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});
</script>

<template>
    <Head title="Paramètres" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="font-display text-3xl font-bold tracking-tight text-white">Paramètres</h1>
                <p class="mt-1 text-sm text-slate-400">Gérez votre compte, votre mot de passe et vos préférences.</p>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <div class="glass rounded-2xl p-6 sm:p-8">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </div>
            <div class="glass rounded-2xl p-6 sm:p-8">
                <UpdatePasswordForm />
            </div>
            <div class="glass rounded-2xl border-rose-500/15 p-6 sm:p-8">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
