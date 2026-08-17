<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class SavingsProjectionCalculator
{
    public function __construct(private AccountBalanceCalculator $balanceCalculator) {}

    /** @return array{history: array<int, array{month_offset:int, balance_cents:int}>, projection: array<int, int>} */
    public function build(User $user, int $horizonMonths): array
    {
        $today = Carbon::today();
        $history = [];

        for ($i = 3; $i >= 0; $i--) {
            $asOf = $i === 0 ? $today : $today->copy()->subMonthsNoOverflow($i - 1)->endOfMonth();
            $history[] = [
                'month_offset' => -$i,
                'balance_cents' => $this->savingsBalanceAt($user, $asOf),
            ];
        }

        // A user has no user_settings row until they've saved settings at
        // least once (or the registration flow creates one, in a later
        // task). Fall back to a zeroed default row rather than crashing on
        // a null relation, mirroring the fix applied to SettingController.
        $settings = $user->settings()->firstOrCreate([], [
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 0,
            'annual_return_rate_bps' => 0,
        ]);

        $contribution = $settings->monthly_savings_contribution_cents;
        $monthlyRate = $settings->annual_return_rate_bps / 10000 / 12;

        $projection = [end($history)['balance_cents']];
        for ($i = 1; $i <= $horizonMonths; $i++) {
            $projection[] = (int) round($projection[$i - 1] * (1 + $monthlyRate) + $contribution);
        }

        return ['history' => $history, 'projection' => $projection];
    }

    private function savingsBalanceAt(User $user, Carbon $asOf): int
    {
        return $user->accounts()
            ->where('type', 'savings')
            ->get()
            ->sum(fn ($account) => $this->balanceCalculator->balanceAt($account, $asOf));
    }
}
