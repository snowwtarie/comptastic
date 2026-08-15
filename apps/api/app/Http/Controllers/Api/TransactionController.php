<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionRunningBalanceCalculator;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private TransactionRunningBalanceCalculator $runningBalances) {}

    public function index(Request $request)
    {
        [$start, $end] = $this->periodRange($request->string('period', 'current')->toString());

        $query = $request->user()->transactions()
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        $transactions = $query->paginate(50);

        $balancesByAccount = [];
        $transactions->getCollection()->each(function (Transaction $transaction) use (&$balancesByAccount) {
            $accountId = $transaction->account_id;
            if (! isset($balancesByAccount[$accountId])) {
                $balancesByAccount[$accountId] = $this->runningBalances->forAccount($transaction->account);
            }
            $transaction->running_balance_cents = $balancesByAccount[$accountId][$transaction->id] ?? null;
        });

        return TransactionResource::collection($transactions);
    }

    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'previous' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'year' => [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
            ],
            default => [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ],
        };
    }
}
