<?php

namespace App\Http\Traits\Models\Client\Client;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\CommandConfigRepository;
use App\Models\ClientMainInformation;
use App\Models\TypeBilling;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait ScopeClient
{
    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action' && $value !== 'full_name') {
                        // Añade el alias para evitar ambigüedad
                        if (strpos($value, '.') === false) {
                            $value = 'client_main_information.' . $value;
                        }
                        if (strpos($value, 'nomenclature_name')) {
                            $value = 'nomenclatures.name';
                        }
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                    if ($value === 'full_name') {
                        $searchTerms = explode(' ', $search);
                        $query->orWhere(function ($q) use ($searchTerms) {
                            foreach ($searchTerms as $term) {
                                $q->where(DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name)"), 'like', '%' . $term . '%');
                            }
                        });
                    }
                }
                $query->orWhere(DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name)"), 'like', '%' . $search . '%');
            });
        } elseif (!empty($filter)) {
            $query->where(function ($query) use ($filter, $search, $columns) {
                foreach ($filter as $key => $values) {
                    foreach ($values as $keyV => $val) {
                        if ($val == 'null') {
                            $query = $query;
                            break;
                        }
                        if ($keyV === 'fecha_corte') {
                            if (is_array($val) && count($val) == 2) {
                                $query->whereDate('clients.fecha_corte', '>=', Carbon::parse($val[0])->subDay()->format('Y-m-d'))
                                    ->whereDate('clients.fecha_corte', '<', Carbon::parse($val[1])->format('Y-m-d'));
                            }
                        } elseif ($keyV === 'fecha_pago_today') {
                            $query->whereDate('clients.fecha_pago', $val);
                        } elseif ($keyV === 'fecha_corte_today') {
                            $query->whereDate('clients.fecha_corte', $val);
                        } elseif ($keyV === 'bundle_id') {
                            $query = $query->whereHas('bundle_service', function ($query) use ($keyV, $val) {
                                $query->where($keyV, $val);
                            })->with('bundle_service');
                        } elseif ($keyV === 'internet_id') {
                            $query = $query->whereHas('internet_service', function ($query) use ($keyV, $val) {
                                $query->where($keyV, $val)
                                    ->whereNull('client_bundle_service_id');
                            })->with('internet_service');
                        } elseif ($keyV === 'custom_id') {
                            $query = $query->whereHas('custom_service', function ($query) use ($keyV, $val) {
                                $query->where($keyV, $val)
                                    ->whereNull('client_bundle_service_id');
                            })->with('custom_service');
                        } elseif ($keyV === 'client_main_information.estado') {
                            $query = $query->whereHas('client_main_information', function ($query) use ($keyV, $val) {
                                $query->whereIn($keyV, $val);
                            })->with('client_main_information');
                        } elseif ($keyV === 'voz_id') {
                            $query = $query->whereHas('voz_service', function ($query) use ($keyV, $val) {
                                $query->where($keyV, $val)
                                    ->whereNull('client_bundle_service_id');
                            })->with('voz_service');
                        } else {
                            if (is_array($val)) {
                                foreach ($val as $v) {
                                    $query->orWhere($keyV, $v)
                                        ->where(function ($query) use ($search, $columns) {
                                            foreach ($columns as $value) {
                                                if ($value !== 'action' && $value !== 'full_name') {
                                                    // Añade el alias para evitar ambigüedad
                                                    if (strpos($value, '.') === false) {
                                                        $value = 'client_main_information.' . $value;
                                                    }

                                                    if (strpos($value, 'nomenclature_name')) {
                                                        $value = 'nomenclatures.name';
                                                    }
                                                    $query->orWhere($value, 'like', '%' . $search . '%');
                                                }
                                                if ($value === 'full_name') {
                                                    $searchTerms = explode(' ', $search);
                                                    $query->orWhere(function ($q) use ($searchTerms) {
                                                        foreach ($searchTerms as $term) {
                                                            $q->where(DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name)"), 'like', '%' . $term . '%');
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                }
                            } else {
                                $query->where($keyV, $val)
                                    ->where(function ($query) use ($search, $columns) {
                                        foreach ($columns as $value) {
                                            if ($value !== 'action' && $value !== 'full_name') {
                                                // Añade el alias para evitar ambigüedad
                                                if (strpos($value, '.') === false) {
                                                    $value = 'client_main_information.' . $value;
                                                }
                                                if (strpos($value, 'nomenclature_name')) {
                                                    $value = 'nomenclatures.name';
                                                }
                                                $query->orWhere($value, 'like', '%' . $search . '%');
                                            }
                                            if ($value === 'full_name') {
                                                $searchTerms = explode(' ', $search);
                                                $query->orWhere(function ($q) use ($searchTerms) {
                                                    foreach ($searchTerms as $term) {
                                                        $q->where(DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name)"), 'like', '%' . $term . '%');
                                                    }
                                                });
                                            }
                                        }
                                    });
                            }
                        }
                    }
                }
            });
        }
    }

    public function scopeNoTenganPlanAdministrador($query)
    {
        $query->whereDoesntHave('internet_service', function ($query) {
            $query->whereHas('internet', function ($query) {
                $query->where('title', 'administracion');
            });
        });
    }

    public function scopeTenganPlanAdministrador($query)
    {
        $query->whereHas('internet_service', function ($query) {
            $query->whereHas('internet', function ($query) {
                $query->where('title', 'administracion');
            });
        });
    }

    public function scopeActive($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->stateActive();
        });
    }

    public function scopeNotActive($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->notActive();
        });
    }

    public function scopeNoEsInactivo($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->stateNotInActive();
        });
    }

    public function scopeTypeBillingRecurrent($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
        });
    }

    public function scopeTypeBillingNotRecurrent($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->where('type_of_billing_id', '!=', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
        });
    }



    public function scopeNegativeBalance($query)
    {
        $query->whereHas('balance', function ($query) {
            $query->negativeBalance();
        });
    }

    public function scopePositiveBalance($query)
    {
        $query->whereHas('balance', function ($query) {
            $query->positiveBalance();
        });
    }

    public function scopeActiveGracePeriod($query)
    {
        $query->whereNotNull('fecha_fin_periodo_gracia')->orWhere('fecha_fin_periodo_gracia', '!=', '');
    }

    public function scopeNotActiveGracePeriod($query)
    {
        $query->whereNull('fecha_fin_periodo_gracia');
    }



    public function scopePromisePayment($query)
    {
        $query->whereHas('payments', function ($query) {
            $query->where('enabled_payment_promise', true)
                ->where(function ($query) {
                    $query->whereDate('first_court_date', '<=', \Carbon\Carbon::now()->format('Y-m-d'))
                        ->WhereDate('third_court_date', '>=', \Carbon\Carbon::now()->format('Y-m-d'));
                });
        });
    }

    public function scopeHaveServices($query)
    {
        $query->whereHas('internet_service')
            ->orWhereHas('bundle_service');
    }

    public function scopeTipoRecurrenteYElDiaDeFacturacionSeaHoy($query)
    {
        $query->where(function ($query) {
            $query->whereHas('client_main_information', function ($query) {
                $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
            })
                ->whereHas('billing_configuration', function ($query) {
                    $query->where('billing_date', (int)Carbon::now()->format('d'));
                });
        });
    }

    public function scopeOSeaDeTipoCustomYTocaFacturarHoy($query)
    {
        $query->orWhere(function ($query) {
            $query
                ->whereHas('client_main_information', function ($query) {
                    $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM);
                })
                ->whereHas('payments', function ($query) {
                    $query->whereRaw('DATE(created_at) <= DATE(DATE_SUB(now(),INTERVAL 1 MONTH))');
                });
        });
    }

    public function scopeClienteDeTipoCustom($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM);
        });
    }

    public function scopeClienteDeTipoRecurrente($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
        });
    }

    public function scopeElClienteNoTieneFacturaCreadaEsteMes($query)
    {
        $query->whereHas('client_invoices', function ($query) {
            $query->where(DB::raw('DATE_FORMAT(created_at,"%m")'), '<', Carbon::now()->format('m'));
        })
            ->orWhereDoesntHave('client_invoices');
    }

    public function scopeTipoDiario($query)
    {
        $query->whereHas('client_main_information', function ($query) {
            $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY);
        });
    }

    public function scopeEstado($query, $estado)
    {
        $query->whereHas('client_main_information', function ($query) use ($estado) {
            is_array($estado) ?
                $query->whereIn('estado', $estado) :
                $query->where('estado', $estado);
        });
    }

    public function scopeElClienteNoTieneFacturaCreadaHoy($query)
    {
        $query->whereHas('client_invoices', function ($query) {
            $query->where(DB::raw('DATE_FORMAT(created_at,"%d")'), '<', Carbon::now()->format('d'));
        })
            ->orWhereDoesntHave('client_invoices');
    }


    public function scopeClienteDeTipoRecurrenteHoyDiaDeFacturacion($query)
    {
        $query->whereHas('billing_configuration', function ($query) {
            $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
        });
    }

    public function scopeHasGracePeriod($query)
    {
        $query->whereHas('client_grace_period');
    }

    public function scopeTieneServicioDeIntenetPagoYDesplegado($query)
    {
        $query->whereHas('internet_service', function ($query) {
            $query->where('charged', ComunConstantsController::IS_NUMERICAL_TRUE)
                ->where('deployed', ComunConstantsController::IS_NUMERICAL_TRUE);
        });
    }

    public function scopeOTieneBundleServicePagoYDesplegado($query)
    {
        $query->orWhere(function ($query) {
            $query->whereHas('bundle_service', function ($query) {
                $query->where('charged', 1)
                    ->where('deployed', 1);
            });
        });
    }

    public function scopePeridoDeGraciaVencido($query)
    {
        $query->whereRaw('DATE(client_grace_periods.created_at) = DATE(DATE_SUB(now(), INTERVAL billing_configurations.grace_period Day))');
    }

    public function scopeTocaPagarHoyOYaPasoLaFechaDeCorte($query)
    {
        $query->where('fecha_corte', '<=', Carbon::now());
    }

    public function scopeTocaSuspender($query, $date = null)
    {
        if (!$date) {
            $comandConfigRepository = new CommandConfigRepository();
            $horaPlanificada = $comandConfigRepository->getHourlyToSuspendService();
            $date = Carbon::now()->setTimeFromTimeString($horaPlanificada);
        }
        $query
            ->whereNotNull('fecha_corte')
            ->where('active_promise_payment', 0)
            ->whereDate('fecha_corte', '<', $date)
            ->whereHas('client_main_information', function ($query) {
                $query->stateActive();
            });
    }

    public function scopeTocaCobrar($query, $date = null)
    {
        if (!$date) {
            $date = Carbon::now()->toDateTimeString();
        }
        $query
            ->where('active_promise_payment', 0)
            ->whereNotNull('fecha_pago')
            ->whereDate('fecha_pago', '<=', $date);
    }


    public function scopeHavePromisePayment($query)
    {
        $query->where('active_promise_payment', 1);
    }

    public function scopeClientMainInformationName($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', ComunConstantsController::CLIENT_ROLE);
        });
    }

    public function scopeFechaCorteMenorAHoy($query)
    {
        $query->whereDate('fecha_corte', '<', Carbon::today())
            ->whereNotNull('fecha_corte');
    }

    public function scopeUltimoPagoFueEnEsteMes($query)
    {
        $query->whereHas('payments', function ($query) {
            $query->whereRaw('MONTH(created_at) = ?', [Carbon::now()->month])
                ->whereRaw('YEAR(created_at) = ?', [Carbon::now()->year])
                ->orderBy('created_at', 'desc')
                ->limit(1);
        });
    }

    public function scopeUltimoPagoNoFueEnEsteMes($query)
    {
        $query->whereHas('payments', function ($query) {
            $query->whereRaw('MONTH(created_at) != ?', [Carbon::now()->month])
                ->orWhereRaw('YEAR(created_at) != ?', [Carbon::now()->year])
                ->orderBy('created_at', 'desc')
                ->limit(1);
        });
    }

    public function scopeIsDedicated($query)
    {
        $query->whereHas('client_additional_information', function ($query) {
            $query->where('is_dedicated', true);
        });
    }
}
