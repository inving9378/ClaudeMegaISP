<?php

namespace App\Modules\Addons\Marketing\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /api/marketing/campanas-360/{id}/piezas/{piezaId}/regenerar — indicaciones opcionales (≤ 500, saneadas) (Campañas 360, §2.4 de docs/marketing/fase-2-paquetes.md).
 */
class RegenerarPiezaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $i = $this->input('indicaciones');
        if (is_string($i)) {
            $this->merge(['indicaciones' => GuardarCampana360Request::sanear($i)]);
        }
    }

    public function rules(): array
    {
        return [
            'indicaciones' => ['nullable', 'string', 'max:500', 'not_regex:/\{\{|\}\}/'],
        ];
    }

    public function messages(): array
    {
        return [
            'indicaciones.string'    => 'Las indicaciones deben ser texto.',
            'indicaciones.max'       => 'Las indicaciones pueden tener hasta 500 caracteres.',
            'indicaciones.not_regex' => 'Las indicaciones no pueden llevar {{ }}.',
        ];
    }

    public function indicaciones(): string
    {
        return (string) ($this->validated('indicaciones') ?? '');
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
