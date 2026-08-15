<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'label' => $this->label,
            'amount' => $this->amount_cents / 100,
            'date' => $this->date->toDateString(),
            'reconciled' => $this->reconciled,
            'link_type' => $this->link_type,
            'linked_debt_id' => $this->linked_debt_id,
            'linked_savings_account_id' => $this->linked_savings_account_id,
            'series_id' => $this->series_id,
            'series_kind' => $this->series_kind,
            'series_index' => $this->series_index,
            'running_balance' => $this->when(
                isset($this->running_balance_cents),
                fn () => $this->running_balance_cents !== null ? $this->running_balance_cents / 100 : null,
            ),
        ];
    }
}
