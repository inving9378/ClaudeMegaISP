<?php

namespace App\Http\Repository;

use App\Models\DocumentTemplate;

class DocumentTemplateRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = DocumentTemplate::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getHtmlById($id)
    {
        $model = $this->model->find($id);
        if ($model) {
            return $model->html;
        }
        return null;
    }
    public function getNameById($id)
    {
        $model = $this->model->findOrFail($id);
        return $model->name;
    }

    public function getModelById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function createDocumentTemplate($array)
    {
        return $this->model->create($array);
    }
}
