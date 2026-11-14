<?php

namespace App\Modules\Addons\Marketing\Requests;

use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST /api/marketing/campanas-360/{id}/piezas/{piezaId}/producir — voz y música opcionales (si faltan, las del guion) (Campañas 360, §2.4 de docs/marketing/fase-2-paquetes.md).
 */
class ProducirVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voz'    => ['nullable', 'string', Rule::in(array_keys(CatalogosCampana360::VOCES))],
            'musica' => ['nullable', 'string', Rule::in(array_keys(CatalogosCampana360::MUSICAS))],
        ];
    }

    public function messages(): array
    {
        return [
            'voz.string'    => 'Elige una voz de la lista.',
            'voz.in'        => 'Elige una voz de la lista.',
            'musica.string' => 'Elige una música de la lista.',
            'musica.in'     => 'Elige una música de la lista.',
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
