<?php

namespace App\Http\Repository;

use App\Models\DocumentClient;
use App\Models\DocumentCrm;

class DocumentCrmRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = DocumentCrm::query();
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
