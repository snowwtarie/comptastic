import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../lib/api';

export const useCategoriesStore = defineStore('categories', () => {
  const items = ref([]);
  const loaded = ref(false);

  const byId = computed(() => Object.fromEntries(items.value.map((c) => [c.id, c])));
  const expense = computed(() => items.value.filter((c) => !c.is_income));

  let inflight = null;

  function fetch() {
    if (loaded.value) return Promise.resolve();
    if (inflight) return inflight;
    inflight = (async () => {
      try {
        const res = await apiFetch('/api/categories');
        items.value = res.data;
        loaded.value = true;
      } finally {
        inflight = null;
      }
    })();
    return inflight;
  }

  return { items, byId, expense, fetch };
});
