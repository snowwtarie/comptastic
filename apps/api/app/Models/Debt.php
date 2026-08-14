<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'name', 'original_amount_cents', 'remaining_amount_cents',
        'monthly_payment_cents', 'rate_bps', 'end_date',
    ];

    protected $casts = [
        'end_date' => 'date',
    ];
}
