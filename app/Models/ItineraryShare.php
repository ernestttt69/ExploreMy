<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryShare extends Model
{
    use HasFactory;

    protected $primaryKey = 'share_id';

    protected $fillable = [
        'trip_id',
        'created_by',
        'token_hash',
        'permission',
        'expires_at',
        'last_accessed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    /**
     * Get the trip exposed by this link.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id', 'id');
    }

    /**
     * Check whether a link can still be used.
     */
    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Check whether the recipient can modify the itinerary.
     */
    public function canEdit(): bool
    {
        return $this->isActive() && $this->permission === 'edit';
    }
}
