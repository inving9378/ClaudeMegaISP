<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientInternetService;
use App\Models\TypeBilling;

class ClientInternetServiceRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientInternetService::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getServiceFilterById($id)
    {
        return $this->model->where('id', $id)->first();
    }

    public function getClientsByInternetId($internetId)
    {
        return $this->model->where('internet_id', $internetId)
            ->whereHas('client')
            ->whereNull('client_bundle_service_id')
            ->pluck('client_id');
    }


    public function getServiceFilterByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function getServicesInternetsWhereDoesntHaveIpAndNotBundleService()
    {
        return $this->model->whereDoesntHave('network_ip_used_by')->servicioNoPerteneceAUnPaquete()->get();
    }

    public function getClientInternetServiceByClientBundleServiceId($clientBundleServiceId)
    {
        return $this->model->where('client_bundle_service_id', $clientBundleServiceId)->get();
    }

    public function obtenerServiciosDeInternetActivosDesplegadosYPagados()
    {
        return $this->model->with('router.mikrotik', 'internet', 'network_ip.network', 'client.client_main_information', 'client.balance', 'client.billing_configuration')
            ->leftJoin('clients', 'client_internet_services.client_id', '=', 'clients.id')
            ->leftJoin('billing_configurations', 'clients.id', '=', 'billing_configurations.client_id')
            ->select('client_internet_services.*')
            ->activo()
            ->deployed()
            ->charged();
    }

    public function obtenerServiciosDeInternetActivosDesplegadosYPagadosYNoPertenecenAPaquetes()
    {
        return $this
            ->obtenerServiciosDeInternetActivosDesplegadosYPagados()
            ->servicioNoPerteneceAUnPaquete();
    }

    public function obtenerServiciosDeInternetActivosDesplegadosYPagadosYPertenecenAPaquetes()
    {
        return $this
            ->obtenerServiciosDeInternetActivosDesplegadosYPagados()
            ->servicioPerteneceAunPaquete();
    }

    public function obtenerServiciosDeInternetActivosDesplegadosYPagadosYNoPertenecenAPaquetesQueEstanEnElAddressList()
    {
        return $this
            ->obtenerServiciosDeInternetActivosDesplegadosYPagadosYNoPertenecenAPaquetes()
            ->esteEnElAddressList();
    }

    public function obtenerServiciosDeInternetActivosDesplegadosYPagadosYPertenecenAPaquetesQueEstanEnElAddressList()
    {
        return $this
            ->obtenerServiciosDeInternetActivosDesplegadosYPagadosYPertenecenAPaquetes()
            ->esteEnElAddressList();
    }

    public function obtenerServiciosDeInternetActivosDesplegadosYPagadosYNoPertenecenAPaquetesQueNoEstanEnElAddressList()
    {
        return $this
            ->obtenerServiciosDeInternetActivosDesplegadosYPagadosYNoPertenecenAPaquetes()
            ->noEsteEnElAddressList();
    }

    public function obtenerServiciosDeInternetActivosDesplegadosYPagadosYPertenecenAPaquetesQueNoEstanEnElAddressList()
    {
        return $this
            ->obtenerServiciosDeInternetActivosDesplegadosYPagadosYPertenecenAPaquetes()
            ->noEsteEnElAddressList();
    }

    public function servicesToSuspend()
    {
        $services = $this->obtenerServiciosDeInternetActivosDesplegadosYPagadosYNoPertenecenAPaquetesQueNoEstanEnElAddressList()
            ->where(function ($query) {
                //  Daily
                $query->where(function ($query) {
                    $query->isClientTypeOfBilling(TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY)
                        ->getClientDontHaveClientPaymentToday()
                        ->getClientDontHaveTransactionToday();
                })
                    //Custom
                    ->orWhere(function ($query) {
                        $query->isClientTypeOfBilling(TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM)
                            ->tiempoDeFacturacionEsHoyOYapaso()
                            ->where(function ($query) {
                                $query->whereNull('billing_configurations.grace_period')
                                    ->orWhereDoesntHave('client.client_grace_period')
                                    ->getIsGracePeriodExpired();
                            });
                    })
                    //Recurrent
                    ->orWhere(function ($query) {
                        $query->isClientTypeOfBilling(TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT)
                            ->tiempoDeFacturacionEsHoyOYapaso()
                            ->where(function ($query) {
                                $query->whereNull('billing_configurations.grace_period')
                                    ->orWhereDoesntHave('client.client_grace_period')
                                    ->getIsGracePeriodExpired();
                            });
                    });
            })
            ->orWhere(function ($query) {
                $query->isClientTypeOfBilling(TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM);
                $query->whereHas('client.payments', function ($query) {
                    $query->tienePromesaDePagoActivada()
                        ->tienePromesaDePagoYFechaDeCorteIgualAHoy();
                });
            })
            ->get();
        return $services;
    }

    public function obtenerServiciosQuePertenecenAPaqueteYqueElPaqueteEstaActivo()
    {
        return $this->model->with('router.mikrotik', 'client.client_main_information')
            ->esteEnElAddressList()
            ->servicioPerteneceAunPaquete()
            ->whereHas('bundle_service', function ($query) {
                $query->activo()
                    ->deployed()
                    ->charged();
            });
    }

    public function getServicesWhereClientNotActive()
    {
        return $this->model->with('network_ip', 'client')->whereHas('network_ip')->whereHas('client', function ($query) {
            $query->notActive();
        })->get();
    }

    public function getServicesWhereHasIp()
    {
        return $this->model->with('network_ip_used_by')->whereHas('network_ip_used_by')->get();
    }

    public function setDeployedTrueAndActiveService($service)
    {
        $service->update(['deployed' => ComunConstantsController::IS_NUMERICAL_TRUE, 'estado' => ComunConstantsController::STATE_ACTIVE]);
    }

    public function suspendService($service)
    {
        $service->update([
            'deployed' => ComunConstantsController::IS_NUMERICAL_FALSE,
            'estado' => ComunConstantsController::STATE_PENDING,
            'charged' => ComunConstantsController::IS_NUMERICAL_FALSE
        ]);
    }

    public function getServicesWhereClientNotActiveFilterByRouter(\App\Models\Router $router)
    {
        return $this->model->with('network_ip_used_by', 'client')
            ->whereHas('network_ip_used_by')
            ->where('router_id', $router->id)
            ->whereHas('client', function ($query) {
                $query->notActive();
            })->get();
    }
}
