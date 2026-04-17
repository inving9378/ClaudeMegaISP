<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;

class ClientCustomServiceRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientCustomService::query();
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

    public function getClientCustomServiceByClientBundleServiceId($clientBundleServiceId)
    {
        return $this->model->where('client_bundle_service_id', $clientBundleServiceId)->get();
    }

    public function getIfExistModelByUser($userName)
    {
        return $this->model->where('user', $userName)->exists();
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


    public function getClientsByCustomId($customId)
    {
        return $this->model->where('custom_id', $customId)
            ->whereHas('client')
            ->whereNull('client_bundle_service_id')
            ->pluck('client_id');
    }
}
