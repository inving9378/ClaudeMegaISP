<?php

namespace App\Http\Requests\module\client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class ClientUpdateRequest extends FormRequest
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
            'user'=>'required|unique:client_main_information,user,'.$request->route()->id,
            'name'=>'required',
            'father_last_name'=>'required',
            'mother_last_name'=>'required',
            'nif_pasaport' => 'required',
            'colony_id'=>'required',
            'power_dbm'=>'numeric',
            
        ];
    }

    public function attributes()
    {
        return [
            'colony_id'=>'Colonia',
            'user'=>'Usuario',
            'power_dbm'=>'Potencia en dbm',
        ];
    }
}
