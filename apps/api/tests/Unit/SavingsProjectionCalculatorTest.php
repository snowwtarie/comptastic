<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use App\Services\SavingsProjectionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SavingsProjectionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_compounds_monthly_from_the_current_savings_balance_using_the_stored_rate_and_contribution(): void
    {
        Carbon::setTestNow('2026-08-06');

        $user = User::factory()->create();

        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'savings',
            'opening_balance_cents' => 100000,
        ]);

        $user->settings()->create([
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 10000,
            'annual_return_rate_bps' => 1200,
        ]);

        $result = (new SavingsProjectionCalculator(new AccountBalanceCalculator))->build($user, 2);

        $this->assertCount(4, $result['history']);
        $this->assertSame(100000, $result['projection'][0]);
        $this->assertSame(111000, $result['projection'][1]);
        $this->assertSame(122110, $result['projection'][2]);
    }
}
