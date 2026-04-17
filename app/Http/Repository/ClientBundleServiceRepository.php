<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientBundleService;
use App\Models\ClientInternetService;

class ClientBundleServiceRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientBundleService::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getServiceFilterById($id)
    {
        return $this->model->find($id);
    }

    public function getServicesFilterByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->get();
    }


    public function getServiceFilterByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->first();
    }

    public function getNetworkIpUsed($clientBundleServiceId)
    {
        $ips = [];
        if ($clientBundleServiceId) {
            $clientBundleService = $this->model
                ->filterById($clientBundleServiceId)
                ->getRelations(['service_internet.network_ip'])
                ->whereHas('service_internet.network_ip')
                ->first();
            if ($clientBundleService) {
                foreach ($clientBundleService->service_internet as $service) {
                    if ($service->network_ip) {
                        $ips[] = $service->network_ip->id;
                    }
                }
            }
        }
        return $ips;
    }

    public function getNetworkIpUsedByCustom($clientBundleServiceId)
    {
        $ips = [];
        if ($clientBundleServiceId) {
            $clientBundleService = $this->model
                ->filterById($clientBundleServiceId)
                ->getRelations(['service_custom.network_ip'])
                ->whereHas('service_custom.network_ip')
                ->first();
            if ($clientBundleService) {
                foreach ($clientBundleService->service_custom as $service) {
                    if ($service->network_ip) {
                        $ips[] = $service->network_ip->id;
                    }
                }
            }
        }
        return $ips;
    }

    public function getBundlesWhereInternetDoesntHaveIp(){
        return $this->model->with(['service_internet' => function ($query) {
            $query->whereDoesntHave('network_ip_used_by');
        }])->whereHas('service_internet', function ($query) {
            $query->whereDoesntHave('network_ip_used_by');
        })->get();
    }

    public function getBundleServicesByServiceableIdWithInternetServices($serviceableId)
    {
        return $this->model->find($serviceableId)->service_internet()->get();
    }

    public function getModelByIdIdWithInternetServices($id)
    {
        return $this->model->find($id)->service_internet()->get();
    }

    public function getModelByIdIdWithVozServices($id)
    {
        return $this->model->find($id)->service_voz()->get();
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

    public function getClientIdByClientBundleServiceId($id)
    {
        return $this->model->findOrFail($id);
    }

    public function getClientsByBundleId($bundleId)
    {
        return $this->model->where('bundle_id', $bundleId)->whereHas('client')->pluck('client_id');
    }


}
