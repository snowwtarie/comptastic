import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../lib/api';

export const useDebtsStore = defineStore('debts', () => {
  const items = ref([]);

  const byId = computed(() => Object.fromEntries(items.value.map((d) => [d.id, d])));

  async function fetch() {
    const res = await apiFetch('/api/debts');
    items.value = res.data;
  }

  async function create({ name, originalAmount, remainingAmount, monthlyPayment, rate, endDate }) {
    const res = await apiFetch('/api/debts', {
      method: 'POST',
      body: {
        name,
        original_amount: originalAmount,
        remaining_amount: remainingAmount,
        monthly_payment: monthlyPayment,
        rate,
        end_date: endDate,
      },
    });
    items.value.push(res.data);
    return res.data;
  }

  return { items, byId, fetch, create };
});
