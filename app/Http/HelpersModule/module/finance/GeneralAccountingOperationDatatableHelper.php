<?php


namespace App\Http\HelpersModule\module\finance;

use App\Models\Module;
use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\GeneralAccountingIncome;
use App\Models\GeneralAccountingOperation;
use Illuminate\Support\Arr;

class GeneralAccountingOperationDatatableHelper extends HelperDatatable
{
    private $model, $columns;


    public function __construct()
    {
        $this->model = GeneralAccountingOperation::class;
        $moduleName = 'GeneralAccountingOperation';
        $this->columns = Module::where('name', $moduleName)->first()
            ->columnsDatatable->where('name', '!=', 'action')->pluck('name')->toArray();
    }
}
