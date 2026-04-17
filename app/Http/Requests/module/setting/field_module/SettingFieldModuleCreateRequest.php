<?php

namespace App\Http\Requests\module\setting\field_module;

use App\Rules\ValidateIfColumnExistInDataBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingFieldModuleCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request)
    {
        $moduleID = $request->input('module_id');
        $fieldModuleID = $request->input('id'); // Si estás actualizando un registro existente, obtén su ID

        return [
            'name' => [
                'required',
                Rule::unique('field_modules', 'name')
                    ->where('id', '<>', $fieldModuleID)
                    ->where('module_id', $moduleID),
                new ValidateIfColumnExistInDataBase($request)
            ]
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Nombre',
        ];
    }
}
