<?php

namespace App\Http\Repository;

use App\Models\Module;

class ColumnDatatableModuleRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = Module::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getAllColumnsByModule($moduleName)
    {
        $module = $this->model->where('name', $moduleName)->first();
        if ($module) {
            return $module->getColumnsDatatable(true);
        } else {
            return [];
        }
    }

    public function getColumnsByModule($moduleName)
    {
        $module = $this->model->where('name', $moduleName)->first();
        if ($module) {
            return $module->getColumnsDatatable();
        } else {
            return [];
        }
    }



    // SETTERS




}
