<?php

namespace App\Services\ClientService;


class ClientBundleService implements ClientServiceInterface
{
    protected $model;
    public function __construct($model)
    {
        $this->model = $model;
    }

    public function deploy()
    {
        $repository = $this->model->getRepository();
        $repository = new $repository();
        $repository->setDeployedTrueAndActiveService($this->model);
    }

}
