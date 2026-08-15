<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TransactionListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_lists_only_the_current_month_by_default_newest_first_with_running_balance(): void
    {
        Carbon::setTestNow('2026-08-15');

        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'opening_balance_cents' => 0,
        ]);
        $category = Category::factory()->create();

        $inMonth = Transaction::factory()->for($user)->for($account)->for($category)->create([
            'date' => '2026-08-05',
            'amount_cents' => -1000,
        ]);

        Transaction::factory()->for($user)->for($account)->for($category)->create([
            'date' => '2026-07-31',
            'amount_cents' => -500,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transactions');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($inMonth->id, $response->json('data.0.id'));
        // Running balance is cumulative over the account's full history (opening
        // balance 0, then the 2026-07-31 tx of -500, then this in-month tx of
        // -1000), not scoped to the displayed period.
        $this->assertEquals(-15.0, $response->json('data.0.running_balance'));
    }

    public function test_it_filters_by_account_id_when_provided(): void
    {
        Carbon::setTestNow('2026-08-15');

        $user = User::factory()->create();
        $accountA = Account::factory()->create(['user_id' => $user->id]);
        $accountB = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();

        Transaction::factory()->for($user)->for($accountA)->for($category)->create([
            'date' => '2026-08-05',
        ]);

        Transaction::factory()->for($user)->for($accountB)->for($category)->create([
            'date' => '2026-08-06',
        ]);

        $response = $this->actingAs($user)->getJson("/api/transactions?account_id={$accountA->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($accountA->id, $response->json('data.0.account_id'));
    }
}
