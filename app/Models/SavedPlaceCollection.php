<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedPlaceCollection extends Model
{
    protected $table = 'saved_place_collections';
    protected $primaryKey = 'collection_id';

    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SavedPlaceCollectionItem::class, 'collection_id', 'collection_id');
    }
}
