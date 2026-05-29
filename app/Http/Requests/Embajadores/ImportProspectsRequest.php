<?php

namespace App\Http\Requests\Embajadores;

use Illuminate\Foundation\Http\FormRequest;

class ImportProspectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contacts'          => 'required|array|min:1|max:200',
            'contacts.*.name'   => 'required|string|max:100',
            'contacts.*.phone'  => 'required|string|max:20',
            'contacts.*.email'  => 'nullable|email|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'contacts.required'         => 'La lista de contactos es obligatoria.',
            'contacts.min'              => 'Debes enviar al menos un contacto.',
            'contacts.max'              => 'Máximo 200 contactos por importación.',
            'contacts.*.name.required'  => 'Cada contacto debe tener nombre.',
            'contacts.*.phone.required' => 'Cada contacto debe tener teléfono.',
            'contacts.*.email.email'    => 'Algún correo de contacto no es válido.',
        ];
    }
}
