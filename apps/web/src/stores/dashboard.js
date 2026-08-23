import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useDashboardStore = defineStore('dashboard', () => {
  const byPeriod = ref({}); // { current: {bars, categories}, previous: {...}, year: {...} }

  async function fetch(period) {
    if (byPeriod.value[period]) return byPeriod.value[period];
    const res = await apiFetch(`/api/dashboard/summary?period=${period}`);
    byPeriod.value = { ...byPeriod.value, [period]: res.data };
    return res.data;
  }

  return { byPeriod, fetch };
});
