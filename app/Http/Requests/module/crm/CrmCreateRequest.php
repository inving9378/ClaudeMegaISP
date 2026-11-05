<?php

namespace App\Http\Requests\module\crm;

use App\Rules\ValidateSameNameInCrmOrClientTable;
use App\Rules\ValidateUniqueEmailForClientAndCrm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class CrmCreateRequest extends FormRequest
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
    /** CRM-E 03 (#557): vacío = sin tipo de persona / sin segmento (residencial de siempre). */
    protected function prepareForValidation()
    {
        foreach (['tipo_persona', 'segmento_id'] as $campo) {
            if ($this->has($campo) && $this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    public function rules(Request $request)
    {
        return [
            'name'=> [
                new ValidateSameNameInCrmOrClientTable($request),
                'required',
                'regex:' . \App\Support\PersonaMoralCrm::regexNombre($request)
            ],
            'father_last_name' => \App\Support\PersonaMoralCrm::reglaApellido($request),
            'mother_last_name' => \App\Support\PersonaMoralCrm::reglaApellido($request),
            'email'=> [new ValidateUniqueEmailForClientAndCrm($request)],
            'phone'=>'required',
            'crm_status'=>'required',
            'owner_id' => 'required',
            'colony_id'=>'required',
            // FR-03 (#537): plazo de contrato del prospecto; meses solo si es «Especial».
            'plazo_especial_meses' => \App\Support\PlazoContrato::reglaMeses($request->input('duration_contract_id')),
        ];
    }

    public function attributes()
    {
        return ['plazo_especial_meses' => 'meses del plazo Especial'];
    }
}
