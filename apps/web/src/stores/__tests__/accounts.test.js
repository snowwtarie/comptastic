import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAccountsStore } from '../accounts';
import { apiFetch } from '../../lib/api';

vi.mock('../../lib/api', () => ({ apiFetch: vi.fn() }));

describe('accounts store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    apiFetch.mockReset();
  });

  it('replaces the matching item in place on update', async () => {
    const store = useAccountsStore();
    store.items.push({ id: 1, name: 'Old name', bank: 'Old bank', type: 'checking' });
    store.items.push({ id: 2, name: 'Other', bank: null, type: 'savings' });

    apiFetch.mockResolvedValueOnce({ data: { id: 1, name: 'New name', bank: 'New bank', type: 'checking' } });

    await store.update(1, { name: 'New name', bank: 'New bank', type: 'checking', openingBalance: 0 });

    expect(store.items).toHaveLength(2);
    expect(store.items[0]).toEqual({ id: 1, name: 'New name', bank: 'New bank', type: 'checking' });
    expect(store.items[1].id).toBe(2);
  });

  it('removes the matching item after a successful delete', async () => {
    const store = useAccountsStore();
    store.items.push({ id: 1, name: 'A' }, { id: 2, name: 'B' });
    apiFetch.mockResolvedValueOnce(null);

    await store.remove(1);

    expect(apiFetch).toHaveBeenCalledWith('/api/accounts/1', { method: 'DELETE' });
    expect(store.items.map((a) => a.id)).toEqual([2]);
  });

  it('leaves items untouched when the delete is rejected (e.g. account has transactions)', async () => {
    const store = useAccountsStore();
    store.items.push({ id: 1, name: 'A' });
    apiFetch.mockRejectedValueOnce(new Error('422'));

    await expect(store.remove(1)).rejects.toThrow();
    expect(store.items).toHaveLength(1);
  });
});
