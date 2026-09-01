<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreenTree extends Model
{
    protected $fillable = ['user_id', 'level', 'experience', 'growth_stage'];
    protected $casts = ['level' => 'integer', 'experience' => 'integer'];
}
