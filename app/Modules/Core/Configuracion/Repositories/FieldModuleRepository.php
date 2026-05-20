<?php

namespace App\Modules\Core\Configuracion\Repositories;

use App\Modules\Core\Configuracion\Models\FieldModule;
use App\Modules\Core\Configuracion\Models\FieldType;

class FieldModuleRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = FieldModule::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function getByModuleId($moduleId)
    {
        return $this->model->where('module_id', $moduleId)->first();
    }

    public function create($array)
    {
        $this->model->create($array);
    }


    public function updatePositionField($array)
    {
        $moduleId = $array['module_id'];
        $name = $array['name'];
        $position = $array['position'];
        $model = $this->model->where('module_id', $moduleId)->where('name', $name)->first();
        return $model->update(['position' => $position]);
    }
}
