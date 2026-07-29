<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferenceCategory extends Model
{
    protected $table = 'preference_categories';

    protected $primaryKey = 'preference_id';

    public $timestamps = false;

    protected $fillable = [
        'category_name',
    ];
}