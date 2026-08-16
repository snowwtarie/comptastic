<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BudgetAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_spent_pct_and_status_per_expense_category_for_the_given_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $logement = Category::factory()->create([
            'name' => 'Logement',
            'is_income' => false,
            'sort_order' => 0,
        ]);

        $revenus = Category::factory()->create([
            'name' => 'Revenus',
            'is_income' => true,
            'sort_order' => 1,
        ]);

        $user->budgets()->create([
            'category_id' => $logement->id,
            'monthly_amount_cents' => 80000,
        ]);

        Transaction::factory()->for($user)->for($account)->for($logement)->create([
            'amount_cents' => -85000,
            'date' => '2026-08-04',
        ]);

        Transaction::factory()->for($user)->for($account)->for($revenus)->create([
            'amount_cents' => 220000,
            'date' => '2026-08-05',
        ]);

        $rows = (new BudgetAggregator)->forMonth($user, Carbon::parse('2026-08-01'));

        $names = array_column($rows, 'name');
        $this->assertNotContains('Revenus', $names);

        $logementRow = collect($rows)->firstWhere('name', 'Logement');

        $this->assertNotNull($logementRow);
        $this->assertSame(85000, $logementRow['spent_cents']);
        $this->assertSame(106.3, $logementRow['pct']);
        $this->assertSame('over', $logementRow['status']);
    }
}
