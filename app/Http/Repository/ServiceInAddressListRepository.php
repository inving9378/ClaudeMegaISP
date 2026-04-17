<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientMainInformation;
use App\Models\ServiceInAddressList;

class ServiceInAddressListRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ServiceInAddressList::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelById($id)
    {
        return $this->model->where('id', $id)->first();
    }

    // GETTERS

    public function getServicesNotDeployed()
    {
        return $this->model->where('deployed', '=', ComunConstantsController::IS_FALSE)->get();
    }


    // SETTERS


}
