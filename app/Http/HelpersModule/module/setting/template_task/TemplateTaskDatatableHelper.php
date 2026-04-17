<?php


namespace App\Http\HelpersModule\module\setting\template_task;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\TemplateTask;

class TemplateTaskDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(TemplateTask::class, 'TemplateTask');
    }
}
