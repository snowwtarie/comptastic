import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '../auth';
import { apiFetch } from '../../lib/api';

vi.mock('../../lib/api', () => ({ apiFetch: vi.fn(), resetCsrf: vi.fn() }));

describe('auth store boot race', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    apiFetch.mockReset();
  });

  it('shares one in-flight boot() across concurrent callers instead of resolving early with no user', async () => {
    const store = useAuthStore();
    let resolve;
    apiFetch.mockImplementationOnce(
      () =>
        new Promise((r) => {
          resolve = r;
        })
    );

    // First navigation guard starts boot().
    const firstBoot = store.boot();
    expect(store.status).toBe('loading');

    // A second navigation fires before the /api/user request resolves. The old
    // code returned undefined here immediately, leaving auth.user null and
    // bouncing an authenticated user to /login.
    const secondBoot = store.boot();

    resolve({ data: { id: 1, name: 'Demo', email: 'demo@test.fr' } });
    await firstBoot;
    await secondBoot;

    expect(apiFetch).toHaveBeenCalledTimes(1);
    expect(store.status).toBe('ready');
    expect(store.user).toEqual({ id: 1, name: 'Demo', email: 'demo@test.fr' });
  });

  it('does not reboot once already ready', async () => {
    const store = useAuthStore();
    apiFetch.mockResolvedValueOnce({ data: { id: 1, name: 'Demo', email: 'demo@test.fr' } });

    await store.boot();
    await store.boot();

    expect(apiFetch).toHaveBeenCalledTimes(1);
  });
});
