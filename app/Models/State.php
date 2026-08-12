<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;

    protected $primaryKey = 'state_id';

    protected $fillable = [
        'state_name',
        'state_description',
    ];

    public function attractions()
    {
        return $this->hasMany(Attraction::class, 'state_id', 'state_id');
    }
}