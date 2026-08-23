// apps/web/src/stores/transactions.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useTransactionsStore = defineStore('transactions', () => {
  const items = ref([]);

  async function fetch({ period = 'current', accountId = null } = {}) {
    const params = new URLSearchParams({ period });
    if (accountId) params.set('account_id', accountId);
    const res = await apiFetch(`/api/transactions?${params.toString()}`);
    items.value = res.data;
  }

  async function create(payload) {
    const res = await apiFetch('/api/transactions', { method: 'POST', body: payload });
    return res.data;
  }

  async function toggleReconciled(id) {
    const t = items.value.find((x) => x.id === id);
    if (!t) return;
    const res = await apiFetch(`/api/transactions/${id}`, {
      method: 'PATCH',
      body: { reconciled: !t.reconciled },
    });
    Object.assign(t, res.data);
  }

  async function remove(id) {
    await apiFetch(`/api/transactions/${id}`, { method: 'DELETE' });
    items.value = items.value.filter((t) => t.id !== id);
  }

  return { items, fetch, create, toggleReconciled, remove };
});
