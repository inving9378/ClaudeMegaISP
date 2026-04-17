<?php

namespace App\Http\Controllers\Module\Setting\WorkFlow;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\setting\workflow\WorkFlowDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;

class WorkFlowController extends CrudModalController
{
    public function __construct(WorkFlowDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\WorkFlow';
        $this->data['url'] = 'meganet.module.setting.workflow';
        $this->data['module'] = 'WorkFlow';
    }
}
