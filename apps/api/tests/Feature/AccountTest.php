<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
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

    public function test_it_updates_an_account_and_recomputes_its_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'opening_balance_cents' => 5000,
        ]);

        $response = $this->actingAs($user)->patchJson("/api/accounts/{$account->id}", [
            'name' => 'Compte renommé',
            'bank' => 'Nouvelle banque',
            'type' => 'checking',
            'opening_balance' => 100,
        ]);

        $response->assertOk();
        $this->assertSame('Compte renommé', $response->json('data.name'));
        $this->assertEquals(100.0, $response->json('data.balance'));
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Compte renommé']);
    }

    public function test_it_returns_404_when_another_user_tries_to_update_an_account(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->patchJson("/api/accounts/{$account->id}", [
            'name' => 'Hacked',
            'type' => 'checking',
            'opening_balance' => 0,
        ]);

        $response->assertNotFound();
    }

    public function test_it_deletes_an_account_with_no_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/accounts/{$account->id}");

        $response->assertNoContent();
        $this->assertNull(Account::find($account->id));
    }

    public function test_it_refuses_to_delete_an_account_that_has_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/accounts/{$account->id}");

        $response->assertUnprocessable();
        $this->assertNotNull(Account::find($account->id));
    }
}
