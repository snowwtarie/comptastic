<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountBalanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(private AccountBalanceCalculator $balances) {}

    public function index(Request $request)
    {
        $today = Carbon::today();

        $accounts = $request->user()->accounts()->get()->each(function (Account $account) use ($today) {
            $account->balance_cents = $this->balances->balanceAt($account, $today);
            $account->pending_encours_cents = $this->balances->pendingEncoursAt($account, $today);
        });

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request)
    {
        $account = $request->user()->accounts()->create($request->validated());
        $account->balance_cents = $account->opening_balance_cents;
        $account->pending_encours_cents = 0;

        return (new AccountResource($account))->response()->setStatusCode(201);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $today = Carbon::today();

        $account->update($request->validated());
        $account->balance_cents = $this->balances->balanceAt($account, $today);
        $account->pending_encours_cents = $this->balances->pendingEncoursAt($account, $today);

        return new AccountResource($account);
    }

    public function destroy(Account $account)
    {
        if ($account->transactions()->exists()) {
            throw ValidationException::withMessages([
                'account' => ['Ce compte a des transactions et ne peut pas être supprimé.'],
            ]);
        }

        $account->delete();

        return response()->noContent();
    }
}
