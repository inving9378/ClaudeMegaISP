<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientUser;
use App\Models\User;

class UserRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = User::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getUserById($id)
    {
        return $this->model->find($id);
    }

    public function getUserAdmin(){
        return $this->model->leadProject()->get();
    }





    // SETTERS



}
