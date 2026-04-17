<?php

namespace App\Http\Controllers\Module\Setting;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\setting\SettingAdditionalFieldDatatableHelper;
use App\Http\Repository\FieldModuleRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\setting\field_module\SettingFieldModuleCreateRequest;
use App\Http\Requests\module\setting\field_module\ValidateIfColumnExistInDataBaseRequest;
use App\Services\FieldModuleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SettingAdditionalFieldController extends Controller
{
    private $helper;

    public function __construct(SettingAdditionalFieldDatatableHelper $helper)
    {
        $this->data['model'] = 'App\Models\FieldModule';
        $this->data['url'] = 'meganet.module.setting.additionalfield';
        $this->data['module'] = "FieldModule";
        $this->helper = $helper;
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('FieldModule');
        $this->data['modules'] = $this->getAllModules();
        return view('meganet.module.setting.additionalfield.listar', $this->data);
    }

    public function store(SettingFieldModuleCreateRequest $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->except('required');
        $input['rule'] = $this->buildRules($request);
        $input['additional_field'] = true;
        if ($request->options) {
            $input['options'] = $this->assignValuesOptions($request->options);
        }
        $input['name'] = strtolower(str_replace(" ", "_", $request->name));

        try {
            $this->createColumnInDataBase($request);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            throw new \Exception($errorMessage);
        }
        if (is_null($input['include'])) {
            $input['include'] = true;
        }
        $model = $this->data['model']::create($input);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        return $model;
    }

    public function update(Request $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->except('name', 'required', 'additional_field');
        $input['rule'] = $this->buildRules($request);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        if (is_null($input['include'])) {
            $input['include'] = true;
        }
        return $model->update($input);
    }

    public function destroy($id)
    {
        try {
            $field = $this->data['model']::findOrFail($id);
            if ($field->additional_field == false) {
                return response()->json([
                    'error' => 'Este campo no se puede eliminar'
                ]);
            } else {
                $fieldModuleService = new FieldModuleService();
                $fieldModuleService->deleteColumn($field);

                $field->delete();
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Este campo no se puede eliminar'
            ]);
        }
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function getAllModules()
    {
        $moduleRepository = new ModuleRepository();
        $arrayModules = $moduleRepository->getAllModulesWithField()->groupBy('group')->map(function ($modules) {
            return $modules->mapWithKeys(function ($module) {
                return [
                    $module->id => $module->name
                ];
            });
        })->toArray();

        $modulesNotShowInSelect = ['Bundle', 'ClientBundleService'];

        $filteredModules = collect($arrayModules)->map(function ($group) use ($modulesNotShowInSelect) {
            return array_filter($group, function ($moduleName, $moduleId) use ($modulesNotShowInSelect) {
                return !in_array($moduleName, $modulesNotShowInSelect) && !in_array($moduleId, $modulesNotShowInSelect);
            }, ARRAY_FILTER_USE_BOTH);
        })->toArray();

        $translateModules = trans('translation_modules');
        $arrayReturn = [];
        unset($filteredModules[""]); //evitar error cuando no tienen grupo asignado
        foreach ($filteredModules as $groupKey => $group) {
            foreach ($group as $moduleId => $moduleName) {
                // Verifica si existe una traducción para el módulo y si no, usa el nombre original
                $translatedName = $translateModules[$moduleName] ?? $moduleName;

                $arrayReturn[$translateModules[$groupKey]][$moduleId] = $translatedName;
            }
        }
        return $arrayReturn;
    }

    public function buildRules($request)
    {
        $rules = [];
        if (isset($request->required) && $request->required == true) {
            $rules[] = 'required';
        }
        if (count($rules) > 0) {
            return json_encode($rules);
        } else {
            return null;
        }
    }

    public function getRequiredValue($id)
    {
        $fieldModuleRepository = new FieldModuleRepository();
        $rule = $fieldModuleRepository->getById($id)->rule;
        $required = false;
        if (strpos($rule, 'required')) {
            $required = true;
        }

        return response()->json([
            'required' => $required
        ]);
    }

    public function createColumnInDataBase($request)
    {
        $fieldModuleService = new FieldModuleService();
        $fieldModuleService->createColumn($request);
    }

    public function assignValuesOptions($options)
    {
        $arrayOptions = [];
        foreach ($options as $key => $value) {
            $arrayOptions[$value] = $value;
        }
        return json_encode($arrayOptions);
    }

    public function updatePositionField(Request $request)
    {
        $array = $request->all();
        foreach ($array as $key => $value) {
            $fieldModuleRepository = new FieldModuleRepository();
            $fieldModuleRepository->updatePositionField($value);
        }
    }
}
