// apps/web/src/stores/auth.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';
import { useDashboardStore } from './dashboard';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null); // { id, name, email } | null
  const status = ref('idle'); // 'idle' | 'loading' | 'ready'

  async function boot() {
    if (status.value !== 'idle') return;
    status.value = 'loading';
    try {
      const res = await apiFetch('/api/user');
      user.value = res.data;
    } catch {
      user.value = null;
    }
    status.value = 'ready';
  }

  async function login(email, password) {
    await apiFetch('/api/login', { method: 'POST', body: { email, password } });
    const res = await apiFetch('/api/user');
    user.value = res.data;
  }

  async function register({ name, email, password }) {
    const res = await apiFetch('/api/register', { method: 'POST', body: { name, email, password } });
    user.value = res.data;
  }

  async function logout() {
    await apiFetch('/api/logout', { method: 'POST' });
    clear();
  }

  function clear() {
    user.value = null;
    useDashboardStore().clearCache();
  }

  return { user, status, boot, login, register, logout, clear };
});
