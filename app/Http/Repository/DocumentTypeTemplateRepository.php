<?php

namespace App\Http\Repository;

use App\Models\DocumentTypeTemplate;

class DocumentTypeTemplateRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = DocumentTypeTemplate::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getIdByName($name)
    {
        return $this->model->where('name', $name)->first()->id;
    }
}
