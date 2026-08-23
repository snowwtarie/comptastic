import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../lib/api';

export const useCategoriesStore = defineStore('categories', () => {
  const items = ref([]);
  const loaded = ref(false);

  const byId = computed(() => Object.fromEntries(items.value.map((c) => [c.id, c])));
  const expense = computed(() => items.value.filter((c) => !c.is_income));

  async function fetch() {
    if (loaded.value) return;
    loaded.value = true;
    try {
      const res = await apiFetch('/api/categories');
      items.value = res.data;
    } catch (e) {
      loaded.value = false;
      throw e;
    }
  }

  return { items, byId, expense, fetch };
});
