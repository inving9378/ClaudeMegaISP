<?php


namespace App\Http\HelpersModule\module\finance;

use App\Models\Module;
use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\GeneralAccountingCategory;

class GeneralAccountingCategoryDatatableHelper extends HelperDatatable
{
    private $model, $columns;

    public function __construct()
    {
        $this->model = GeneralAccountingCategory::class;
        $moduleName = 'GeneralAccountingCategory';
        $this->columns = Module::where('name', $moduleName)->first()
            ->columnsDatatable->where('name', '!=', 'action')->pluck('name')->toArray();
    }
}
