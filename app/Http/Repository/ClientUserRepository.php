<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientUser;

class ClientUserRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientUser::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getModelByServiceId($id)
    {
        return $this->model->where('service_id', $id)->first();
    }


    public function getClientUserByUserAndRouterId($user, $routerId)
    {
        return $this->model->where('user', $user)->where('router_id', $routerId)->first();
    }


    // SETTERS

    public function create($user, $routerId, $serviceId, $clientId)
    {
        return $this->model->create([
            'user' => $user,
            'router_id' => $routerId,
            'service_id' => $serviceId,
            'client_id' => $clientId
        ]);
    }
}
