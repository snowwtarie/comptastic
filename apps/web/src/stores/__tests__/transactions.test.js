import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useTransactionsStore } from '../transactions';
import { useDashboardStore } from '../dashboard';
import { apiFetch } from '../../lib/api';

vi.mock('../../lib/api', () => ({ apiFetch: vi.fn() }));

describe('transactions store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    apiFetch.mockReset();
  });

  it('clears the dashboard cache after creating a transaction', async () => {
    const dashboard = useDashboardStore();
    dashboard.byPeriod.current = { bars: [], categories: [] };
    apiFetch.mockResolvedValueOnce({ data: { id: 1 } });

    const store = useTransactionsStore();
    await store.create({ label: 'Test', amount: 10 });

    expect(dashboard.byPeriod).toEqual({});
  });

  it('clears the dashboard cache after removing a transaction', async () => {
    const dashboard = useDashboardStore();
    dashboard.byPeriod.current = { bars: [], categories: [] };
    apiFetch.mockResolvedValueOnce(null);

    const store = useTransactionsStore();
    store.items.push({ id: 1 });
    await store.remove(1);

    expect(store.items).toHaveLength(0);
    expect(dashboard.byPeriod).toEqual({});
  });

  it('cancelSeries removes only the unreconciled rows of that series and clears the dashboard cache', async () => {
    const dashboard = useDashboardStore();
    dashboard.byPeriod.current = { bars: [], categories: [] };
    apiFetch.mockResolvedValueOnce(null);

    const store = useTransactionsStore();
    store.items.push(
      { id: 1, series_id: 'abc', reconciled: true },
      { id: 2, series_id: 'abc', reconciled: false },
      { id: 3, series_id: 'other', reconciled: false }
    );

    await store.cancelSeries('abc');

    expect(apiFetch).toHaveBeenCalledWith('/api/transactions/series/abc', { method: 'DELETE' });
    expect(store.items.map((t) => t.id)).toEqual([1, 3]);
    expect(dashboard.byPeriod).toEqual({});
  });

  it('fetch stores the pagination meta and loadMore appends the next page', async () => {
    apiFetch.mockResolvedValueOnce({
      data: [{ id: 1 }],
      meta: { current_page: 1, last_page: 2 },
    });
    const store = useTransactionsStore();
    await store.fetch({ period: 'current' });

    expect(store.items).toHaveLength(1);
    expect(store.currentPage).toBe(1);
    expect(store.lastPage).toBe(2);

    apiFetch.mockResolvedValueOnce({
      data: [{ id: 2 }],
      meta: { current_page: 2, last_page: 2 },
    });
    await store.loadMore();

    expect(apiFetch).toHaveBeenLastCalledWith('/api/transactions?period=current&page=2');
    expect(store.items.map((t) => t.id)).toEqual([1, 2]);
    expect(store.currentPage).toBe(2);
  });

  it('loadMore is a no-op once the last page has been reached', async () => {
    apiFetch.mockResolvedValueOnce({
      data: [{ id: 1 }],
      meta: { current_page: 1, last_page: 1 },
    });
    const store = useTransactionsStore();
    await store.fetch({ period: 'current' });

    await store.loadMore();

    expect(apiFetch).toHaveBeenCalledTimes(1);
  });
});
