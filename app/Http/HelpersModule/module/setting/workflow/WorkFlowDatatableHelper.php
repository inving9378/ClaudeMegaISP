<?php


namespace App\Http\HelpersModule\module\setting\workflow;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\WorkFlow;

class WorkFlowDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(WorkFlow::class, 'WorkFlow');
    }
}
