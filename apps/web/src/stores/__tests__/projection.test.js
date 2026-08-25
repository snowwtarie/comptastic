import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useProjectionStore } from '../projection';
import { apiFetch } from '../../lib/api';

vi.mock('../../lib/api', () => ({ apiFetch: vi.fn() }));

describe('projection store stale-response guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    apiFetch.mockReset();
  });

  it('ignores a slow response that resolves after a newer request', async () => {
    const store = useProjectionStore();

    let resolveFirst;
    const firstPromise = new Promise((resolve) => {
      resolveFirst = resolve;
    });
    // horizon=12 fires first but is slow (e.g. a settings-update-triggered refetch)
    apiFetch.mockImplementationOnce(() => firstPromise);
    // horizon=24 fires right after but resolves immediately (e.g. a horizon dropdown change)
    apiFetch.mockImplementationOnce(() =>
      Promise.resolve({ data: { history: [], projection: Array(25).fill(2000) } })
    );

    const firstFetch = store.fetch(12);
    const secondFetch = store.fetch(24);

    await secondFetch;
    expect(store.projection).toHaveLength(25);
    expect(store.projection[0]).toBe(2000);

    resolveFirst({ data: { history: [], projection: Array(13).fill(1000) } });
    await firstFetch;

    // The stale horizon=12 response must not clobber the newer horizon=24 result,
    // which previously caused the "Montant final" tile to read an undefined index.
    expect(store.projection).toHaveLength(25);
    expect(store.projection[0]).toBe(2000);
  });
});
