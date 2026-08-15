<?php

namespace App\Services;

use App\Models\Account;
use Carbon\CarbonInterface;

class AccountBalanceCalculator
{
    public function balanceAt(Account $account, CarbonInterface $asOf): int
    {
        $reconciledSum = $account->transactions()
            ->where('reconciled', true)
            ->whereDate('date', '<=', $asOf)
            ->sum('amount_cents');

        return $account->opening_balance_cents + (int) $reconciledSum;
    }

    public function pendingEncoursAt(Account $account, CarbonInterface $asOf): int
    {
        return (int) $account->transactions()
            ->where('reconciled', false)
            ->whereDate('date', '<=', $asOf)
            ->sum('amount_cents');
    }
}
