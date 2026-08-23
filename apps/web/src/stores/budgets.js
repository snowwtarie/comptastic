import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useBudgetsStore = defineStore('budgets', () => {
  const rows = ref([]); // [{ category_id, name, color_hex, budget, spent, pct, status }, ...]

  async function fetch(month = null) {
    const query = month ? `?month=${month}` : '';
    const res = await apiFetch(`/api/budgets${query}`);
    rows.value = res.data.map((row) => ({
      category_id: row.category_id,
      name: row.name,
      color_hex: row.color_hex,
      budget: row.budget_cents / 100,
      spent: row.spent_cents / 100,
      pct: row.pct,
      status: row.status,
    }));
  }

  async function update(categoryId, monthlyAmount) {
    const res = await apiFetch(`/api/budgets/${categoryId}`, {
      method: 'PUT',
      body: { monthly_amount: monthlyAmount },
    });
    const row = rows.value.find((r) => r.category_id === categoryId);
    if (row) row.budget = res.data.monthly_amount;
  }

  return { rows, fetch, update };
});
