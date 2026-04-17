<?php


namespace App\Http\Traits\Models\Inventory\InventoryMovement;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait ScopeInventoryMovement
{
    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action') {
                        if ($value == 'created_by') {
                            $query->orWhereHas('user', function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%');
                            });
                        } elseif ($value == 'from') {
                            $query->orWhereHas('movementableFrom', function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%')
                                    ->where('type', '!=', 'System');
                            });
                        } elseif ($value == 'to') {
                            $query->orWhereHas('movementableTo', function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%') ->where('type', '!=', 'System');
                            });
                        } else {
                            $query->orWhere($value, 'like', '%' . $search . '%');
                        }
                    }
                }
            });
        } elseif (!empty($filter)) {
            $query =  $query->where(function ($query) use ($filter, $search, $columns) {
                // Aplica los filtros
                foreach ($filter as $field => $values) {

                    if ($field == 'type' && !empty($values)) {
                        $query->whereIn('type', $values);
                    }
                    if ($field == "created_at" && is_array($values) && !empty($values)) {
                        try {
                            // Asegúrate de que el rango de fechas sea válido
                            $from = isset($values[0]) ? Carbon::parse($values[0])->startOfDay() : null;
                            $to = isset($values[1]) ? Carbon::parse($values[1])->endOfDay() : Carbon::now()->endOfDay();

                            if ($from) {
                                $query->whereDate('inventory_movements.created_at', '>=', $from->format('Y-m-d'))
                                    ->whereDate('inventory_movements.created_at', '<=', $to->format('Y-m-d'));
                            }
                        } catch (\Exception $e) {
                            // Manejo de errores en caso de fechas no válidas
                            Log::error("Error parsing dates: " . $e->getMessage());
                        }
                    }
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    if ($value == 'created_by') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'from') {
                        $query->orWhereHas('movementableFrom', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'to') {
                        $query->orWhereHas('movementableTo', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } else {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        }
        return $query;
    }
}
