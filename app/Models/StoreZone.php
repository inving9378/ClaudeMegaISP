<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StoreZone extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function store()
    {
        return $this->belongsTo(InventoryStore::class, 'store_id');
    }


    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query = $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    $query->orWhere('store_zones.' . $value, 'like', '%' . $search . '%');
                }
            });
        } elseif (!empty($filter)) {
            $query =  $query->where(function ($query) use ($filter, $search, $columns) {
                // Aplica los filtros
                foreach ($filter as $field => $values) {
                    if ($field == 'store_id' && !empty($values)) {
                        $query->whereHas('store', function ($query) use ($values) {
                            $query->whereIn('id', $values);
                        });
                    }
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {

                    $query->orWhere('store_zones.' . $value, 'like', '%' . $search . '%');
                }
            });
        }

        return $query;
    }
}
