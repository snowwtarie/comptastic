<script setup>
import { reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useIsMobile } from '../lib/useIsMobile';
import { useAuthStore } from '../stores/auth';
import { ApiError } from '../lib/api';

const router = useRouter();
const isMobile = useIsMobile();
const authStore = useAuthStore();

const mode = ref('login');
const form = reactive({ name: '', email: '', password: '', confirmPassword: '' });
const error = ref('');
const submitting = ref(false);

const isLogin = computed(() => mode.value === 'login');
const isSignup = computed(() => mode.value === 'signup');
const title = computed(() => (isLogin.value ? 'Connectez-vous à votre compte' : 'Créez votre compte'));
const submitLabel = computed(() => (isLogin.value ? 'Se connecter' : "S'inscrire"));
const switchPrompt = computed(() => (isLogin.value ? 'Pas encore de compte ?' : 'Déjà un compte ?'));
const switchLabel = computed(() => (isLogin.value ? "S'inscrire" : 'Se connecter'));

function setMode(next) {
  mode.value = next;
  error.value = '';
}
function switchMode() {
  setMode(isLogin.value ? 'signup' : 'login');
}

function validEmail(v) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

async function submit() {
  if (!validEmail(form.email)) {
    error.value = 'Adresse e-mail invalide.';
    return;
  }
  if (!form.password || form.password.length < 6) {
    error.value = 'Le mot de passe doit contenir au moins 6 caractères.';
    return;
  }
  if (isSignup.value) {
    if (!form.name) {
      error.value = 'Merci de renseigner votre nom.';
      return;
    }
    if (form.password !== form.confirmPassword) {
      error.value = 'Les mots de passe ne correspondent pas.';
      return;
    }
  }

  error.value = '';
  submitting.value = true;
  try {
    if (isSignup.value) {
      await authStore.register({ name: form.name, email: form.email, password: form.password });
    } else {
      await authStore.login(form.email, form.password);
    }
    router.push('/dashboard');
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = e.errors ? Object.values(e.errors).flat()[0] : e.message;
    } else {
      error.value = 'Une erreur est survenue.';
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <!-- Mobile: compact card, placeholder-only fields -->
  <div v-if="isMobile" class="min-h-screen bg-slate-200 flex justify-center items-start px-3 py-8">
    <div class="w-full max-w-[380px] flex flex-col justify-center">
      <div class="text-center mb-7">
        <span class="text-xl font-extrabold tracking-tight">Comptastic</span>
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-6">
        <div class="flex border border-slate-200 rounded-[10px] overflow-hidden mb-5">
          <button
            type="button"
            class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer"
            :class="isLogin ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'"
            @click="setMode('login')"
          >Connexion</button>
          <button
            type="button"
            class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer"
            :class="isSignup ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'"
            @click="setMode('signup')"
          >Inscription</button>
        </div>

        <h1 class="m-0 mb-4 text-[17px] font-bold tracking-tight">{{ title }}</h1>

        <div class="grid gap-3 mb-2">
          <input
            v-if="isSignup"
            type="text"
            v-model="form.name"
            placeholder="Nom complet"
            class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]"
          />
          <input
            type="email"
            v-model="form.email"
            placeholder="E-mail"
            class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]"
          />
          <input
            type="password"
            v-model="form.password"
            placeholder="Mot de passe"
            class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]"
          />
          <input
            v-if="isSignup"
            type="password"
            v-model="form.confirmPassword"
            placeholder="Confirmer le mot de passe"
            class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]"
          />
        </div>

        <div v-if="isLogin" class="flex justify-end mb-4">
          <a href="#" class="text-xs font-semibold text-indigo-600">Mot de passe oublié ?</a>
        </div>
        <div v-else class="mb-4"></div>

        <div v-if="error" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">
          {{ error }}
        </div>

        <button
          type="button"
          :disabled="submitting"
          class="w-full bg-indigo-600 text-white rounded-[10px] py-3.5 text-sm font-bold cursor-pointer disabled:opacity-60"
          @click="submit"
        >{{ submitLabel }}</button>

        <p class="text-center text-xs text-slate-500 mt-4 mb-0">
          {{ switchPrompt }}
          <a href="#" class="font-bold text-indigo-600" @click.prevent="switchMode">{{ switchLabel }}</a>
        </p>
      </div>
    </div>
  </div>

  <!-- Desktop: labeled fields, wider card -->
  <div v-else class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <div class="w-full max-w-[420px]">
      <div class="flex items-center justify-center gap-2 mb-7">
        <span class="text-xl font-extrabold tracking-tight">Comptastic</span>
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
        <div class="flex border border-slate-200 rounded-lg overflow-hidden mb-6">
          <button
            type="button"
            class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer"
            :class="isLogin ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
            @click="setMode('login')"
          >Connexion</button>
          <button
            type="button"
            class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer"
            :class="isSignup ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
            @click="setMode('signup')"
          >Inscription</button>
        </div>

        <h1 class="m-0 mb-5 text-xl font-bold tracking-tight">{{ title }}</h1>

        <div class="grid gap-4 mb-2">
          <div v-if="isSignup">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nom complet</label>
            <input type="text" v-model="form.name" placeholder="Jeanne Martin" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">E-mail</label>
            <input type="email" v-model="form.email" placeholder="vous@exemple.fr" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Mot de passe</label>
            <input type="password" v-model="form.password" placeholder="••••••••" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
          <div v-if="isSignup">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Confirmer le mot de passe</label>
            <input type="password" v-model="form.confirmPassword" placeholder="••••••••" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
        </div>

        <div v-if="isLogin" class="flex justify-end mb-5">
          <a href="#" class="text-[13px] font-semibold">Mot de passe oublié ?</a>
        </div>
        <div v-else class="mb-5"></div>

        <div v-if="error" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">
          {{ error }}
        </div>

        <button
          type="button"
          :disabled="submitting"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-3 text-sm font-semibold shadow-sm cursor-pointer disabled:opacity-60"
          @click="submit"
        >{{ submitLabel }}</button>

        <p class="text-center text-[13px] text-slate-500 mt-5 mb-0">
          {{ switchPrompt }}
          <a href="#" class="font-semibold" @click.prevent="switchMode">{{ switchLabel }}</a>
        </p>
      </div>
    </div>
  </div>
</template>
