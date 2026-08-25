<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_debts_with_derived_progress_and_months_left(): void
    {
        $user = User::factory()->create();

        Debt::factory()->create([
            'user_id' => $user->id,
            'original_amount_cents' => 100000,
            'remaining_amount_cents' => 40000,
            'monthly_payment_cents' => 10000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/debts');

        $response->assertOk();
        $this->assertEquals(60.0, $response->json('data.0.progress_pct'));
        $this->assertSame(4, $response->json('data.0.months_left'));
    }

    public function test_it_creates_a_debt_from_euro_amounts_and_percentage_rate(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/debts', [
            'name' => 'Prêt auto',
            'original_amount' => 18000,
            'remaining_amount' => 11200,
            'monthly_payment' => 320,
            'rate' => 3.9,
            'end_date' => '2029-06-15',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('debts', [
            'name' => 'Prêt auto',
            'user_id' => $user->id,
            'rate_bps' => 390,
        ]);
    }

    public function test_it_returns_404_when_another_user_tries_to_update_a_debt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $debt = Debt::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->patchJson("/api/debts/{$debt->id}", [
            'name' => 'Hacked',
            'original_amount' => 1000,
            'remaining_amount' => 500,
            'monthly_payment' => 50,
            'rate' => 1.0,
            'end_date' => '2030-01-01',
        ]);

        $response->assertNotFound();
    }

    public function test_it_deletes_a_debt(): void
    {
        $user = User::factory()->create();
        $debt = Debt::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/debts/{$debt->id}");

        $response->assertNoContent();
        $this->assertNull(Debt::find($debt->id));
    }

    public function test_it_returns_404_when_another_user_tries_to_delete_a_debt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $debt = Debt::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->deleteJson("/api/debts/{$debt->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('debts', ['id' => $debt->id]);
    }
}
