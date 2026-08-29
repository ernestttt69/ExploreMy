<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $primaryKey = 'state_id';
    public $timestamps = false;
    protected $fillable = ['state_name'];

    public function attractions()
    {
        return $this->hasMany(Attraction::class, 'state_id', 'state_id');
    }
}
