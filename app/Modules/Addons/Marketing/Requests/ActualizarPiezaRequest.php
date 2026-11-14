<?php

namespace App\Modules\Addons\Marketing\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * PUT /api/marketing/campanas-360/{id}/piezas/{piezaId} — el contenido se valida con ValidadorPiezas (mismo validador que la salida de la IA) (Campañas 360, §2.4 de docs/marketing/fase-2-paquetes.md).
 */
class ActualizarPiezaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // `present` (no `required`): un objeto vacío llega al validador de la pieza (o al 422 `no_editable` de un video).
            'contenido' => ['present', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'contenido.present'  => 'Falta el contenido de la pieza.',
            'contenido.array'    => 'El contenido debe ser un objeto.',
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
