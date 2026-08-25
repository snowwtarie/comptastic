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

  async function update(id, { remainingAmount }) {
    const current = items.value.find((d) => d.id === id);
    if (!current) return;
    const res = await apiFetch(`/api/debts/${id}`, {
      method: 'PATCH',
      body: {
        name: current.name,
        original_amount: current.original_amount,
        remaining_amount: remainingAmount,
        monthly_payment: current.monthly_payment,
        rate: current.rate,
        end_date: current.end_date,
      },
    });
    const idx = items.value.findIndex((d) => d.id === id);
    if (idx !== -1) items.value[idx] = res.data;
  }

  async function remove(id) {
    await apiFetch(`/api/debts/${id}`, { method: 'DELETE' });
    items.value = items.value.filter((d) => d.id !== id);
  }

  return { items, byId, fetch, create, update, remove };
});
