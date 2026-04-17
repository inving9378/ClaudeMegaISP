<?php

namespace App\Http\Requests\module\client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class GenerateContractRequest extends FormRequest
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
            'template' => 'required_if:name,null',
            'name' => 'required_if:template,null',
        ];
    }
}
