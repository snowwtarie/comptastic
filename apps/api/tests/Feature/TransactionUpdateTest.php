<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_toggles_reconciled_on_the_authenticated_users_own_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'reconciled' => false,
        ]);

        $response = $this->actingAs($user)->patchJson("/api/transactions/{$transaction->id}", [
            'reconciled' => true,
        ]);

        $response->assertOk();
        $this->assertTrue($transaction->fresh()->reconciled);
    }

    public function test_it_returns_404_when_patching_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $account = Account::factory()->create(['user_id' => $owner->id]);
        $category = Category::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'reconciled' => false,
        ]);

        $response = $this->actingAs($intruder)->patchJson("/api/transactions/{$transaction->id}", [
            'reconciled' => true,
        ]);

        $response->assertNotFound();
    }

    public function test_it_deletes_a_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertNoContent();
        $this->assertNull(Transaction::find($transaction->id));
    }
}
