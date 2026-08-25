// apps/web/src/stores/auth.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch, resetCsrf } from '../lib/api';
import { useDashboardStore } from './dashboard';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null); // { id, name, email } | null
  const status = ref('idle'); // 'idle' | 'loading' | 'ready'
  let bootPromise = null;

  function boot() {
    if (status.value === 'ready') return Promise.resolve();
    if (bootPromise) return bootPromise;
    status.value = 'loading';
    bootPromise = (async () => {
      try {
        const res = await apiFetch('/api/user');
        user.value = res.data;
      } catch {
        user.value = null;
      }
      status.value = 'ready';
      bootPromise = null;
    })();
    return bootPromise;
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

  async function updateProfile({ name, email }) {
    const res = await apiFetch('/api/profile', { method: 'PATCH', body: { name, email } });
    user.value = res.data;
  }

  async function updatePassword({ currentPassword, password, passwordConfirmation }) {
    await apiFetch('/api/password', {
      method: 'PUT',
      body: { current_password: currentPassword, password, password_confirmation: passwordConfirmation },
    });
  }

  async function logout() {
    await apiFetch('/api/logout', { method: 'POST' });
    clear();
  }

  function clear() {
    user.value = null;
    resetCsrf();
    useDashboardStore().clearCache();
  }

  return { user, status, boot, login, register, updateProfile, updatePassword, logout, clear };
});
