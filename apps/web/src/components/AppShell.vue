<script setup>
import { useRoute, useRouter } from 'vue-router';
import Icon from './Icon.vue';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const NAV_ITEMS = [
  { to: '/dashboard', icon: 'home', label: 'Tableau de bord', mobileLabel: 'Accueil' },
  { to: '/transactions', icon: 'swap', label: 'Transactions', mobileLabel: 'Trans.' },
  { to: '/budgets', icon: 'pie', label: 'Budgets', mobileLabel: 'Budgets' },
  { to: '/comptes', icon: 'wallet', label: 'Comptes', mobileLabel: 'Comptes' },
  { to: '/dettes', icon: 'debt', label: 'Dettes', desktopOnly: true },
  { to: '/projection', icon: 'projection', label: 'Projection', mobileLabel: 'Projet.' },
];
const mobileItems = NAV_ITEMS.filter((i) => !i.desktopOnly);

function isActive(to) {
  return route.path === to;
}

function openNewTransaction() {
  router.push({ path: '/transactions', query: { new: String(Date.now()) } });
}

async function logout() {
  await authStore.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <nav class="only-desktop items-center gap-8 px-8 py-4 bg-white border-b border-slate-200 flex">
      <router-link to="/dashboard" class="text-lg font-extrabold tracking-tight text-slate-900 mr-2">
        Comptastic
      </router-link>
      <router-link
        v-for="item in NAV_ITEMS"
        :key="item.to"
        :to="item.to"
        class="inline-flex items-center gap-1.5 text-sm"
        :class="isActive(item.to) ? 'font-semibold text-indigo-600' : 'font-medium text-slate-600 hover:text-slate-900'"
      >
        <Icon :name="item.icon" />{{ item.label }}
      </router-link>
      <button
        type="button"
        class="ml-auto inline-flex items-center gap-1.5 bg-transparent text-slate-500 hover:text-slate-900 text-sm font-medium cursor-pointer"
        @click="logout"
      >
        Déconnexion
      </button>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm cursor-pointer"
        @click="openNewTransaction"
      >
        <Icon name="plus" :stroke-width="2" />Nouvelle transaction
      </button>
    </nav>

    <main class="flex-1 flex flex-col">
      <router-view />
    </main>

    <nav class="only-mobile relative justify-around items-center h-16 bg-white border-t border-slate-200 shrink-0 flex">
      <router-link
        v-for="item in mobileItems"
        :key="item.to"
        :to="item.to"
        class="flex flex-col items-center gap-0.5"
        :class="isActive(item.to) ? 'text-indigo-600' : 'text-slate-400'"
      >
        <Icon :name="item.icon" :size="20" />
        <span class="text-[10px]" :class="isActive(item.to) ? 'font-semibold' : 'font-medium'">{{ item.mobileLabel }}</span>
      </router-link>
      <button
        type="button"
        class="absolute right-4 -top-6 w-[52px] h-[52px] rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-[0_8px_16px_rgba(79,70,229,0.35)] cursor-pointer"
        aria-label="Nouvelle transaction"
        @click="openNewTransaction"
      >
        <Icon name="plus" :size="22" :stroke-width="2.2" />
      </button>
    </nav>
  </div>
</template>
