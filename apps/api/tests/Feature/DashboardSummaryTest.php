<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_weekly_bars_and_category_totals_for_the_current_month(): void
    {
        Carbon::setTestNow('2026-08-15');

        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $logement = Category::factory()->create([
            'name' => 'Logement',
            'is_income' => false,
        ]);
        $revenus = Category::factory()->create([
            'name' => 'Revenus',
            'is_income' => true,
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $logement->id,
            'label' => 'Loyer',
            'amount_cents' => -78000,
            'date' => '2026-08-04',
            'reconciled' => true,
            'link_type' => 'none',
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $revenus->id,
            'label' => 'Salaire',
            'amount_cents' => 220000,
            'date' => '2026-08-05',
            'reconciled' => true,
            'link_type' => 'none',
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertOk();

        $bars = $response->json('data.bars');
        $this->assertNotEmpty($bars);

        $categories = collect($response->json('data.categories'));
        $logementRow = $categories->firstWhere('category_id', $logement->id);

        $this->assertNotNull($logementRow);
        $this->assertEquals(780.0, $logementRow['amount']);
    }
}
