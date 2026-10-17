<?php

namespace App\Http\Requests\module\crm;

use App\Rules\ValidateSameNameInCrmOrClientTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class CrmUpdateRequest extends FormRequest
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
        return [
            'name' => [
                new ValidateSameNameInCrmOrClientTable($request),
                'required',
                'regex:/^[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+$/u'
            ],
            'father_last_name' => 'required',
            'mother_last_name' => 'required',
            'phone' => 'required',
            'crm_status' => 'required',
            'owner_id' => 'required',
            'instalation_date' => 'unique:crm_lead_information,instalation_date,' . $this->id,
            'colony_id' => 'required',
            'nif_pasaport' => 'required',
            // FR-03 (#537): plazo de contrato del prospecto; meses solo si es «Especial».
            'plazo_especial_meses' => \App\Support\PlazoContrato::reglaMeses($request->input('duration_contract_id')),
        ];
    }

    public function attributes()
    {
        return [
            'nif_pasaport' => 'RFC/CURP',
            'plazo_especial_meses' => 'meses del plazo Especial',
        ];
    }
}
