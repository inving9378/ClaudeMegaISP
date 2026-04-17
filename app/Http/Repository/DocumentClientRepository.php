<?php

namespace App\Http\Repository;

use App\Models\DocumentClient;

class DocumentClientRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = DocumentClient::query();
    }

    public function count()
    {
        return $this->model->count();
    }


    public function create($array)
    {
        return $this->model->create($array);
    }
}
