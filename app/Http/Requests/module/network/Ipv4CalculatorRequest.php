<?php

namespace App\Http\Requests\module\network;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class Ipv4CalculatorRequest extends FormRequest
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
            'network_calculator' => 'ipv4',
            'bm_calculator' => 'required'
        ];
    }
}

