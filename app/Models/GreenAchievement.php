<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreenAchievement extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'requirement_type', 'requirement_value', 'reward_points'];
}
