<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attraction extends Model
{
    protected $primaryKey = 'attraction_id';
    public $timestamps = true;
    protected $fillable = ['place_id', 'state_id', 'attraction_name', 'category', 'description', 'location', 'operating_hours', 'entrance_fee', 'budget_level', 'nearby_transport', 'rating', 'image_path'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id', 'state_id');
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_attractions', 'attraction_id', 'user_id')->withTimestamps();
    }

    public function getCategoriesAttribute(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->category ?? ''))));
    }
}
