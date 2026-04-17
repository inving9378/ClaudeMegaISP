<?php

namespace App\Http\Requests\module\plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomUpdateRequest extends FormRequest
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
            'title' => 'required',
            'service_name' => 'required',
            'price' => 'required',
         //   'partners' => 'required',
            'tax_include' => 'required',
            'tax' => 'required',
            'types_of_billing' => 'required',
            'prepaid_period' => 'required',
            'amount_days' => Rule::requiredIf($request->prepaid_period == 'Diario')
        ];
    }
}
