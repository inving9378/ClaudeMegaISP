<?php

namespace App\Http\Requests\module\client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class ClientInvoiseRequest extends FormRequest
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
            'number' => 'required',
            'date' => 'required',
            'total' => 'required',
            'payment_date' => 'required',
            'estado' => 'required',
            'client_id' => 'required',
        ];
    }
}
