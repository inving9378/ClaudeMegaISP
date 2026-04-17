<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItemCustomModel extends Model
{
    use HasFactory;
    protected $guarded = [];


    public function inventory_item_type()
    {
        return $this->belongsTo(InventoryItemType::class);
    }


    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action') {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        } elseif (!empty($filter)) {
            $query =  $query->where(function ($query) use ($filter, $search, $columns) {
                // Aplica los filtros
                // foreach ($filter as $field => $values) {

                // }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
        return $query;
    }
}
