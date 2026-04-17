<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStore extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];


    public function inventory_item_stocks()
    {
        return $this->morphMany(InventoryItemStock::class, 'modelable');
    }


    public function inventory_items()
    {
        return $this->morphToMany(InventoryItem::class, 'modelable', 'inventory_item_stocks', 'modelable_id', 'inventory_item_id')
            ->withPivot('current_stock');
    }

    public function inventoryItemZones()
    {
        return $this->hasMany(InventoryItemStoreZone::class, 'inventory_store_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action') {
                        if ($value == 'user_id') {
                            $query->orWhereHas('user', function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%');
                            });
                        } else {
                            $query->orWhere($value, 'like', '%' . $search . '%');
                        }
                    }
                }
            });
        } elseif (!empty($filter)) {
            $query->where(function ($query) use ($filter, $search, $columns) {
                foreach ($filter as $key => $values) {
                    foreach ($values as $keyV => $val) {
                        if ($val == 'null') {
                            $query = $query;
                            break;
                        }
                        $query->where($keyV, $val)
                            ->where(function ($query) use ($search, $columns) {
                                foreach (
                                    collect($columns)->filter(function ($value) {
                                        return $value != 'action';
                                    })->toArray() as $value
                                ) {
                                    if ($value == 'user_id') {
                                        $query->orWhereHas('user', function ($query) use ($search) {
                                            $query->where('name', 'like', '%' . $search . '%');
                                        });
                                    } else {
                                        $query->orWhere($value, 'like', '%' . $search . '%');
                                    }
                                }
                            });
                    }
                }
            });
        }
        return $query;
    }

    public function scopeStoreHasItem($query)
    {
        return $query->whereHas('inventory_item_stocks', function ($query) {
            $query->where('current_stock', '>', 0);
        });
    }
}
