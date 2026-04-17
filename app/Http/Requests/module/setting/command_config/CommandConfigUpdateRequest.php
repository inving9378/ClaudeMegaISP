<?php

namespace App\Http\Requests\module\setting\command_config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class CommandConfigUpdateRequest extends FormRequest
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
            'frequency_id'=>'required',
            'command'=>'required'
        ];
    }

    public function attributes()
    {
        return [
            'frequency_id'=>'Frecuencia',  
            'command'=>'Comando'          
        ];
    }
}
