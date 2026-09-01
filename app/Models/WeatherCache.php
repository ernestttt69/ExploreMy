<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherCache extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_key',
        'forecast_date',
        'payload',
        'expires_at',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];
}
