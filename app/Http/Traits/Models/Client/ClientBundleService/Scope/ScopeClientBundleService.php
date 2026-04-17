<?php

namespace App\Http\Traits\Models\Client\ClientBundleService\Scope;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientInternetService;
use Carbon\Carbon;

trait ScopeClientBundleService
{
    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (collect($columns)->filter(function ($value) {
                    return $value != 'action';
                })->toArray() as $value) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }

    public function scopeActivo($query)
    {
        return $query->where('client_bundle_services.estado', ComunConstantsController::STATE_ACTIVE);
    }

    public function scopeCharged($query)
    {
        $query->where('client_bundle_services.charged', '=', ComunConstantsController::IS_NUMERICAL_TRUE);
    }

    public function scopeDeployed($query)
    {
        $query->where('client_bundle_services.deployed', '=', ComunConstantsController::IS_NUMERICAL_TRUE);
    }

    public function scopeIsClientTypeOfBilling($query, $typeOfBilling)
    {
        $query->whereHas('client.client_main_information', function ($query) use ($typeOfBilling) {
            $query->where('type_of_billing_id', $typeOfBilling);
        });
    }

    public function scopeGetClientActiveBillingToday($query)
    {
        $query->whereHas('client.billing_configuration', function ($query) {
            $query->where('billing_activated', '=', ComunConstantsController::IS_NUMERICAL_TRUE)
                ->where('billing_date', (int)Carbon::now()->format('d'));
        });
    }

    public function scopeGetClientDontHaveClientPaymentToday($query)
    {
        $query->whereDoesntHave('client_payment_service', function ($query) {
            $query->whereDate('created_at', Carbon::now()->toDateString());
        });
    }

    public function scopeGetClientDontHaveTransactionToday($query)
    {
        $query->whereDoesntHave('transactions', function ($query) {
            $query->whereDate('created_at', Carbon::now()->toDateString());
        });
    }

    public function scopeGetClientDontHaveTransactionAMonthAgo($query)
    {
        $query->whereHas('transactions', function ($query) {
            $query->whereRaw('DATE(created_at) <= DATE(DATE_SUB(now(), INTERVAL billing_configurations.period MONTH))');
        });
    }
    public function scopeGetServicePaymentAMonthAgo($query)
    {
        $query->whereHas('client_payment_service', function ($query) {
            $query->whereRaw('DATE(created_at) <= DATE(DATE_SUB(now(),INTERVAL 1 MONTH))');
        });
    }

    public function scopeGetIsGracePeriodExpired($query)
    {
        $query->orWhereHas('client.client_grace_period', function ($query) {
            $query->whereRaw('DATE(created_at) >= DATE(DATE_SUB(now(), INTERVAL billing_configurations.grace_period Day))');
        });
    }

    public function scopeGetIsClientEstado($query, $estado)
    {
        $query->whereHas('client.client_main_information', function ($query) use ($estado) {
            $query->where('estado', '=', $estado);
        });
    }

    public function scopePerteneceAClienteConBillingConfiguration($query)
    {
        $query->whereHas('client.billing_configuration', function ($query) {
            $query->where('billing_activated', '=', ComunConstantsController::IS_NUMERICAL_TRUE);
        });
    }

    public function scopePending($query)
    {
        $query->where('estado', '=', ComunConstantsController::STATE_PENDING);
    }

    public function scopeBlocked($query)
    {
        $query->where('estado', '=', ComunConstantsController::STATE_BLOCKED);
    }

    public function scopeQueEsteEnUnPeriodoDeTiempoValido($query)
    {
        $query->where('contract_start_date', '<=', \Carbon\Carbon::now()->format('Y-m-d\TH:i'))
            ->where(function ($query) {
                $query->whereNull('contract_end_date')
                    ->orWhere('contract_end_date', '>=', \Carbon\Carbon::now()->format('Y-m-d\TH:i'));
            });
    }

    public function scopeNoEstaCobrado($query)
    {
        return $query->where('charged', '=', ComunConstantsController::IS_NUMERICAL_FALSE);
    }

    public function scopeNoEsteDesplegado($query)
    {
        return $query->where('deployed', '=', ComunConstantsController::IS_NUMERICAL_FALSE);
    }

    public function scopeOQueTengaServiciosDeInternetSinDesplegar($query)
    {
        $query->orWhereHas('service_internet', function ($query) {
            $query->where('charged', '=', ComunConstantsController::IS_NUMERICAL_FALSE);
        });
    }

    public function scopeTieneServicioInternetEnElAddressList($query)
    {
        $query->whereHas('service_internet.service_in_address_list');
    }

    public function scopeFilterById($query, $id)
    {
        return $query->where('id', $id);
    }

    public function scopeGetRelations($query, array $relations)
    {
        return $query->with($relations);
    }

    public function scopeTiempoDeFacturacionEsHoyOYapaso($query)
    {
        $query->whereHas('client', function ($query) {
            $query->tocaPagarHoyOYaPasoLaFechaDeCorte()
                ->orWhereNull('fecha_corte');
        });
    }
}
