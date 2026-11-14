<?php

namespace App\Modules\Addons\Marketing\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * PUT /api/marketing/campanas-360/{id}/estrategia — el contenido se valida con GeneradorEstrategia::validar (mismo validador que la salida de la IA) (Campañas 360, §2.4 de docs/marketing/fase-2-paquetes.md).
 */
class ActualizarEstrategiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estrategia' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'estrategia.required' => 'Falta la estrategia.',
            'estrategia.array'    => 'La estrategia debe ser un objeto.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
