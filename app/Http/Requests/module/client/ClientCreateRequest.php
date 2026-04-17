<?php

namespace App\Http\Requests\module\client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class ClientCreateRequest extends FormRequest
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
            'name' => 'required',
            'father_last_name' => 'required',
            'mother_last_name' => 'required',
            'colony_id' => 'required',
            'user' => 'required|unique:client_main_information,user,' . $request->route()->id,
            'email' => 'required|unique:client_main_information,email,' . $request->route()->id,
            'power_dbm' => 'numeric',
            'nif_pasaport' => 'required',
            'password' => 'required',
            'duration_contract_id' => 'required',
        ];
    }

    public function attributes()
    {
        return [
            'colony_id' => 'Colonia',
            'user' => 'Usuario',
            'power_dbm' => 'Potencia en dbm',
            'password' => 'Contraseña'
        ];
    }
}
