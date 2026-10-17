<?php

namespace App\Http\Requests\module\client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use App\Rules\IfChangeRecurrentToOtherTypeOfBilling;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ClientInformationRequest extends FormRequest
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
        $client_id = $request->route()->id;
        return [
            'name' => 'required',
            'father_last_name' => 'required',
            'mother_last_name' => 'required',
            'colony_id' => 'required',
            'password' => 'required',
            'duration_contract_id' => 'required',
            // FR-03 (#537): meses obligatorios (1-120) solo si el plazo es «Especial».
            'plazo_especial_meses' => \App\Support\PlazoContrato::reglaMeses($request->input('duration_contract_id')),
        ];
    }

    public function attributes()
    {
        return ['plazo_especial_meses' => 'meses del plazo Especial'];
    }
}
