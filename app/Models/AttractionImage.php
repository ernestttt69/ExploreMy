<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttractionImage extends Model
{
    use HasFactory;

    // Matches your exact table name
    protected $table = 'attraction_image';

    protected $primaryKey = 'image_id';

    // Set to false because your schema does not have created_at / updated_at
    public $timestamps = false;

    protected $fillable = [
        'attraction_id',
        'image_path',
    ];

    public function attraction()
    {
        return $this->belongsTo(Attraction::class, 'attraction_id', 'attraction_id');
    }
}