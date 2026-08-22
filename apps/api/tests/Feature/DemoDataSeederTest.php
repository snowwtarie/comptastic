<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_demo_user_with_accounts_transactions_debts_and_budgets(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(DemoDataSeeder::class);

        $user = User::where('email', 'demo@comptastic.test')->firstOrFail();

        $this->assertCount(5, $user->accounts);
        $this->assertCount(10, $user->transactions);
        $this->assertCount(3, $user->debts);
        $this->assertCount(6, $user->budgets);
        $this->assertNotNull($user->settings);
    }
}
