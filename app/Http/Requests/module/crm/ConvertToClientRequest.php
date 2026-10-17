<?php

namespace App\Http\Requests\module\crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ConvertToClientRequest extends FormRequest
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
            'colony_id'=>'required',
            'connection_type'=>'required',
            'estado'=>'required',
            'power_dbm'=>'nullable|numeric',
            'seller_id'=>'required',
            'email' => 'required|unique:client_main_information,email,' . $request->route('id'),
            'medium_id'=>'required',
            // FR-03 (#537): plazo copiado del prospecto; meses solo si es «Especial».
            'plazo_especial_meses' => \App\Support\PlazoContrato::reglaMeses($request->input('duration_contract_id')),
        //    'user' => 'required|unique:client_main_information,user,' . $request->route('id'),
        ];
    }

    public function attributes()
    {
        return [
            'user'=>'usuario',
            'plazo_especial_meses'=>'meses del plazo Especial',
            'colony_id'=>'colonia',
            'connection_type'=>'tipo de conexión',
            'estado'=>'estado',
            'power_dbm'=>'potencia en dbm',
            'seller_id'=>'propietario',
            'medium_id'=>'medio de venta',
        ];
    }
}
