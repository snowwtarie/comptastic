import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../lib/api';

export const ACCOUNT_TYPE_LABELS = { checking: 'Compte courant', savings: 'Épargne' };

export const useAccountsStore = defineStore('accounts', () => {
  const items = ref([]);
  const loaded = ref(false);

  const byId = computed(() => Object.fromEntries(items.value.map((a) => [a.id, a])));
  const savings = computed(() => items.value.filter((a) => a.type === 'savings'));

  async function fetch() {
    const res = await apiFetch('/api/accounts');
    items.value = res.data;
    loaded.value = true;
  }

  async function create({ name, bank, type, openingBalance }) {
    const res = await apiFetch('/api/accounts', {
      method: 'POST',
      body: { name, bank: bank || null, type, opening_balance: openingBalance },
    });
    items.value.push(res.data);
    return res.data;
  }

  return { items, byId, savings, loaded, fetch, create };
});
