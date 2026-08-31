<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use HasFactory;

    /**
     * The existing application stores trips with Laravel's conventional id key.
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'title',
        'destination',
        'description',
        'start_date',
        'end_date',
        'days',
        'co2_kg',
        'map_center',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'map_center' => 'array',
    ];

    /**
     * Get the owner of this trip.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the itinerary entries in their display order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItineraryItem::class, 'trip_id', 'id')
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('sort_order');
    }

    /**
     * Get share links created for this trip.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ItineraryShare::class, 'trip_id', 'id');
    }

    /**
     * Scope a query to a single traveller's trips.
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the total estimated footprint from loaded itinerary items.
     */
    public function getTotalCarbonKgAttribute(): float
    {
        return round((float) $this->items->sum('carbon_kg'), 2);
    }
}
