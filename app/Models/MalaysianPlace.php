<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MalaysianPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'provider_place_id',
        'name',
        'display_name',
        'admin1',
        'admin2',
        'country_code',
        'latitude',
        'longitude',
        'timezone',
        'provider_payload',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'provider_payload' => 'array',
    ];

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(ItineraryItem::class, 'place_id');
    }
}
