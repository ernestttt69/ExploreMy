<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PreferenceCategory extends Model
{
    protected $table = 'preference_categories';

    protected $primaryKey = 'preference_id';

    public $timestamps = false;

    protected $fillable = [
        'category_name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_preferences',
            'preference_id',
            'user_id'
        );
    }
}
