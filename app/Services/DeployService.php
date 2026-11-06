<?php

namespace App\Services;

use App\Models\Interface\ServiceInterface;

class DeployService
{
    protected $model;
    public function __construct(ServiceInterface $model)
    {
        $this->model = $model;
    }

    public function deployService()
    {
        // #1126: un cliente Cancelado/Desinstalado no se reactiva ni se redespliega por automatismos.
        $client = \App\Models\Client::find($this->model->client_id);
        if ($client && ! \App\Services\Finance\Billing\ReglasFacturacion::reactivaConPago($client)) {
            return;
        }
        $clientMainInformationService = new ClientMainInformationService($this->model->client_id);
        $clientMainInformationService->setStateActive();

        $service = $this->model->getService();
        $service = new $service($this->model);
        $service->deploy();
    }
}
