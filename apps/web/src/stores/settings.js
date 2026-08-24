// apps/web/src/stores/settings.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useSettingsStore = defineStore('settings', () => {
  const income = ref(0);
  const monthlySavingsContribution = ref(0);
  const annualReturnRate = ref(0);
  const loaded = ref(false);

  function apply(data) {
    income.value = data.monthly_income;
    monthlySavingsContribution.value = data.monthly_savings_contribution;
    annualReturnRate.value = data.annual_return_rate;
  }

  async function fetch() {
    const res = await apiFetch('/api/settings');
    apply(res.data);
    loaded.value = true;
  }

  let updateChain = Promise.resolve();
  function update(partial) {
    updateChain = updateChain.then(() => doUpdate(partial), () => doUpdate(partial));
    return updateChain;
  }

  async function doUpdate(partial) {
    const res = await apiFetch('/api/settings', {
      method: 'PUT',
      body: {
        monthly_income: partial.income ?? income.value,
        monthly_savings_contribution: partial.monthlySavingsContribution ?? monthlySavingsContribution.value,
        annual_return_rate: partial.annualReturnRate ?? annualReturnRate.value,
      },
    });
    apply(res.data);
  }

  return { income, monthlySavingsContribution, annualReturnRate, loaded, fetch, update };
});
