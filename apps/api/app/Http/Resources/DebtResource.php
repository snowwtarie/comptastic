<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray($request): array
    {
        $progressPct = $this->original_amount_cents > 0
            ? min((($this->original_amount_cents - $this->remaining_amount_cents) / $this->original_amount_cents) * 100, 100)
            : 0.0;

        $monthsLeft = $this->monthly_payment_cents > 0
            ? (int) ceil($this->remaining_amount_cents / $this->monthly_payment_cents)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_amount' => $this->original_amount_cents / 100,
            'remaining_amount' => $this->remaining_amount_cents / 100,
            'monthly_payment' => $this->monthly_payment_cents / 100,
            'rate' => $this->rate_bps / 100,
            'end_date' => $this->end_date->toDateString(),
            'progress_pct' => round($progressPct, 1),
            'months_left' => $monthsLeft,
        ];
    }
}
