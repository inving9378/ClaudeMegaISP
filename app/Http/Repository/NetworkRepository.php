<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Network;
use App\Models\NetworkIp;
use Illuminate\Support\Facades\Log;

class NetworkRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Network::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getNetworkByNetwork($network)
    {
        return $this->model->where('network', $network)->first();
    }

}
