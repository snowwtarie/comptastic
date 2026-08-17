<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardSummaryBuilder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardSummaryBuilder $builder) {}

    public function summary(Request $request)
    {
        $period = $request->string('period', 'current')->toString();
        $summary = $this->builder->build($request->user(), $period);

        return response()->json([
            'data' => [
                'bars' => collect($summary['bars'])->map(fn ($bar) => [
                    'label' => $bar['label'],
                    'income' => $bar['income_cents'] / 100,
                    'expense' => $bar['expense_cents'] / 100,
                ]),
                'categories' => collect($summary['categories'])->map(fn ($cat) => [
                    'category_id' => $cat['category_id'],
                    'amount' => $cat['amount_cents'] / 100,
                ]),
            ],
        ]);
    }
}
