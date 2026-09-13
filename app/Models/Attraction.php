<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attraction extends Model
{
    protected $primaryKey = 'attraction_id';

    public $timestamps = true;

    protected $fillable = [
        'place_id',
        'state_id',
        'attraction_name',
        'description',
        'location',
        'operating_hours',
        'entrance_fee',
        'budget_level',
        'nearby_transport',
        'rating',
        'image_path',
    ];

    public function images()
    {
        return $this->hasMany(
            AttractionImage::class,
            'attraction_id',
            'attraction_id'
        );
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(
            State::class,
            'state_id',
            'state_id'
        );
    }

    public function preferences()
    {
        return $this->belongsToMany(
            PreferenceCategory::class,
            'attraction_preferences',
            'attraction_id',
            'preference_id',
            'attraction_id',
            'preference_id'
        );
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'wishlists',
            'attraction_id',
            'user_id'
        );
    }

    // Category labels come from the existing preference relationship.
    public function getCategoryAttribute(): string
    {
        return implode(', ', $this->categories);
    }

    public function getCategoriesAttribute(): array
    {
        return $this->preferences->pluck('category_name')->all();
    }
}
