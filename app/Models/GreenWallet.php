<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreenWallet extends Model
{
    protected $fillable = ['user_id', 'points'];
    protected $primaryKey = 'user_id';
    public function getIncrementing(): bool { return false; }
}
