<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_single_expense_transaction_with_a_negative_signed_amount(): void
    {
        $user = User::factory()->create();
        [$account, $category] = $this->accountAndCategoryFor($user);

        $response = $this->actingAs($user)->postJson('/api/transactions', [
            'label' => 'Supermarché',
            'amount' => 64.20,
            'type' => 'expense',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'date' => '2026-08-03',
            'reconciled' => true,
            'link_type' => 'none',
            'mode' => 'simple',
        ]);

        $response->assertCreated();
        $this->assertSame(-6420, Transaction::first()->amount_cents);
    }

    public function test_it_creates_an_installment_series_that_sums_exactly_to_the_total(): void
    {
        $user = User::factory()->create();
        [$account, $category] = $this->accountAndCategoryFor($user);

        $response = $this->actingAs($user)->postJson('/api/transactions', [
            'label' => 'Canapé',
            'amount' => 100,
            'type' => 'expense',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'date' => '2026-08-01',
            'reconciled' => true,
            'link_type' => 'none',
            'mode' => 'installment',
            'installment' => ['count' => 3],
        ]);

        $response->assertCreated();
        $this->assertSame(3, Transaction::count());
        $this->assertSame(-10000, Transaction::sum('amount_cents'));
    }

    public function test_it_requires_a_linked_debt_when_link_type_is_debt(): void
    {
        $user = User::factory()->create();
        [$account, $category] = $this->accountAndCategoryFor($user);

        $response = $this->actingAs($user)->postJson('/api/transactions', [
            'label' => 'Remboursement',
            'amount' => 100,
            'type' => 'expense',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'date' => '2026-08-01',
            'reconciled' => true,
            'link_type' => 'debt',
            'mode' => 'simple',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_rejects_a_savings_link_that_points_at_a_checking_account(): void
    {
        $user = User::factory()->create();
        [$account, $category] = $this->accountAndCategoryFor($user);
        $checkingTarget = Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'checking',
        ]);

        $response = $this->actingAs($user)->postJson('/api/transactions', [
            'label' => 'Virement',
            'amount' => 100,
            'type' => 'expense',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'date' => '2026-08-01',
            'reconciled' => true,
            'link_type' => 'savings',
            'linked_savings_account_id' => $checkingTarget->id,
            'mode' => 'simple',
        ]);

        $response->assertStatus(422);
    }

    /**
     * @return array{0: Account, 1: Category}
     */
    private function accountAndCategoryFor(User $user): array
    {
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'checking',
        ]);

        $category = Category::factory()->create([
            'is_income' => false,
        ]);

        return [$account, $category];
    }
}
