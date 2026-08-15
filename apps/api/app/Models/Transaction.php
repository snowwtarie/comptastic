<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'account_id', 'category_id', 'label', 'amount_cents', 'date',
        'reconciled', 'link_type', 'linked_debt_id', 'linked_savings_account_id',
        'series_id', 'series_kind', 'series_index',
    ];

    protected $casts = [
        'date' => 'date',
        'reconciled' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
