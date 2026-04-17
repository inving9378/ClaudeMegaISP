<?php

namespace App\Http\Traits\Models\Client\ClientCustomService\Scope;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientInternetService;
use Carbon\Carbon;

trait ScopeClientCustomService
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

    public function scopePerteneceAClienteConBillingConfiguration($query)
    {
        $query->whereHas('client.billing_configuration', function ($query) {
            $query->where('billing_activated', '=', ComunConstantsController::IS_NUMERICAL_TRUE);
        });
    }

    public function scopeServicioNoPerteneceAUnPaquete($query)
    {
        $query->whereDoesntHave('bundle_service');
    }

    public function scopeActivo($query)
    {
        $query->where('estado', '=', ComunConstantsController::STATE_ACTIVE);
    }

    public function scopeQueEsteEnUnPeriodoDeTiempoValido($query)
    {
        $query->where('start_date', '<=', \Carbon\Carbon::now()->format('Y-m-d\TH:i'))
            ->where(function ($query) {
                $query->whereNull('finish_date')
                    ->orWhere('finish_date', '>=', \Carbon\Carbon::now()->format('Y-m-d\TH:i'));
            });
    }

    public function scopeNoEstaCobrado($query)
    {
        return $query->where('charged', '=', ComunConstantsController::IS_NUMERICAL_FALSE);
    }

    public function scopeNoEstaDesplegado($query)
    {
        return $query->where('deployed', '=', ComunConstantsController::IS_NUMERICAL_FALSE);
    }

    public function scopePending($query)
    {
        $query->where('estado', '=', ComunConstantsController::STATE_PENDING);
    }

    public function scopeBlocked($query)
    {
        $query->where('estado', '=', ComunConstantsController::STATE_BLOCKED);
    }

    public function scopeDeployed($query)
    {
        return $query->where('deployed', '=', ComunConstantsController::IS_NUMERICAL_TRUE);
    }


    public function scopeGetIsGracePeriodExpired($query)
    {
        $query->orWhereHas('client.client_grace_period', function ($query) {
            $query->whereRaw('DATE(created_at) >= DATE(DATE_SUB(now(), INTERVAL billing_configurations.grace_period Day))');
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

    public function scopeIsClientTypeOfBilling($query, $typeOfBilling)
    {
        $query->whereHas('client.client_main_information', function ($query) use ($typeOfBilling) {
            $query->where('type_of_billing_id', $typeOfBilling);
        });
    }

    public function scopeCharged($query)
    {
        $query->where('charged', '=', ComunConstantsController::IS_NUMERICAL_TRUE);
    }

    public function scopeTiempoDeFacturacionEsHoyOYapaso($query)
    {
        $query->whereHas('client', function ($query) {
            $query->tocaPagarHoyOYaPasoLaFechaDeCorte()
                ->orWhereNull('fecha_corte');
        });
    }

    public function scopeTypeOfPaymentIsNotUnique($query)
    {
        $query->where('payment_type', '!=', 'Pago unico');
    }


    public function scopeEsteEnElAddressList($query)
    {
        $query->whereHas('service_in_address_list');
    }

    public function scopeNoEsteEnElAddressList($query)
    {
        $query->whereDoesntHave('service_in_address_list');
    }
}
