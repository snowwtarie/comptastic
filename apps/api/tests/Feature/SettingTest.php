<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_default_settings_on_demand_when_reading_for_the_first_time(): void
    {
        $user = User::factory()->create();
        $user->settings()->create([
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 0,
            'annual_return_rate_bps' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/settings');

        $response->assertOk();
        $this->assertEquals(0.0, $response->json('data.monthly_income'));
    }

    public function test_it_updates_settings_from_euro_percent_input(): void
    {
        $user = User::factory()->create();
        $user->settings()->create([
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 0,
            'annual_return_rate_bps' => 0,
        ]);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'monthly_income' => 2200,
            'monthly_savings_contribution' => 1000,
            'annual_return_rate' => 2,
        ]);

        $response->assertOk();
        $this->assertSame(200, $user->settings->fresh()->annual_return_rate_bps);
    }
}
