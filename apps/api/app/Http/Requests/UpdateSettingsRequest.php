<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'monthly_savings_contribution' => ['required', 'numeric'],
            'annual_return_rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return [
            'monthly_income_cents' => (int) round($data['monthly_income'] * 100),
            'monthly_savings_contribution_cents' => (int) round($data['monthly_savings_contribution'] * 100),
            'annual_return_rate_bps' => (int) round($data['annual_return_rate'] * 100),
        ];
    }
}
