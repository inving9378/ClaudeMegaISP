<?php

namespace App\Http\Requests\module\perfil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerfilUpdateRequest extends FormRequest
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
            'name' => 'required',
            'email' => 'email|unique:users,email,' . $this->id,
            'password' => [
                'required',
                'string',
                'min:8', // Al menos 8 caracteres
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/',
            ],
        ];
    }

    public function messages()
    {
        return [
            'password.regex' => 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.',

        ];
    }
}
