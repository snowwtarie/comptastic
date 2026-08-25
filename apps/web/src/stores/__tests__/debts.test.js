import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useDebtsStore } from '../debts';
import { apiFetch } from '../../lib/api';

vi.mock('../../lib/api', () => ({ apiFetch: vi.fn() }));

describe('debts store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    apiFetch.mockReset();
  });

  it('merges the remaining-amount override with the rest of the current debt on update', async () => {
    const store = useDebtsStore();
    store.items.push({
      id: 1,
      name: 'Prêt auto',
      original_amount: 18000,
      remaining_amount: 11200,
      monthly_payment: 320,
      rate: 3.9,
      end_date: '2029-06-15',
    });
    apiFetch.mockResolvedValueOnce({ data: { id: 1, remaining_amount: 0 } });

    await store.update(1, { remainingAmount: 0 });

    expect(apiFetch).toHaveBeenCalledWith('/api/debts/1', {
      method: 'PATCH',
      body: {
        name: 'Prêt auto',
        original_amount: 18000,
        remaining_amount: 0,
        monthly_payment: 320,
        rate: 3.9,
        end_date: '2029-06-15',
      },
    });
    expect(store.items[0]).toEqual({ id: 1, remaining_amount: 0 });
  });

  it('removes the matching debt after a successful delete', async () => {
    const store = useDebtsStore();
    store.items.push({ id: 1 }, { id: 2 });
    apiFetch.mockResolvedValueOnce(null);

    await store.remove(1);

    expect(apiFetch).toHaveBeenCalledWith('/api/debts/1', { method: 'DELETE' });
    expect(store.items.map((d) => d.id)).toEqual([2]);
  });
});
