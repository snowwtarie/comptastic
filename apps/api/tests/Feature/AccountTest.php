<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_the_authenticated_users_accounts_with_computed_balance(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Compte courant',
            'opening_balance_cents' => 5000,
        ]);
        Account::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson('/api/accounts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Compte courant', $response->json('data.0.name'));
        // json_encode() drops the trailing .0 from whole-number floats, so
        // the value round-trips as an int; compare numerically, not by type.
        $this->assertEquals(50.0, $response->json('data.0.balance'));
    }

    public function test_it_creates_an_account_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/accounts', [
            'name' => 'Livret A',
            'bank' => 'Ma Banque',
            'type' => 'savings',
            'iban_last4' => '1234',
            'opening_balance' => 123.45,
        ]);

        $response->assertCreated();
        $this->assertSame(123.45, $response->json('data.opening_balance'));

        $this->assertDatabaseHas('accounts', [
            'name' => 'Livret A',
            'user_id' => $user->id,
        ]);
    }
}
