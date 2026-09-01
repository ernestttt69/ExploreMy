<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreenInventory extends Model
{
    protected $table = 'green_inventory';
    protected $fillable = ['user_id', 'shop_item_id', 'quantity'];
    protected $casts = ['quantity' => 'integer'];
    public function item() { return $this->belongsTo(GreenShopItem::class, 'shop_item_id'); }
}
