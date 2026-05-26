<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'inventory_item_id',
        'inventory_item_stock_id',
        'base_price',
        'price',
        'bulk_price',
        'bulk_min_quantity',
        'is_active',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'base_price'        => 'float',
        'price'             => 'float',
        'bulk_price'        => 'float',
        'bulk_min_quantity' => 'integer',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryItemStock()
    {
        return $this->belongsTo(InventoryItemStock::class);
    }

    public function records()
    {
        return $this->hasMany(SupplierProductPriceRecord::class)->orderByDesc('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (!empty($search)) {
            $query->where(function ($q) use ($search, $columns) {
                foreach ($columns as $col) {
                    if ($col === 'action' || $col === 'id' || $col === 'media') continue;
                    if (strpos($col, '.') !== false) {
                        [$relation, $field] = explode('.', $col, 2);
                        $relationCamel = \Illuminate\Support\Str::camel($relation);
                        $q->orWhereHas($relationCamel, fn($sub) =>
                            $sub->where($field, 'like', "%{$search}%")
                        );
                    } elseif ($col === 'inventory_item_id') {
                        $q->orWhereHas('inventoryItem', fn($sub) =>
                            $sub->where('name', 'like', "%{$search}%")
                        );
                    } else {
                        $q->orWhere($col, 'like', "%{$search}%");
                    }
                }
            });
        }

        if (!empty($filter)) {
            if (!empty($filter['supplier_id'])) {
                $query->where('supplier_id', $filter['supplier_id']);
            }
            if (isset($filter['is_active'])) {
                $query->where('is_active', filter_var($filter['is_active'], FILTER_VALIDATE_BOOLEAN));
            } else {
                $query->where('is_active', true);
            }
            if (!empty($filter['inventory_item_id'])) {
                $query->where('inventory_item_id', $filter['inventory_item_id']);
            }
        } else {
            $query->where('is_active', true);
        }

        return $query;
    }
}
