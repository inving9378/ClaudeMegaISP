<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralAccountingIncome extends Model
{
    use HasFactory;

    protected $table = 'general_accounting_incomes';

    protected $guarded = [];

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        } elseif (!empty($filter)) {
            return $query->where(function ($query) use ($filter) {
                foreach ($filter as $field => $values) {
                    if ($field === 'category' && !empty($values)) {
                        $normalized = array_map(function ($v) {
                            $v = str_replace('_', ' ', $v);
                            $v = strtolower($v);
                            return ucwords($v);
                        }, $values);

                        if (!in_array('Total Ingresos', $normalized, true)) {
                            $query->whereIn('category', $normalized);
                        }
                    }
                    if ($field == "created_at" && is_array($values) && !empty($values)) {
                        $from = Carbon::parse($values[0])->startOfDay();
                        $to = $values[1];
                        if (!$to) {
                            $to = Carbon::now()->endOfDay();
                        } else {
                            $to = Carbon::parse($values[1])->endOfDay();
                        }
                        $query->whereBetween('created_at', [$from, $to]);
                    }

                    if ($field != "created_at") {
                        $from = now()->startOfMonth();
                        $to   = now()->endOfMonth();
                        $query->whereBetween('created_at', [$from, $to]);
                    }
                }
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
    }
}
