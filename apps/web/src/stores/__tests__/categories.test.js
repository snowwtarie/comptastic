import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useCategoriesStore } from '../categories';
import { apiFetch } from '../../lib/api';

vi.mock('../../lib/api', () => ({ apiFetch: vi.fn() }));

describe('categories store in-flight dedup', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    apiFetch.mockReset();
  });

  it('shares one in-flight request across concurrent callers instead of one getting an empty list', async () => {
    const store = useCategoriesStore();
    let resolve;
    apiFetch.mockImplementationOnce(
      () =>
        new Promise((r) => {
          resolve = r;
        })
    );

    // Two views mounting in the same tick, e.g. Dashboard and Transactions.
    const call1 = store.fetch();
    const call2 = store.fetch();

    resolve({ data: [{ id: 1, name: 'Alimentation', is_income: false }] });
    await call1;
    await call2;

    expect(apiFetch).toHaveBeenCalledTimes(1);
    // Without dedup, the second caller would have returned immediately with items
    // still [], leaving TransactionsView's default category permanently null.
    expect(store.items).toHaveLength(1);
    expect(store.expense).toHaveLength(1);
  });

  it('does not refetch once already loaded', async () => {
    const store = useCategoriesStore();
    apiFetch.mockResolvedValueOnce({ data: [{ id: 1, name: 'Alimentation', is_income: false }] });

    await store.fetch();
    await store.fetch();

    expect(apiFetch).toHaveBeenCalledTimes(1);
  });
});
