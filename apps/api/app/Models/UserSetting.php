<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'monthly_income_cents', 'monthly_savings_contribution_cents', 'annual_return_rate_bps',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
