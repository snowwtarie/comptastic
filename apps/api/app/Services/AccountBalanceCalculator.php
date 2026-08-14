<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;

class AccountBalanceCalculator
{
    public function balanceAt(Account $account, CarbonInterface $asOf): int
    {
        if ($this->transactionModelMissing()) {
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
        if ($this->transactionModelMissing()) {
            return 0;
        }

        return (int) $account->transactions()
            ->where('reconciled', false)
            ->whereDate('date', '<=', $asOf)
            ->sum('amount_cents');
    }

    /**
     * TODO(Task 6): remove this guard (and this method) once the
     * Transaction model exists. Eloquent's hasMany() resolves the
     * related class eagerly the moment the relation is queried (not
     * lazily on declaration), so without this check Account::transactions()
     * throws "Class Transaction not found" until Task 6 creates it.
     */
    private function transactionModelMissing(): bool
    {
        return ! class_exists(Transaction::class);
    }
}
