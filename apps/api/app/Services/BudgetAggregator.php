<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;

class BudgetAggregator
{
    /** @return array<int, array{category_id:int, name:string, color_hex:string, budget_cents:int, spent_cents:int, pct: float, status:string}> */
    public function forMonth(User $user, Carbon $monthStart): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        $budgets = $user->budgets()->pluck('monthly_amount_cents', 'category_id');

        $spent = $user->transactions()
            ->where('amount_cents', '<', 0)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('category_id, SUM(-amount_cents) as spent_cents')
            ->groupBy('category_id')
            ->pluck('spent_cents', 'category_id');

        return Category::where('is_income', false)
            ->orderBy('sort_order')
            ->get()
            ->map(function (Category $category) use ($budgets, $spent) {
                $budgetCents = (int) ($budgets[$category->id] ?? 0);
                $spentCents = (int) ($spent[$category->id] ?? 0);
                $pct = $budgetCents > 0 ? ($spentCents / $budgetCents) * 100 : 0.0;

                return [
                    'category_id' => $category->id,
                    'name' => $category->name,
                    'color_hex' => $category->color_hex,
                    'budget_cents' => $budgetCents,
                    'spent_cents' => $spentCents,
                    'pct' => round($pct, 1),
                    'status' => match (true) {
                        $pct >= 100 => 'over',
                        $pct >= 80 => 'warn',
                        default => 'ok',
                    },
                ];
            })
            ->all();
    }
}
