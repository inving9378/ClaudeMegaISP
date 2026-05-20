<?php

namespace App\Modules\Core\Clientes\Repositories;

use App\Modules\Core\Clientes\Models\ClientGracePeriod;

class ClientGracePeriodRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientGracePeriod::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelByServiceableId($serviceableId){
        return $this->model->where('serviceable_id',$serviceableId)->first();
    }


    public function create($array){
        return $this->model->create($array);
    }
}
