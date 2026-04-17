<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItemMedia extends Model
{
    use HasFactory;

    protected $table = 'inventory_item_media';
    protected $guarded = [];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            if (auth()->check()) {
                $obj->created_by = auth()->user()->id;
            } else {
                $obj->created_by = 1;
            }
            if ($obj->inventory_item_id) {
                $lastOrder = self::where('inventory_item_id', $obj->inventory_item_id)
                    ->max('order');
                $obj->order = $lastOrder ? $lastOrder + 1 : 1;
            }

            if ($obj->inventory_item_stock_id) {
                $lastOrder = self::where('inventory_item_stock_id', $obj->inventory_item_stock_id)
                    ->max('order');
                $obj->order = $lastOrder ? $lastOrder + 1 : 1;
            }
        });
    }




    public function inventory_item()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventory_item_stock()
    {
        return $this->belongsTo(InventoryItemStock::class);
    }
}
