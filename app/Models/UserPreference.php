<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $table = 'user_preferences';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'preference_id',
    ];
}