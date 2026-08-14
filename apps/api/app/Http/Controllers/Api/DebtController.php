<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Http\Resources\DebtResource;
use App\Models\Debt;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        return DebtResource::collection($request->user()->debts()->get());
    }

    public function store(StoreDebtRequest $request)
    {
        $debt = $request->user()->debts()->create($request->validated());

        return (new DebtResource($debt))->response()->setStatusCode(201);
    }

    public function update(UpdateDebtRequest $request, Debt $debt)
    {
        $debt->update($request->validated());

        return new DebtResource($debt);
    }
}
