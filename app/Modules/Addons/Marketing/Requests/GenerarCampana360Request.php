<?php

namespace App\Modules\Addons\Marketing\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST /api/marketing/campanas-360/{id}/generar — alcance (todo|estrategia|piezas, obligatorio — H-5) y reemplazar (Campañas 360, §2.4 de docs/marketing/fase-2-paquetes.md).
 */
class GenerarCampana360Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public const ALCANCES = ['todo', 'estrategia', 'piezas'];

    public function rules(): array
    {
        return [
            // H-5 (QA): el alcance es obligatorio; un POST sin cuerpo ya no lanza la IA como «todo».
            'alcance'    => ['required', 'string', Rule::in(self::ALCANCES)],
            'reemplazar' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'alcance.required'   => 'Indica qué generar: todo, estrategia o piezas.',
            'alcance.string'     => 'Indica qué generar: todo, estrategia o piezas.',
            'alcance.in'         => 'Indica qué generar: todo, estrategia o piezas.',
            'reemplazar.boolean' => '«reemplazar» debe ser sí o no.',
        ];
    }

    public function alcance(): string
    {
        return (string) $this->validated('alcance');
    }

    public function reemplazar(): bool
    {
        return (bool) ($this->validated('reemplazar') ?? false);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
