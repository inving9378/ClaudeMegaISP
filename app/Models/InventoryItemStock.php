<?php

namespace App\Models;

use App\Http\Traits\Models\Inventory\InventoryItemStock\ScopeInventoryItemStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItemStock extends Model
{
    use ScopeInventoryItemStock;
    use HasFactory;
    use SoftDeletes;
    protected $table = 'inventory_item_stocks';

    protected $fillable = [
        'inventory_item_id',
        'modelable_id',
        'modelable_type',
        'current_stock',
        'reserved_stock'
    ];

    protected $appends = ['name_item', 'type_item', 'zone', 'first_image_url'];

    public function inventory_item()
    {
        return $this->belongsTo(InventoryItem::class);
    }
    public function medias()
    {
        return $this->hasMany(InventoryItemMedia::class, 'inventory_item_stock_id');
    }

    public function modelable()
    {
        return $this->morphTo();
    }

    public function getNameItemAttribute()
    {
        return $this->inventory_item->name ?? '';
    }


    public function getTypeItemAttribute()
    {
        return $this->inventory_item->inventory_item_type->type ?? '';
    }

    public function getZoneAttribute()
    {
        if ($this->modelable_type !== 'App\Models\InventoryStore') {
            return '';
        }

        try {
            $inventoryStoreZone = InventoryItemStoreZone::where([
                'inventory_item_id' => $this->inventory_item_id,
                'inventory_store_id' => $this->modelable_id
            ])->first();

            if (!$inventoryStoreZone || !$inventoryStoreZone->store_zone_id) {
                return '';
            }

            $storeZone = StoreZone::find($inventoryStoreZone->store_zone_id);

            return $storeZone ? $storeZone->name : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getFirstImageUrlAttribute()
    {
        $firstImage = $this->medias()->orderBy('order', 'asc')->first();
        if ($firstImage) {
            return $firstImage->url;
        }
        return null;
    }

    public function getReservedStockAttribute()
    {
        $reservedQty = InventoryReservation::where('inventory_item_id', $this->inventory_item_id)
            ->where('modelable_id', $this->modelable_id)
            ->where('modelable_type', $this->modelable_type)
            ->sum('quantity');
        return $reservedQty;
    }
}
