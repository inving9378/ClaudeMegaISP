<?php

namespace App\Http\Repository;

use App\Http\Traits\RouterConnection;
use App\Models\Crm;
use App\Models\ListTemplateVerification;

class ListTemplateVerificationRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ListTemplateVerification::query();
    }

    public function count()
    {
        return $this->model->count();
    }
    
    public function getModelById($id)
    {
        return $this->model->where('id', $id)->first() ?? null;
    }
}
