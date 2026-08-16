<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_aggregated_budgets_for_the_requested_month(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'is_income' => false,
            'sort_order' => 0,
        ]);

        $user->budgets()->create([
            'category_id' => $category->id,
            'monthly_amount_cents' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/budgets?month=2026-08');

        $response->assertOk();
        $response->assertJsonPath('data.0.budget_cents', 50000);
    }

    public function test_it_upserts_a_budget_amount_for_a_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'is_income' => false,
        ]);

        $response = $this->actingAs($user)->putJson("/api/budgets/{$category->id}", [
            'monthly_amount' => 500,
        ]);

        $response->assertOk();
        $this->assertSame(50000, $user->budgets()->first()->monthly_amount_cents);
    }
}
