<?php


namespace App\Http\Traits\Models\Task;

use Carbon\Carbon;

trait ScopeTask
{
    public function scopeFilters($query, $columns, $search = null, $filters = null)
    {
        // 1. Agrupar la búsqueda general para no romper la lógica de los filtros
        if (!empty($search)) {
            $query->where(function ($q) use ($search, $columns) {
                foreach ($columns as $column) {
                    if ($column === 'action' || $column === 'note') continue;

                    if ($column === 'assigned_to') {
                        $q->orWhereHas('users', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                    } elseif ($column === 'client_main_information_id') {
                        // Evitar CONCAT, buscar individualmente es más rápido si hay índices
                        $q->orWhereHas('client_main_information', function ($sq) use ($search) {
                            $terms = explode(' ', $search);
                            foreach ($terms as $term) {
                                $sq->where(function ($nameQuery) use ($term) {
                                    $nameQuery->where('name', 'like', "%{$term}%")
                                        ->orWhere('father_last_name', 'like', "%{$term}%")
                                        ->orWhere('mother_last_name', 'like', "%{$term}%");
                                });
                            }
                        });
                    } else {
                        $q->orWhere("tasks.{$column}", 'like', "%{$search}%");
                    }
                }
            });
        }

        // 2. Aplicar Filtros Específicos (Usar un array de mapeo para evitar IFs repetitivos)
        if (!empty($filters)) {
            foreach ($filters as $field => $values) {
                if (empty($values)) continue;

                switch ($field) {
                    case 'project_id':
                    case 'partner_id':
                    case 'location_id':
                    case 'status':
                    case 'priority':
                    case 'id':
                        $query->whereIn(str_replace('_filter', '', $field), (array)$values);
                        break;

                    case 'assigned':
                    case 'assigned_to':
                    case 'assignuser_filter':
                    case 'assignedAuthUser':
                        $query->whereHas('users', fn($q) => $q->whereIn('users.id', (array)$values));
                        break;

                    case 'periodo':
                    case 'archived_at':
                    case 'finish_at':
                        $column = ($field === 'periodo') ? 'created_at' : "tasks.$field";
                        $from = \Carbon\Carbon::parse($values[0])->startOfDay();
                        $to = isset($values[1]) ? \Carbon\Carbon::parse($values[1])->endOfDay() : now();
                        // whereBetween es más rápido que dos whereDate
                        $query->whereBetween($column, [$from, $to]);
                        break;

                    case 'archived':
                        $query->whereIn('archived', (array)$values);
                        break;
                }
            }
        }

        return $query;
    }

    public function scopeArchive($query)
    {
        $query->where('archived', true);
    }
}
