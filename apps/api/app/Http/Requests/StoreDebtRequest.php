<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'original_amount' => ['required', 'numeric', 'min:0'],
            'remaining_amount' => ['required', 'numeric', 'min:0'],
            'monthly_payment' => ['required', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0'],
            'end_date' => ['required', 'date'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return [
            'name' => $data['name'],
            'original_amount_cents' => (int) round($data['original_amount'] * 100),
            'remaining_amount_cents' => (int) round($data['remaining_amount'] * 100),
            'monthly_payment_cents' => (int) round($data['monthly_payment'] * 100),
            'rate_bps' => (int) round($data['rate'] * 100),
            'end_date' => $data['end_date'],
        ];
    }
}
