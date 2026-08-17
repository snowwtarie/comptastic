<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardSummaryBuilder
{
    public function build(User $user, string $period): array
    {
        return match ($period) {
            'previous' => $this->weeklyBars($user, Carbon::now()->subMonthNoOverflow()),
            'year' => $this->monthlyBars($user),
            default => $this->weeklyBars($user, Carbon::now()),
        };
    }

    private function weeklyBars(User $user, Carbon $monthReference): array
    {
        $start = $monthReference->copy()->startOfMonth();
        $end = $monthReference->copy()->endOfMonth();

        $transactions = $user->transactions()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'amount_cents', 'category_id']);

        $bars = [];
        $weekIndex = 1;
        for ($weekStart = $start->copy(); $weekStart->lte($end); $weekStart->addWeek()) {
            $weekEnd = $weekStart->copy()->addDays(6)->min($end);
            $weekTxns = $transactions->filter(fn ($t) => $t->date->betweenIncluded($weekStart, $weekEnd));

            $bars[] = [
                'label' => "Sem. {$weekIndex}",
                'income_cents' => (int) $weekTxns->where('amount_cents', '>', 0)->sum('amount_cents'),
                'expense_cents' => (int) $weekTxns->where('amount_cents', '<', 0)->sum('amount_cents') * -1,
            ];
            $weekIndex++;
        }

        return ['bars' => $bars, 'categories' => $this->categoryTotals($transactions)];
    }

    private function monthlyBars(User $user): array
    {
        $year = Carbon::now()->year;
        $transactions = $user->transactions()
            ->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['date', 'amount_cents', 'category_id']);

        $bars = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthTxns = $transactions->filter(fn ($t) => $t->date->month === $month);
            $bars[] = [
                'label' => Carbon::create($year, $month, 1)->translatedFormat('M'),
                'income_cents' => (int) $monthTxns->where('amount_cents', '>', 0)->sum('amount_cents'),
                'expense_cents' => (int) $monthTxns->where('amount_cents', '<', 0)->sum('amount_cents') * -1,
            ];
        }

        return ['bars' => $bars, 'categories' => $this->categoryTotals($transactions)];
    }

    private function categoryTotals(Collection $transactions): array
    {
        return $transactions
            ->where('amount_cents', '<', 0)
            ->groupBy('category_id')
            ->map(fn ($group, $categoryId) => [
                'category_id' => (int) $categoryId,
                'amount_cents' => (int) $group->sum('amount_cents') * -1,
            ])
            ->values()
            ->all();
    }
}
