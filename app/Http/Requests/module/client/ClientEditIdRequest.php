<?php

namespace App\Http\Requests\module\client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use App\Rules\IfChangeRecurrentToOtherTypeOfBilling;
use App\Rules\ValidateClientIdEdit;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ClientEditIdRequest extends FormRequest
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
        $id = $request->id_actual; // Asumiendo que el id viene en la URL como un parámetro de ruta

        return [
            'id_new' => [
                'required',
                'numeric',
                new ValidateClientIdEdit
            ],
        ];
    }
}
