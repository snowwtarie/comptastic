import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useProjectionStore = defineStore('projection', () => {
  const history = ref([]); // [{month_offset, balance}, ...]
  const projection = ref([]); // [balance, ...] index 0 = today

  async function fetch(horizon) {
    const res = await apiFetch(`/api/savings-projection?horizon=${horizon}`);
    history.value = res.data.history;
    projection.value = res.data.projection;
  }

  return { history, projection, fetch };
});
