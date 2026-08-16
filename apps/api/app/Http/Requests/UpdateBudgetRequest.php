<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        return [
            'monthly_amount_cents' => (int) round($this->input('monthly_amount') * 100),
        ];
    }
}
