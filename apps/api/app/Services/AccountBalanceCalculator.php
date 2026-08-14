<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;

class AccountBalanceCalculator
{
    public function balanceAt(Account $account, CarbonInterface $asOf): int
    {
        if (! class_exists(Transaction::class)) {
            return $account->opening_balance_cents;
        }

        $reconciledSum = $account->transactions()
            ->where('reconciled', true)
            ->whereDate('date', '<=', $asOf)
            ->sum('amount_cents');

        return $account->opening_balance_cents + (int) $reconciledSum;
    }

    public function pendingEncoursAt(Account $account, CarbonInterface $asOf): int
    {
        if (! class_exists(Transaction::class)) {
            return 0;
        }

        return (int) $account->transactions()
            ->where('reconciled', false)
            ->whereDate('date', '<=', $asOf)
            ->sum('amount_cents');
    }
}
