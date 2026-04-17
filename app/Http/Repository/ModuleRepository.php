<?php

namespace App\Http\Repository;

use App\Models\Module;

class ModuleRepository
{
    protected $client;
    protected $model;

    const MODULES_FOR_IMPORT = [
        'Internet',
        'Voise',
        'Custom',
        'Bundle',
        "Client",
        "ClientPayment",
        "ClientTransaction",
        "ClientInternetService",
        "ClientVozService",
        "ClientCustomService",
        "Partner",
        "NetworkIp",
        "Network",
        "ClientInvoice",
        "ClientBundleService"
    ];

    public function __construct()
    {
        $this->model = Module::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getModuleById($id)
    {
        return $this->model->findOrFail($id);
    }
   
    public function getAllModulesWithField()
    {
        return $this->model->with('fields')->whereHas('fields')->get();
    }

    public function getModuleByName($moduleName)
    {
        return $this->model->where('name', ucfirst($moduleName))->first();
    }

    public function getColumnsByModuleName($moduleName)
    {
        return $this->model->where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
    }


    public function getModulesForImport()
    {
        $namesModules = self::MODULES_FOR_IMPORT;
        return $this->model->whereIn('name', $namesModules)->get();
    }

    public function actualizaOrden(array $firstColumns, $moduleName)
    {
        $module = $this->model->where('name',$moduleName)->first();
        $columnsDatatable = $module->columnsDatatable()->pluck('name')->toArray();
        $specialOrders = array_merge($firstColumns, $columnsDatatable);
        $specialOrders = array_unique($specialOrders);
        foreach ($specialOrders as $key => $value) {
            $columnsDatatableModule = $module->columnsDatatable()->where('name', $value)->first();
            $columnsDatatableModule->update([
                'order' => $key
            ]);
        }       
    }
}
