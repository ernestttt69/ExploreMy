<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attraction extends Model
{
    use HasFactory;

    protected $primaryKey = 'attraction_id';

    protected $fillable = [
        'state_id',
        'attraction_name',
        'description',
        'location',
        'category',
        'entrance_fee',
        'rating',
        'operating_hours',
        'nearby_transport'
    ];

    /**
     * Get all images for the attraction.
     */
    public function images()
    {
        return $this->hasMany(AttractionImage::class, 'attraction_id', 'attraction_id');
    }

    /**
     * Get the state associated with the attraction.
     */
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'state_id');
    }
}