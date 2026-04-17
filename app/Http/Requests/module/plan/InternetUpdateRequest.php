<?php

namespace App\Http\Requests\module\plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternetUpdateRequest extends FormRequest
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
            'tax_include' => 'required',
            'tax' => 'required',
            'download_speed' => 'required',
            'upload_speed' => 'required',
           // 'guaranteed_speed' => 'required',
            'guaranteed_speed_limit' => 'required',
            'priority' => 'required',
            'aggregation' => 'required',
            'burst' => 'required',
            'planes_internet_disponibles' => 'nullable|integer',
            'types_of_billing' => 'required',
            'prepaid_period' => 'required',
            'transaction_category' => 'nullable',
            'amount_days' => Rule::requiredIf($request->prepaid_period == 'Diario')
        ];
    }
}
