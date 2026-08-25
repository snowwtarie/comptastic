<script setup>
import { reactive, ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth';
import { extractErrorMessage } from '../lib/api';

const authStore = useAuthStore();

const profileForm = reactive({ name: authStore.user?.name ?? '', email: authStore.user?.email ?? '' });
watch(
  () => authStore.user,
  (user) => {
    if (user) {
      profileForm.name = user.name;
      profileForm.email = user.email;
    }
  }
);

const profileError = ref('');
const profileSuccess = ref(false);
const savingProfile = ref(false);
async function submitProfile() {
  profileError.value = '';
  profileSuccess.value = false;
  savingProfile.value = true;
  try {
    await authStore.updateProfile({ name: profileForm.name, email: profileForm.email });
    profileSuccess.value = true;
  } catch (e) {
    profileError.value = extractErrorMessage(e);
  } finally {
    savingProfile.value = false;
  }
}

function blankPasswordForm() {
  return { currentPassword: '', password: '', passwordConfirmation: '' };
}
const passwordForm = reactive(blankPasswordForm());
const passwordError = ref('');
const passwordSuccess = ref(false);
const savingPassword = ref(false);
async function submitPassword() {
  passwordError.value = '';
  passwordSuccess.value = false;
  savingPassword.value = true;
  try {
    await authStore.updatePassword(passwordForm);
    Object.assign(passwordForm, blankPasswordForm());
    passwordSuccess.value = true;
  } catch (e) {
    passwordError.value = extractErrorMessage(e);
  } finally {
    savingPassword.value = false;
  }
}
</script>

<template>
  <main class="max-w-[560px] w-full mx-auto px-4 sm:px-8 pt-10 pb-14">
    <h1 class="m-0 mb-2 text-[28px] font-bold tracking-tight">Profil</h1>
    <p class="mt-0 mb-7 text-sm text-slate-500">Gérez vos informations personnelles et votre mot de passe.</p>

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm px-6 py-6 mb-6">
      <h2 class="text-base font-bold m-0 mb-4">Informations personnelles</h2>
      <div class="grid gap-4 mb-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nom complet</label>
          <input v-model="profileForm.name" type="text" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">E-mail</label>
          <input v-model="profileForm.email" type="email" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
      </div>
      <div v-if="profileError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ profileError }}</div>
      <div v-if="profileSuccess" class="bg-emerald-50 text-emerald-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">Profil mis à jour.</div>
      <button
        type="button"
        :disabled="savingProfile"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer disabled:opacity-60"
        @click="submitProfile"
      >Enregistrer</button>
    </section>

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm px-6 py-6">
      <h2 class="text-base font-bold m-0 mb-4">Mot de passe</h2>
      <div class="grid gap-4 mb-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Mot de passe actuel</label>
          <input v-model="passwordForm.currentPassword" type="password" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nouveau mot de passe</label>
          <input v-model="passwordForm.password" type="password" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Confirmer le nouveau mot de passe</label>
          <input v-model="passwordForm.passwordConfirmation" type="password" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
      </div>
      <div v-if="passwordError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ passwordError }}</div>
      <div v-if="passwordSuccess" class="bg-emerald-50 text-emerald-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">Mot de passe modifié.</div>
      <button
        type="button"
        :disabled="savingPassword"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer disabled:opacity-60"
        @click="submitPassword"
      >Modifier le mot de passe</button>
    </section>
  </main>
</template>
