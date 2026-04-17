<?php


namespace App\Http\Traits\Models\Inventory\InventoryItemStock;

use App\Models\InventoryStore;

trait ScopeInventoryItemStock
{
    public function scopeFilters($query, array $columns, ?string $search = null, array $filter = [])
    {
        // 1. APLICAR FILTROS (MATCH EXACTO)
        $query->when(!empty($filter), function ($q) use ($filter) {
            $q->where(function ($sub) use ($filter) {
                foreach ($filter as $field => $values) {
                    if (empty($values)) continue;

                    match ($field) {
                        // Relación profunda
                        'inventory_item_type_id' => $sub->whereHas('inventory_item.inventory_item_type', fn($item) => $item->whereIn('id', $values)),

                        // Filtros directos sobre la tabla (asumiendo que modelable_id/type están en esta tabla)
                        'inventory_store_id' => $sub->where(function ($q) use ($values) {
                            $q->where('modelable_type', 'App\Models\InventoryStore')->whereIn('modelable_id', $values);
                        }),

                        'user_id' => $sub->where(function ($q) use ($values) {
                            $q->where('modelable_type', 'App\Models\User')->whereIn('modelable_id', $values);
                        }),

                        // Relación simple
                        'inventory_item_custom_model_id' => $sub->whereHas('inventory_item', fn($item) => $item->whereIn('inventory_item_custom_model_id', $values)),

                        default => null
                    };
                }
            });
        });

        // 2. APLICAR BÚSQUEDA GLOBAL (LIKE)
        $query->when($search, function ($q) use ($search, $columns) {
            $q->where(function ($sub) use ($search, $columns) {
                // Agrupamos búsquedas en la relación 'inventory_item' (Una sola subconsulta EXISTS)
                $sub->orWhereHas('inventory_item', function ($item) use ($search, $columns) {
                    $item->where(function ($itemSub) use ($search, $columns) {
                        if (in_array('inventory_item_name', $columns)) $itemSub->orWhere('name', 'like', "%$search%");
                        if (in_array('inventory_item_description', $columns)) $itemSub->orWhere('description', 'like', "%$search%");
                        if (in_array('status_item', $columns)) $itemSub->orWhere('status_item', 'like', "%$search%");
                        if (in_array('serial_number', $columns)) $itemSub->orWhere('serial_number', 'like', "%$search%");

                        if (in_array('type', $columns)) {
                            $itemSub->orWhereHas('inventory_item_type', fn($type) => $type->where('name', 'like', "%$search%"));
                        }
                    });
                });

                // Búsquedas en columnas de la tabla principal
                $exclude = ['inventory_item_name', 'inventory_item_description', 'status_item', 'serial_number', 'type', 'action', 'location', 'zone', 'media', 'reserved_stock'];
                foreach ($columns as $column) {
                    if (!in_array($column, $exclude)) {
                        $sub->orWhere($column, 'like', "%$search%");
                    }
                }
            });
        });

        // 3. SEGURIDAD (RESTRICCIÓN DE ACCESO)
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            $almacen = $this->authUserEsResponsableDeAlmacenDevuelveAlmacen();

            $query->where(function ($q) use ($user, $almacen) {
                // El registro le pertenece al usuario directamente
                $q->where(function ($qUser) use ($user) {
                    $qUser->where('modelable_type', \App\Models\User::class)
                        ->where('modelable_id', $user->id);
                });

                // O el registro pertenece al almacén del cual es responsable
                if ($almacen) {
                    $q->orWhere(function ($qStore) use ($almacen) {
                        $qStore->where('modelable_type', \App\Models\InventoryStore::class)
                            ->where('modelable_id', $almacen->id);
                    });
                }
            });
        }

        return $query;
    }


    private function isResponsableDeAlmacen($userId)
    {
        return InventoryStore::where('user_id', $userId)->exists();
    }

    private function getAlmacenesDelUsuario($userId)
    {
        return InventoryStore::where('user_id', $userId)->pluck('id');
    }

    public function authUserEsResponsableDeAlmacenDevuelveAlmacen()
    {
        $inventory_store = InventoryStore::where('user_id', auth()->user()->id)->first();
        return $inventory_store ? $inventory_store : false;
    }
}
