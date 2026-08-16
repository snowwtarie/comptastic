<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Category;
use App\Services\BudgetAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BudgetController extends Controller
{
    public function __construct(private BudgetAggregator $aggregator) {}

    public function index(Request $request)
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->string('month').'-01')
            : Carbon::now()->startOfMonth();

        return response()->json([
            'data' => $this->aggregator->forMonth($request->user(), $month),
        ]);
    }

    public function update(UpdateBudgetRequest $request, Category $category)
    {
        $budget = $request->user()->budgets()->updateOrCreate(
            ['category_id' => $category->id],
            ['monthly_amount_cents' => $request->validated()['monthly_amount_cents']],
        );

        return response()->json(['data' => [
            'category_id' => $category->id,
            'monthly_amount' => $budget->monthly_amount_cents / 100,
        ]]);
    }
}
