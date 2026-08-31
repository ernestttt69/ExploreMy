<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_id';

    protected $fillable = [
        'trip_id',
        'category',
        'title',
        'scheduled_date',
        'start_time',
        'end_time',
        'location',
        'latitude',
        'longitude',
        'notes',
        'transport_mode',
        'distance_km',
        'carbon_kg',
        'eco_note',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'distance_km' => 'float',
        'carbon_kg' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'metadata' => 'array',
    ];

    /**
     * Get the itinerary this item belongs to.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id', 'id');
    }
}
