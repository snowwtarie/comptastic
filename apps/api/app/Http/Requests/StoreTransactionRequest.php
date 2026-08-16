<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'type' => ['required', 'in:expense,income'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'reconciled' => ['boolean'],
            'link_type' => ['required', 'in:none,debt,savings'],
            'linked_debt_id' => [
                'required_if:link_type,debt', 'nullable', 'integer',
                function ($attribute, $value, $fail) {
                    if ($value && ! Debt::find($value)) {
                        $fail('Dette introuvable.');
                    }
                },
            ],
            'linked_savings_account_id' => [
                'required_if:link_type,savings', 'nullable', 'integer',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }
                    $account = Account::find($value);
                    if (! $account) {
                        $fail('Compte introuvable.');
                    } elseif ($account->type !== 'savings') {
                        $fail("Le compte lié doit être un compte d'épargne.");
                    }
                },
            ],
            'mode' => ['required', 'in:simple,installment,recurring'],
            'installment.count' => ['required_if:mode,installment', 'integer', 'in:2,3,4,6,12'],
            'recurring.count' => ['required_if:mode,recurring', 'integer', 'in:3,6,12,24'],
            'recurring.frequency' => ['required_if:mode,recurring', 'in:weekly,monthly,yearly'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $amountCents = (int) round(abs($data['amount']) * 100);
        $data['amount_cents'] = $data['type'] === 'expense' ? -$amountCents : $amountCents;
        $data['reconciled'] = $data['reconciled'] ?? false;

        return $data;
    }
}
