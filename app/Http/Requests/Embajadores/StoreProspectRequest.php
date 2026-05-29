<?php

namespace App\Http\Requests\Embajadores;

use Illuminate\Foundation\Http\FormRequest;

class StoreProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:100',
            'phone'   => 'required|string|max:20',
            'email'   => 'nullable|email|max:100',
            'address' => 'nullable|string|max:255',
            'source'  => 'nullable|in:shared_link,manual,contact_import,bulk_send',
            'notes'   => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'El nombre es obligatorio.',
            'phone.required' => 'El teléfono es obligatorio.',
            'email.email'    => 'El correo no es válido.',
            'source.in'      => 'La fuente no es válida.',
        ];
    }
}
