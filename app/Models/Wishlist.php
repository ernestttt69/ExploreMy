<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $table = 'wishlists';

    protected $primaryKey = 'wishlist_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'attraction_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function attraction()
    {
        return $this->belongsTo(Attraction::class, 'attraction_id', 'attraction_id');
    }
}