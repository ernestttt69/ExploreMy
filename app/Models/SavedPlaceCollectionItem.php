<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPlaceCollectionItem extends Model
{
    protected $table = 'saved_place_collection_items';
    protected $primaryKey = 'collection_item_id';

    protected $fillable = ['collection_id', 'wishlist_id'];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SavedPlaceCollection::class, 'collection_id', 'collection_id');
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class, 'wishlist_id', 'wishlist_id');
    }
}
