<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
	use HasFactory, Notifiable;

	protected $table = 'user';

	protected $primaryKey = 'user_id';

    public $timestamps = false;
    
	protected $fillable = [
		'google_id',
		'name',
		'email',
		'profile_picture',
	];

	public function preferenceCategories(): BelongsToMany
	{
		return $this->belongsToMany(
			PreferenceCategory::class,
			'user_preferences',
			'user_id',
			'preference_id'
		);
	}
}
