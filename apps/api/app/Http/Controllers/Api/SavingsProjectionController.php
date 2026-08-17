<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SavingsProjectionCalculator;
use Illuminate\Http\Request;

class SavingsProjectionController extends Controller
{
    public function __construct(private SavingsProjectionCalculator $calculator) {}

    public function __invoke(Request $request)
    {
        $horizon = $request->integer('horizon', 12);
        $result = $this->calculator->build($request->user(), $horizon);

        return response()->json([
            'data' => [
                'history' => collect($result['history'])->map(fn ($p) => [
                    'month_offset' => $p['month_offset'],
                    'balance' => $p['balance_cents'] / 100,
                ]),
                'projection' => collect($result['projection'])->map(fn ($cents) => $cents / 100),
            ],
        ]);
    }
}
