<?php

namespace App\Http\Controllers\Module\Setting\TemplateTask;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\setting\list_template_verification\ListTemplateVerificationDatatableHelper;
use App\Http\HelpersModule\module\setting\template_task\TemplateTaskDatatableHelper;
use App\Http\Repository\ListTemplateVerificationRepository;
use App\Http\Repository\TemplateTaskRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use Illuminate\Http\Request;

class TemplateTaskController extends CrudModalController
{
    public function __construct(TemplateTaskDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\TemplateTask';
        $this->data['url'] = 'meganet.module.setting.template_task';
        $this->data['module'] = 'TemplateTask';
    }

    public function store(Request $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $model = $this->data['model']::create($input);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        return $model;
    }

    public function getDataTemplate($id)
    {
        $documentTemplateRepository = new TemplateTaskRepository();
        $taskTemplate = $documentTemplateRepository->getModelById($id);
        return response()->json([
            'description' => $taskTemplate->description,
            'title' => $taskTemplate->title_template,
            'project_id' => $taskTemplate->project_id,
            'template_verification' => $taskTemplate->template_verification_id,
            'assigned_to' =>  $taskTemplate->assigned_to,
            'priority' =>  $taskTemplate->priority,
        ]);
    }
}
