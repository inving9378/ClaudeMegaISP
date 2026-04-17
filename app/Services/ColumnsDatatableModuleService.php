<?php

namespace App\Services;

use App\Http\Repository\ColumnDatatableModuleRepository;

class ColumnsDatatableModuleService
{

    public function getColumnsDatatableByModule($module, $all = false)
    {
        $columnDatatableModuleRepository = new ColumnDatatableModuleRepository;
        if ($all) {
            return $columnDatatableModuleRepository->getAllColumnsByModule($module);
        } else {
            return $columnDatatableModuleRepository->getColumnsByModule($module);
        }
    }
}
