<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountBalanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_balance_and_pending_encours_as_of_a_date(): void
    {
        $this->markTestSkipped('Enabled in Task 6 once the Transaction model exists.');

        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'opening_balance_cents' => 10000,
        ]);

        // Reconciled, past: included in balance.
        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount_cents' => -2000,
            'date' => '2026-08-01',
            'reconciled' => true,
        ]);

        // Unreconciled, past: excluded from balance, included in pending encours.
        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount_cents' => -500,
            'date' => '2026-08-02',
            'reconciled' => false,
        ]);

        // Reconciled, far future: excluded from balance as of today.
        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount_cents' => 100000,
            'date' => '2099-01-01',
            'reconciled' => true,
        ]);

        $calculator = new AccountBalanceCalculator;
        $asOf = Carbon::parse('2026-08-06');

        $this->assertSame(8000, $calculator->balanceAt($account, $asOf));
        $this->assertSame(-500, $calculator->pendingEncoursAt($account, $asOf));
    }
}
