import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSettingsStore } from '../settings';
import { apiFetch } from '../../lib/api';

vi.mock('../../lib/api', () => ({ apiFetch: vi.fn() }));

describe('settings store update serialization', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    apiFetch.mockReset();
  });

  it('serializes concurrent updates so the second call sees the first result, not stale local state', async () => {
    const store = useSettingsStore();
    store.income = 1000;
    store.monthlySavingsContribution = 200;
    store.annualReturnRate = 3;

    let resolveFirst;
    const firstPromise = new Promise((resolve) => {
      resolveFirst = resolve;
    });
    apiFetch.mockImplementationOnce(() => firstPromise);
    apiFetch.mockImplementationOnce((path, opts) =>
      Promise.resolve({
        data: {
          monthly_income: opts.body.monthly_income,
          monthly_savings_contribution: opts.body.monthly_savings_contribution,
          annual_return_rate: opts.body.annual_return_rate,
        },
      })
    );

    // Two callers firing back-to-back, e.g. BudgetsView editing income while
    // also editing a category budget in the same tick.
    const firstCall = store.update({ income: 5000 });
    const secondCall = store.update({ monthlySavingsContribution: 999 });

    // Let the queued microtasks run so the first update actually starts.
    await Promise.resolve();
    await Promise.resolve();

    // The second update must not hit the API until the first has resolved and applied.
    expect(apiFetch).toHaveBeenCalledTimes(1);

    resolveFirst({
      data: { monthly_income: 5000, monthly_savings_contribution: 200, annual_return_rate: 3 },
    });
    await firstCall;
    await secondCall;

    expect(apiFetch).toHaveBeenCalledTimes(2);
    const secondCallBody = apiFetch.mock.calls[1][1].body;
    // Without serialization this would read the pre-first-response value (1000)
    // instead of the value the first call just applied (5000), silently reverting it.
    expect(secondCallBody.monthly_income).toBe(5000);
    expect(secondCallBody.monthly_savings_contribution).toBe(999);
  });
});
