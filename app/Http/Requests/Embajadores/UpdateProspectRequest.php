<?php

namespace App\Http\Requests\Embajadores;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'sometimes|string|max:100',
            'phone'   => 'sometimes|string|max:20',
            'email'   => 'nullable|email|max:100',
            'address' => 'nullable|string|max:255',
            'status'  => 'sometimes|in:new,contacted,interested,quoted,converted,lost',
            'notes'   => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'  => 'El correo no es válido.',
            'status.in'    => 'El estado no es válido.',
        ];
    }
}
