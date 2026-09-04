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

    public function getLocalizedNameAttribute(): string
    {
        // Category names come from reference data and may contain spaces or
        // symbols (for example "Culture & Heritage"). Map the stable IDs to
        // translation keys instead of deriving an invalid key from the label.
        $translationKeys = [
            1 => 'nature',
            2 => 'adventure',
            3 => 'culture_heritage',
            4 => 'family',
            5 => 'food_drinks',
            6 => 'shopping',
            7 => 'beach',
            8 => 'city',
            9 => 'relaxation',
        ];

        $slug = $translationKeys[(int) $this->preference_id]
            ?? str($this->category_name)->slug('_')->toString();
        $key = 'ui.categories.'.$slug;
        $translated = __($key);

        return $translated === $key ? $this->category_name : $translated;
    }
}
