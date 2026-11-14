<?php

namespace App\Modules\Addons\Marketing\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * GET /api/marketing/dashboard/resumen?mes=AAAA-MM (Fase 1, §2.4.1 de fase-1-paquetes.md).
 *
 * `mes` es opcional; si se manda debe ser AAAA-MM entre 2020-01 y el mes actual (America/Mexico_City).
 * Vacío, arreglo, texto libre, `<script>` o SQL → 422 con el formato de Laravel (`errors.mes`), siempre JSON.
 */
class ResumenDashboardRequest extends FormRequest
{
    public const ZONA = 'America/Mexico_City';

    private const MINIMO = '2020-01';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mes' => [
                'bail',
                'sometimes',
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])\z/',
                function (string $atributo, mixed $valor, Closure $falla): void {
                    // Con `bail`, aquí solo llega un AAAA-MM válido; la guarda es por si alguien quita el bail.
                    if (! is_string($valor) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])\z/', $valor)) {
                        return;
                    }
                    $actual = CarbonImmutable::now(self::ZONA)->format('Y-m');
                    if (strcmp($valor, self::MINIMO) < 0 || strcmp($valor, $actual) > 0) {
                        $falla('El mes debe estar entre 2020-01 y el mes actual.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mes.required' => 'El mes no puede ir vacío.',
            'mes.string'   => 'El mes debe tener el formato AAAA-MM.',
            'mes.regex'    => 'El mes debe tener el formato AAAA-MM.',
        ];
    }

    /** Mes pedido o, si no viene, el mes actual en America/Mexico_City. */
    public function mes(): string
    {
        $mes = $this->validated('mes');

        return is_string($mes) ? $mes : CarbonImmutable::now(self::ZONA)->format('Y-m');
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
