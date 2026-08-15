<?php

namespace App\Services;

use App\Models\Account;

class TransactionRunningBalanceCalculator
{
    /** @return array<int,int> transaction id => running balance in cents */
    public function forAccount(Account $account): array
    {
        $running = $account->opening_balance_cents;
        $balances = [];

        $account->transactions()
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'amount_cents'])
            ->each(function ($transaction) use (&$running, &$balances) {
                $running += $transaction->amount_cents;
                $balances[$transaction->id] = $running;
            });

        return $balances;
    }
}
