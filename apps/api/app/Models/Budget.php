<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use BelongsToUser;

    protected $fillable = ['category_id', 'monthly_amount_cents'];
}
