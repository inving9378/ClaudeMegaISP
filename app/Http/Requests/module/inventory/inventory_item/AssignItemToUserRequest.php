<?php

namespace App\Http\Requests\module\inventory\inventory_item;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignItemToUserRequest extends FormRequest
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
            'id_user' => 'required',
            'store_from' => 'required',
            'quantity' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($stockFromDisponible) {
                    if ($value > $stockFromDisponible) {
                        $fail("No hay suficiente stock. Solo hay {$stockFromDisponible} unidades disponibles.");
                    }
                },
            ],
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'id_user.required' => 'El usuario es requerido',
            'quantity.required' => 'La cantidad es requerida',
            'quantity.min' => 'La cantidad debe ser mayor a 0',
        ];
    }
}
