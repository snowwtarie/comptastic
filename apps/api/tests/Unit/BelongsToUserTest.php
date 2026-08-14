<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_overwrite_an_explicitly_set_user_id(): void
    {
        $currentUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($currentUser);

        // user_id is intentionally not mass-assignable (it should come from
        // auth or an explicit assignment like this, never raw user input),
        // so it's set directly on the model to simulate a seeder/admin flow
        // that creates a record on another user's behalf.
        $account = new Account([
            'name' => 'Seeded Account',
            'type' => 'checking',
            'opening_balance_cents' => 0,
        ]);
        $account->user_id = $otherUser->id;
        $account->save();

        $this->assertSame($otherUser->id, $account->user_id);
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'user_id' => $otherUser->id,
        ]);
    }
}
