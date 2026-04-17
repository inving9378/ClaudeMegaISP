<?php


namespace App\Http\Traits\Models\Inventory\InventoryItem;

trait ScopeInventoryItem
{
    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value == 'current_stock') {
                        $query->whereHas('stocks', function ($query) use ($value, $search) {
                            $query->orWhere($value, 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'inventory_store_id') {
                        //TODO arreglar para que busque por el nombre
                    } elseif ($value == 'location') {
                        //TODO arreglar para que busque por el nombre
                    } else {
                        if ($value !== 'action') {
                            $query->orWhere($value, 'like', '%' . $search . '%');
                        }
                    }
                }
            });
        } elseif (!empty($filter)) {
            $query =  $query->where(function ($query) use ($filter, $search, $columns) {
                // Aplica los filtros
                foreach ($filter as $field => $values) {
                    if ($field == 'inventory_item_type_id' && !empty($values)) {
                        $query->whereHas('inventory_item_type', function ($query) use ($values) {
                            $query->whereIn('id', $values);
                        });
                    }
                    if ($field == 'inventory_store_id' && !empty($values)) {
                        $query->whereHas('stocks', function ($query) use ($values) {
                            $query->where('modelable_type', 'App\Models\InventoryStore')
                                ->whereIn('modelable_id', $values)
                                ->where('current_stock', '>', 0);
                        });
                    }
                    if ($field == 'current_stock' && !empty($values)) {
                        $query->whereHas('stocks', function ($query) use ($values) {
                            $query->whereIn('current_stock', $values);
                        });
                    }

                    if ($field == 'user_id' && !empty($values)) {
                        $query->whereHas('stocks', function ($query) use ($values) {
                            $query->where('modelable_type', 'App\Models\User')
                                ->whereIn('modelable_id', $values)
                                ->where('current_stock', '>', 0);
                        });
                    }

                    if ($field == 'location' && !empty($values)) {

                    }
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    if ($value == 'current_stock') {
                        $query->whereHas('stocks', function ($query) use ($value, $search) {
                            $query->where($value, 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'inventory_store_id') {
                        //TODO arreglar para que busque por el nombre
                    } elseif ($value == 'user_id') {
                        //TODO arreglar para que busque por el nombre
                    } elseif ($value == 'location') {
                        //TODO arreglar para que busque por el nombre
                    } else {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        }
        return $query;
    }

    public function scopeHasStock($query)
    {
        return $query->whereHas('stocks', function ($query) {
            $query->where('current_stock', '>', 0);
        });
    }

    public function scopeIsCustom($query)
    {
        return $query->whereNotNull('inventory_item_custom_model_id');        
    }
}
