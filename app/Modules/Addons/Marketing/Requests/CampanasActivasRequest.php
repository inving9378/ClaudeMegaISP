<?php

namespace App\Modules\Addons\Marketing\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * GET /api/marketing/dashboard/campanas-activas?limite=N (Fase 1, §2.4.2 de fase-1-paquetes.md).
 * `limite` opcional, entero de 1 a 20 (por defecto 5); vacío, fuera de rango o no entero → 422 JSON.
 */
class CampanasActivasRequest extends FormRequest
{
    public const LIMITE_DEFAULT = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limite' => ['sometimes', 'required', 'integer', 'between:1,20'],
        ];
    }

    public function messages(): array
    {
        return [
            'limite.required' => 'El límite no puede ir vacío.',
            'limite.integer'  => 'El límite debe ser un número entero entre 1 y 20.',
            'limite.between'  => 'El límite debe ser un número entero entre 1 y 20.',
        ];
    }

    public function limite(): int
    {
        $limite = $this->validated('limite');

        return $limite === null ? self::LIMITE_DEFAULT : (int) $limite;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
