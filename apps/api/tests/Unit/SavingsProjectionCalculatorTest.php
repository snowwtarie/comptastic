<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use App\Services\SavingsProjectionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SavingsProjectionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_compounds_monthly_from_the_current_savings_balance_using_the_stored_rate_and_contribution(): void
    {
        Carbon::setTestNow('2026-08-06');

        $user = User::factory()->create();

        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'savings',
            'opening_balance_cents' => 100000,
        ]);

        $user->settings()->create([
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 10000,
            'annual_return_rate_bps' => 1200,
        ]);

        $result = (new SavingsProjectionCalculator(new AccountBalanceCalculator))->build($user, 2);

        $this->assertCount(4, $result['history']);
        $this->assertSame(100000, $result['projection'][0]);
        $this->assertSame(111000, $result['projection'][1]);
        $this->assertSame(122110, $result['projection'][2]);
    }

    public function test_the_month_offset_minus_one_point_is_end_of_last_month_not_end_of_the_current_month(): void
    {
        // Freeze "today" late in August so the current month still has days
        // left to run. month_offset -1 must land on 2026-07-31 (end of last
        // month). The pre-fix code computed subMonthsNoOverflow(0)->endOfMonth()
        // for this point, i.e. 2026-08-31 — a date in the future relative to
        // "today" — which would wrongly pull in any transaction dated earlier
        // in August.
        Carbon::setTestNow('2026-08-20');

        $user = User::factory()->create();

        $account = Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'savings',
            'opening_balance_cents' => 100000,
        ]);

        // Dated in the second half of the current month: after end-of-last-month
        // (2026-07-31) but before "today" (2026-08-20).
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount_cents' => -5000,
            'date' => '2026-08-18',
            'reconciled' => true,
        ]);

        $user->settings()->create([
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 0,
            'annual_return_rate_bps' => 0,
        ]);

        $result = (new SavingsProjectionCalculator(new AccountBalanceCalculator))->build($user, 1);

        $history = collect($result['history'])->keyBy('month_offset');

        // Correct (fixed) behaviour: as-of 2026-07-31, the 2026-08-18
        // transaction hasn't happened yet, so the balance is still the
        // opening balance. The buggy code would compute this as-of
        // 2026-08-31 instead, wrongly including the transaction and
        // yielding 95000.
        $this->assertSame(100000, $history[-1]['balance_cents']);

        // Sanity check: "today" (month_offset 0) does include the transaction.
        $this->assertSame(95000, $history[0]['balance_cents']);
    }
}
