<?php

namespace App\Modules\Addons\Marketing\Requests;

use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST /api/marketing/campanas-360/{id}/estado (Campañas 360, §2.4 de docs/marketing/fase-2-paquetes.md).
 */
class CambiarEstadoCampanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(array_keys(CatalogosCampana360::ESTADOS_CAMPANA))],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Indica el estado nuevo.',
            'estado.string'   => 'Estado no válido.',
            'estado.in'       => 'Estado no válido.',
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
