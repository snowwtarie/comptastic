<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'monthly_income' => $this->monthly_income_cents / 100,
            'monthly_savings_contribution' => $this->monthly_savings_contribution_cents / 100,
            'annual_return_rate' => $this->annual_return_rate_bps / 100,
        ];
    }
}
