<?php

namespace App\Services;

use App\Http\Repository\ModuleRepository;
use Illuminate\Support\Facades\Artisan;

class FieldModuleService
{

    public function callProcessToCreateColumn($nameTable, $nameColumn, $tipo)
    {
        $nombreTarea = 'field_module_create_field_migration:process';
        $parametros = [
            'tabla' => $nameTable,
            'nombre_columna' => $nameColumn,
            'tipo' => $tipo,
        ];
        Artisan::call($nombreTarea, $parametros);
    }

    public function callProcessToDeleteColumn($nameTable, $nameColumn)
    {
        $nombreTarea = 'delete_column__in_data_base:process';
        $parametros = [
            'tabla' => $nameTable,
            'nombre_columna' => $nameColumn,
            'tipo' => 'string',
        ];
        Artisan::call($nombreTarea, $parametros);
    }

    public function createColumn($request)
    {
        $moduleRepository = new ModuleRepository();
        $module = $moduleRepository->getModuleById($request->module_id);
        $model = $module->getNameModel();
        $tipo = $request->type;
        $nameColumn = strtolower(str_replace(" ", "_", $request->name));
        $nameTable = $this->getTableForThisModel($model);
        if (is_array($nameTable)) {
            foreach ($nameTable as $table) {
                $this->callProcessToCreateColumn($table, $nameColumn, $tipo);
            }
        } else {
            $this->callProcessToCreateColumn($nameTable, $nameColumn, $tipo);
        }
    }

    public function deleteColumn($field)
    {
        $moduleRepository = new ModuleRepository();
        $module = $moduleRepository->getModuleById($field->module_id);
        $model = $module->getNameModel();
        $nameColumn = strtolower(str_replace(" ", "_", $field->name));
        $nameTable = $this->getTableForThisModelToDelete($model);
        if (is_array($nameTable)) {
            foreach ($nameTable as $table) {
                $this->callProcessToDeleteColumn($table, $nameColumn);
            }
        } else {
            $this->callProcessToDeleteColumn($nameTable, $nameColumn);
        }
    }

    public function getTableForThisModel($model)
    {
        $arrayTableModels = [];
        if (defined($model . '::MODEL_RELATION_TO_CREATE_FIELD_MODULE')) {
            $model = $model::MODEL_RELATION_TO_CREATE_FIELD_MODULE;
            if (is_array($model)) {
                foreach ($model as $singleModel) {
                    $modelo = new $singleModel();
                    $arrayTableModels[] = $modelo->getTable();
                }
                return $arrayTableModels;
            } else {
                $modelo = new $model();
                return $modelo->getTable();
            }
        } else {
            $modelo = new $model();
            return $modelo->getTable();
        }
    }

    public function getTableForThisModelToDelete($model)
    {
        if (defined($model . '::MODEL_RELATION_TO_CREATE_FIELD_MODULE')) {
            $model = $model::MODEL_RELATION_TO_CREATE_FIELD_MODULE;
            if (is_array($model)) {
                foreach ($model as $singleModel) {
                    $modelo = new $singleModel();
                    $arrayTableModels[] = $modelo->getTable();
                }
                return $arrayTableModels;
            } else {
                $modelo = new $model();
                return $modelo->getTable();
            }
        } else {
            $modelo = new $model();
            return $modelo->getTable();
        }
    }
}
