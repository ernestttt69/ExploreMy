<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attraction extends Model
{
    protected $table = 'attractions';

    protected $primaryKey = 'attraction_id';

    public $timestamps = false;

    public function images()
    {
        return $this->hasMany(
            AttractionImage::class,
            'attraction_id',
            'attraction_id'
        );
    }

    public function state()
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
}