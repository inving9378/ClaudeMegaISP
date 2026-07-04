<?php

namespace App\Modules\Addons\WhatsAppAgent\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del catálogo de funciones (crear/editar). Gate por whatsapp_manage_functions.
 * name/slug únicos ignorando soft-deleted y la propia fila en update.
 */
class WhatsAppFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('whatsapp_manage_functions'));
    }

    public function rules(): array
    {
        // En update la ruta trae {id}; en create no.
        $id = $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('whatsapp_functions', 'name')->ignore($id)->whereNull('deleted_at'),
            ],
            'slug' => [
                'required', 'string', 'max:80', 'alpha_dash',
                Rule::unique('whatsapp_functions', 'slug')->ignore($id)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'exclusive'   => ['sometimes', 'boolean'],
            'color'       => ['nullable', 'string', 'max:20'],
            'active'      => ['sometimes', 'boolean'],
            'position'    => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una función con ese nombre.',
            'slug.unique' => 'Ya existe una función con ese identificador (slug).',
            'slug.alpha_dash' => 'El slug solo admite letras, números, guiones y guion bajo.',
        ];
    }
}
