<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionRunningBalanceCalculator;
use App\Services\TransactionSeriesGenerator;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionRunningBalanceCalculator $runningBalances,
        private TransactionSeriesGenerator $seriesGenerator,
    ) {}

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

    public function store(StoreTransactionRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $rows = match ($data['mode']) {
            'installment' => $this->seriesGenerator->installments(
                $data['label'], $data['amount_cents'], $data['date'],
                $data['installment']['count'], $data['reconciled'],
            ),
            'recurring' => $this->seriesGenerator->recurring(
                $data['label'], $data['amount_cents'], $data['date'],
                $data['recurring']['count'], $data['recurring']['frequency'], $data['reconciled'],
            ),
            default => [[
                'date' => $data['date'], 'label' => $data['label'], 'amount_cents' => $data['amount_cents'],
                'reconciled' => $data['reconciled'], 'series_id' => null, 'series_kind' => null, 'series_index' => null,
            ]],
        };

        $created = collect($rows)->map(fn (array $row) => $user->transactions()->create([
            ...$row,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'link_type' => $data['link_type'],
            'linked_debt_id' => $data['linked_debt_id'] ?? null,
            'linked_savings_account_id' => $data['linked_savings_account_id'] ?? null,
        ]));

        return TransactionResource::collection($created)->response()->setStatusCode(201);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $transaction->update($request->validated());

        return new TransactionResource($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return response()->noContent();
    }

    public function destroySeries(Request $request, string $seriesId)
    {
        $request->user()->transactions()
            ->where('series_id', $seriesId)
            ->where('reconciled', false)
            ->delete();

        return response()->noContent();
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
