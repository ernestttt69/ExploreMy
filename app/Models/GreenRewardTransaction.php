<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreenRewardTransaction extends Model
{
    protected $fillable = ['user_id', 'activity', 'amount', 'transaction_type', 'metadata'];
    protected $casts = ['metadata' => 'array'];
}
