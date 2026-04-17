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
        $clientMainInformationService = new ClientMainInformationService($this->model->client_id);
        $clientMainInformationService->setStateActive();

        $service = $this->model->getService();
        $service = new $service($this->model);
        $service->deploy();
    }
}
