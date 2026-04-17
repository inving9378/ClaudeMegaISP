<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItemCustom extends Model
{
    use HasFactory;
    protected $table = "inventory_items";



    public function scopeFilters($query, $columns, $search = null, $filters = null)
    {
        // 1. Agrupar la búsqueda general para no romper la lógica de los filtros
        if (!empty($search)) {
            $query->where(function ($q) use ($search, $columns) {
                foreach ($columns as $column) {
                    if ($column === 'action') continue;
                    $q->orWhere("inventory_items.{$column}", 'like', "%{$search}%");
                }
            });
        }
        // 2. Aplicar Filtros Específicos (Usar un array de mapeo para evitar IFs repetitivos)
        if (!empty($filters)) {
            foreach ($filters as $field => $values) {
                if (empty($values)) continue;     
                switch ($field) { 
                    case 'inventory_item_custom_model_id':
                        $query->whereIn('inventory_item_custom_model_id', (array)$values);
                        break;
                }          
            }
        }

        return $query;
    }
}
