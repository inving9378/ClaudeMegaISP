<?php

namespace App\Http\Requests\Embajadores;

use Illuminate\Foundation\Http\FormRequest;

class StoreFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action'           => 'required|in:call,whatsapp,visit,sms,email,note',
            'notes'            => 'required|string|max:2000',
            'next_action_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required'         => 'El tipo de acción es obligatorio.',
            'action.in'               => 'El tipo de acción no es válido.',
            'notes.required'          => 'Las notas son obligatorias.',
            'next_action_date.date'   => 'La fecha no es válida.',
            'next_action_date.after_or_equal' => 'La fecha de próxima acción no puede ser en el pasado.',
        ];
    }
}
