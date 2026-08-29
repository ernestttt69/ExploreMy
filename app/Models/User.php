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
		'phone',
		'date_of_birth',
		'nationality',
		'bio',
		'preferred_language',
		'email_notifications',
		'personalisation_consent',
		'last_login_at',
		'is_admin',
	];

	protected $casts = [
		'date_of_birth' => 'date',
		'email_notifications' => 'boolean',
		'personalisation_consent' => 'boolean',
		'last_login_at' => 'datetime',
		'is_admin' => 'boolean',
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

	public function savedAttractions(): BelongsToMany
	{
		return $this->belongsToMany(
			Attraction::class,
			'saved_attractions',
			'user_id',
			'attraction_id'
		)->withTimestamps();
	}
}
