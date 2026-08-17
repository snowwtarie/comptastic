<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_history_and_projection_points(): void
    {
        $user = User::factory()->create();

        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'savings',
            'opening_balance_cents' => 100000,
        ]);

        $user->settings()->create([
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 10000,
            'annual_return_rate_bps' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/savings-projection?horizon=6');

        $response->assertOk();
        $this->assertCount(4, $response->json('data.history'));
        $this->assertCount(7, $response->json('data.projection'));
    }

    public function test_it_returns_a_flat_projection_for_a_user_with_no_settings_row(): void
    {
        $user = User::factory()->create();

        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'savings',
            'opening_balance_cents' => 100000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/savings-projection?horizon=3');

        $response->assertOk();
        $this->assertCount(4, $response->json('data.history'));
        $this->assertCount(4, $response->json('data.projection'));

        // No contribution, no return rate: the projection stays flat at the
        // current savings balance for every future point.
        foreach ($response->json('data.projection') as $point) {
            $this->assertEquals(1000.0, $point);
        }

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 0,
            'annual_return_rate_bps' => 0,
        ]);
    }
}
