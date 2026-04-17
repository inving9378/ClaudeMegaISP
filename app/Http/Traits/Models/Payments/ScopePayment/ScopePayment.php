<?php

namespace App\Http\Traits\Models\Payments\ScopePayment;

use App\Http\Controllers\Utils\ComunConstantsController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait ScopePayment
{
    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    // Aquí necesitamos especificar la tabla correcta para cada columna
                    if (in_array($value, ['id', 'date', 'amount', 'payment_period', 'comment', 'paymentable_type'])) {
                        $query->orWhere('payments.' . $value, 'like', '%' . $search . '%');
                    } elseif ($value == 'payment_method_id') {
                        $query->whereHas('payment_method', function ($query) use ($search) {
                            $query->orWhere('method_of_payments.type', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'paymentable_id') {
                        $query->whereHas('client_main_information', function ($query) use ($search) {
                            $query->where(DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name)"), 'like', '%' . $search . '%');
                        });
                    } else {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        } elseif (!empty($filter)) {
            return $query->where(function ($query) use ($filter) {
                // Aplica los filtros
                foreach ($filter as $field => $values) {
                    if ($field == 'payment_method_id' && is_array($values) && !empty($values)) {
                        $query->whereHas('payment_method', function ($query) use ($values) {
                            $query->whereIn('id', $values);
                        });
                    }

                    if ($field == "date" && is_array($values) && !empty($values)) {
                        $from = Carbon::parse($values[0])->format('Y-m-d');
                        $to = $values[1];
                        if (!$to) {
                            $to = Carbon::now()->format('Y-m-d');
                        } else {
                            $to = Carbon::parse($values[1])->format('Y-m-d');
                        }
                        $query->whereDate('payments.date', '>=', $from)
                            ->whereDate('payments.date', '<=', $to);
                    }
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    if (in_array($value, ['id', 'date', 'amount', 'payment_period', 'comment', 'paymentable_type'])) {
                        $query->orWhere('payments.' . $value, 'like', '%' . $search . '%');
                    } elseif ($value == 'payment_method_id') {
                        $query->whereHas('payment_method', function ($query) use ($search) {
                            $query->orWhere('method_of_payments.type', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'paymentable_id') {
                        $query->whereHas('client_main_information', function ($query) use ($search) {
                            $query->where(DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name)"), 'like', '%' . $search . '%');
                        });
                    } else {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        }
    }

    public function scopeTienePromesaDePagoActivada($query)
    {
        $query->where('enabled_payment_promise', ComunConstantsController::IS_NUMERICAL_TRUE);
    }

    public function scopeTienePromesaDePagoYFechaDeCorteIgualAHoy($query)
    {
        $query->whereHas('payment_promise', function ($query) {
            $query->whereDate('court_date', '=', \Carbon\Carbon::now()->format('Y-m-d'));
        });
    }
}
