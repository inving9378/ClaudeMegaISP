<?php

namespace App\Http\Repository;

use App\Models\DocumentTemplate;
use App\Models\TemplateTask;

class TemplateTaskRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = TemplateTask::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelById($id)
    {
        return $this->model->findOrFail($id);
    }
}
