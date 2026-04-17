<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientVozService;

class ClientVozServiceRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientVozService::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getServiceFilterById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function getServiceFilterByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function getServiceFilterByClientBundleServiceId($bundleServiceId)
    {
        return $this->model->where('client_bundle_service_id', $bundleServiceId)->get();
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

    public function getClientsByVozId($vozId)
    {
        return $this->model->where('voz_id', $vozId)
            ->whereHas('client')
            ->whereNull('client_bundle_service_id')
            ->pluck('client_id');
    }
}
