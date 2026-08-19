<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttractionPreference extends Model
{
    protected $table = 'attraction_preferences';

    protected $primaryKey = 'attraction_preference_id';

    public $timestamps = false;

    protected $fillable = [
        'attraction_id',
        'preference_id',
    ];

    public function attraction(): BelongsTo
    {
        return $this->belongsTo(
            Attraction::class,
            'attraction_id',
            'attraction_id'
        );
    }

    public function preference(): BelongsTo
    {
        return $this->belongsTo(
            PreferenceCategory::class,
            'preference_id',
            'preference_id'
        );
    }
}