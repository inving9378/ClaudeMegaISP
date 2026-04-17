<?php

namespace App\Http\Requests\module\client;

use App\Rules\ValidateIfUserIsDisponible;
use App\Rules\ValidateIpv4IfNotUsed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientInternetServiceCreateRequest extends FormRequest
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
            'internet_id' => 'required',
            'amount' => 'required',
            'unity' => 'required',
            'start_date' => 'required',
            'pay_period' => 'required',
            'estado' => 'required',
            'password' => 'required',
            'client_name' => [new ValidateIfUserIsDisponible($request->router_id)],
            'router_id'  => 'required',
            'discount_percent' => Rule::requiredIf($request->discount == true),
            'start_date_discount' =>  Rule::requiredIf($request->discount == true),
            'end_date_discount' => Rule::requiredIf($request->discount == true),
            'discount_message' => Rule::requiredIf($request->discount == true),
            'ipv4_assignment' => 'required',
            'ipv4' => ['required_if:ipv4_assignment,IP Estatica', new ValidateIpv4IfNotUsed($client_id)],
            'ipv4_pool' => Rule::requiredIf($request->ipv4_assignment == 'Pool IP'),
            'payment_type' => Rule::requiredIf($request->cost_activation > 0),
            'deferred_payment_in_month' => Rule::requiredIf($request->payment_type == 'Pago diferido'),
        ];
    }
}
