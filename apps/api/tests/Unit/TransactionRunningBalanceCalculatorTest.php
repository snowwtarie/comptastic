<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionRunningBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionRunningBalanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accumulates_every_transaction_on_the_account_regardless_of_reconciled_status(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'opening_balance_cents' => 1000,
        ]);
        $category = Category::factory()->create();

        $t1 = Transaction::factory()->for($user)->for($account)->for($category)->create([
            'amount_cents' => 500,
            'date' => '2026-08-01',
            'reconciled' => true,
        ]);

        $t2 = Transaction::factory()->for($user)->for($account)->for($category)->create([
            'amount_cents' => -200,
            'date' => '2026-08-02',
            'reconciled' => false,
        ]);

        $balances = (new TransactionRunningBalanceCalculator)->forAccount($account->fresh());

        $this->assertSame(1500, $balances[$t1->id]);
        $this->assertSame(1300, $balances[$t2->id]);
    }
}
