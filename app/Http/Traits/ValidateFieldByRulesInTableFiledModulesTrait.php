<?php

namespace App\Http\Traits;

use App\Http\Repository\ModuleRepository;
use App\Models\Module;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ValidateFieldByRulesInTableFiledModulesTrait
{
    public function validateFieldByRulesInTableFiledModules($moduleName, $request)
    {
        $moduleRepository = new ModuleRepository();
        $arrayRequest = $request->all();
        $module = $moduleRepository->getModuleByName($moduleName);
        $fields = $module->fields()->whereNotNull('rule')->get();

        $rules = [];
        foreach ($fields as $field) {
            $rule = trim($field->rule, '[]\"');
            $rules[$field->name] = $rule;
        }

        $validator = Validator::make($arrayRequest, $rules);
        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $formattedErrors = [];

            $fieldNames = array_keys($errors);
            foreach ($fieldNames as $field) {
                $formattedErrors[$field] = $errors[$field][0];
            }
            throw ValidationException::withMessages($formattedErrors);
        }
    }
}
