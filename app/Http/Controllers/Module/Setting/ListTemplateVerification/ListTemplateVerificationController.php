<?php

namespace App\Http\Controllers\Module\Setting\ListTemplateVerification;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\setting\list_template_verification\ListTemplateVerificationDatatableHelper;
use App\Http\Repository\ListTemplateVerificationRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use Illuminate\Http\Request;

class ListTemplateVerificationController extends CrudModalController
{
    public function __construct(ListTemplateVerificationDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\ListTemplateVerification';
        $this->data['url'] = 'meganet.module.setting.list_template_verification';
        $this->data['module'] = 'ListTemplateVerification';
    }

    public function store(Request $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $input = $this->getValueToInputChecks($input);
        $model = $this->data['model']::create($input);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        return $model;
    }

    public function getValueToInputChecks($input)
    {
        $checks = $input['checks'];
        $array = [];
        foreach ($checks as $key => $value) {
            $array[$key] = $value;
        }

        $input['checks'] = json_encode($array);
        return $input;
    }

    public function getChecksById($id)
    {
        $listTemplateVerificationRepository = new ListTemplateVerificationRepository();
        $model = $listTemplateVerificationRepository->getModelById($id);
        $checks = $model->checks;

        return response()->json($checks);
    }
}
