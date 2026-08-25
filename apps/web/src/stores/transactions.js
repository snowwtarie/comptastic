// apps/web/src/stores/transactions.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';
import { useDashboardStore } from './dashboard';

export const useTransactionsStore = defineStore('transactions', () => {
  const items = ref([]);
  const currentPage = ref(1);
  const lastPage = ref(1);
  let currentFilters = { period: 'current', accountId: null };

  async function fetch({ period = 'current', accountId = null } = {}) {
    currentFilters = { period, accountId };
    const params = new URLSearchParams({ period });
    if (accountId) params.set('account_id', accountId);
    const res = await apiFetch(`/api/transactions?${params.toString()}`);
    items.value = res.data;
    currentPage.value = res.meta?.current_page ?? 1;
    lastPage.value = res.meta?.last_page ?? 1;
  }

  async function loadMore() {
    if (currentPage.value >= lastPage.value) return;
    const params = new URLSearchParams({ period: currentFilters.period, page: currentPage.value + 1 });
    if (currentFilters.accountId) params.set('account_id', currentFilters.accountId);
    const res = await apiFetch(`/api/transactions?${params.toString()}`);
    items.value = [...items.value, ...res.data];
    currentPage.value = res.meta?.current_page ?? currentPage.value;
    lastPage.value = res.meta?.last_page ?? lastPage.value;
  }

  async function create(payload) {
    const res = await apiFetch('/api/transactions', { method: 'POST', body: payload });
    useDashboardStore().clearCache();
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
    useDashboardStore().clearCache();
  }

  async function cancelSeries(seriesId) {
    await apiFetch(`/api/transactions/series/${seriesId}`, { method: 'DELETE' });
    items.value = items.value.filter((t) => !(t.series_id === seriesId && !t.reconciled));
    useDashboardStore().clearCache();
  }

  return { items, currentPage, lastPage, fetch, loadMore, create, toggleReconciled, remove, cancelSeries };
});
