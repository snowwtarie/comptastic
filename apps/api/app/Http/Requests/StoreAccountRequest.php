<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:checking,savings'],
            'iban_last4' => ['nullable', 'string', 'size:4'],
            'opening_balance' => ['required', 'numeric'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['opening_balance_cents'] = (int) round($data['opening_balance'] * 100);
        unset($data['opening_balance']);

        return $data;
    }
}
