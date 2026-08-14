<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bank' => $this->bank,
            'type' => $this->type,
            'iban_last4' => $this->iban_last4,
            'opening_balance' => (float) $this->opening_balance_cents / 100,
            'balance' => (float) $this->balance_cents / 100,
            'pending_encours' => (float) $this->pending_encours_cents / 100,
        ];
    }
}
