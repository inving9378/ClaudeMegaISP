<?php

namespace App\Http\Repository;

use App\Models\MikrotikClientPpoe;

class MikrotikClientPpoeRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = MikrotikClientPpoe::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelFilterByMikrotikIdAndClientId($clientId,$mikrotikId){
        return $this->model->where('client_id', '=', $clientId)
            ->where('mikrotik_id', '=', $mikrotikId)
            ->first();
    }



}
