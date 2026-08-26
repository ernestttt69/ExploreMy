<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreenShopItem extends Model
{
    protected $fillable = ['name', 'description', 'price', 'exp_value', 'is_available'];
    protected $casts = ['is_available' => 'boolean'];
}
