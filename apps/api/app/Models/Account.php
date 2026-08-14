<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = ['name', 'bank', 'type', 'iban_last4', 'opening_balance_cents'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
