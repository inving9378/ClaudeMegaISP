<?php

namespace App\Modules\Core\Configuracion\Controllers\WorkFlow;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\setting\workflow\WorkFlowDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;

class WorkFlowController extends CrudModalController
{
    public function __construct(WorkFlowDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\WorkFlow';
        $this->data['url'] = 'core-configuracion::workflow';
        $this->data['module'] = 'WorkFlow';
    }
}
