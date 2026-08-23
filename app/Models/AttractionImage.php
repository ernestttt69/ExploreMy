<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttractionImage extends Model
{
    protected $table = 'attraction_image';

    protected $primaryKey = 'image_id';

    public $timestamps = false;

    protected $fillable = [
        'attraction_id',
        'image_path',
    ];

    public function attraction(): BelongsTo
    {
        return $this->belongsTo(
            Attraction::class,
            'attraction_id',
            'attraction_id'
        );
    }
}