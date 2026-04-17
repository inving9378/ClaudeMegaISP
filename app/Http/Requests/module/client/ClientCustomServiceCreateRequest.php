<?php

namespace App\Http\Requests\module\client;

use App\Rules\ValidateIfUserIsDisponible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientCustomServiceCreateRequest extends FormRequest
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
        $rules = [
            'amount' => 'required',
            'unity' => 'required',
            'start_date' => 'required',
            'pay_period' => 'required',
            'estado' => 'required',
            'payment_type' => 'required',
            'deferred_payment_in_month' => 'required_if:payment_type,Pago diferido',
        ];

        if ($request->router_id !== null) {
            $rules['user'] = [new ValidateIfUserIsDisponible($request->router_id)];
        }

        if ($request->discount == true) {
            $rules['discount_percent'] = 'required';
            $rules['start_date_discount'] = 'required';
            $rules['end_date_discount'] = 'required';
            $rules['discount_message'] = 'required';
        }

        return $rules;
    }
}
