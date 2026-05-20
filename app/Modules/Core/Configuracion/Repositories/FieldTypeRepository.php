<?php

namespace App\Modules\Core\Configuracion\Repositories;

use App\Modules\Core\Configuracion\Models\FieldType;
use Illuminate\Support\Facades\Log;

class FieldTypeRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = FieldType::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getNameById($id)
    {
        return $this->model->findOrFail($id)->name;
    }

    public function getIdByName($name)
    {
        $idField = $this->model->where('name', $name)->first();
        if ($idField) {
            return $idField->id;
        }
        return null;
    }

    // SETTERS




}
