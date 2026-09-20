<script setup>
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirming = ref(false);
const passwordInput = ref(null);

const form = useForm({ password: '' });

const confirmDeletion = () => {
    confirming.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirming.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-bold text-white">Supprimer le compte</h2>
            <p class="mt-1 text-sm text-slate-400">Une fois supprimé, toutes vos données seront définitivement effacées. Pensez à sauvegarder ce que vous souhaitez conserver.</p>
        </header>

        <button @click="confirmDeletion" class="mt-5 rounded-xl border border-rose-500/40 px-5 py-2.5 text-sm font-semibold text-rose-300 transition hover:bg-rose-500/10">Supprimer mon compte</button>

        <!-- Modal de confirmation -->
        <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
            <div v-if="confirming" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur" @click="closeModal">
                <div class="glass w-full max-w-md rounded-2xl bg-ink-800 p-6" @click.stop>
                    <h3 class="font-display text-lg font-bold text-white">Confirmer la suppression</h3>
                    <p class="mt-2 text-sm text-slate-400">Cette action est irréversible. Saisissez votre mot de passe pour confirmer la suppression définitive de votre compte.</p>
                    <input ref="passwordInput" v-model="form.password" type="password" placeholder="Mot de passe" class="field mt-5" @keyup.enter="deleteUser" />
                    <p v-if="form.errors.password" class="mt-1.5 text-sm text-rose-400">{{ form.errors.password }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button @click="closeModal" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:border-white/25">Annuler</button>
                        <button @click="deleteUser" :disabled="form.processing" class="rounded-xl bg-rose-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-600" :class="{ 'opacity-60': form.processing }">Supprimer définitivement</button>
                    </div>
                </div>
            </div>
        </Transition>
    </section>
</template>
