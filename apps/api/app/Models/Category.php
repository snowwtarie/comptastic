<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color_hex', 'is_income', 'sort_order'];

    protected $casts = [
        'is_income' => 'boolean',
    ];
}
