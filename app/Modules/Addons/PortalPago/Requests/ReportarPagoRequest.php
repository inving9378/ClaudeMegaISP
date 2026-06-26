<?php

namespace App\Modules\Addons\PortalPago\Requests;

use App\Modules\Addons\PortalPago\Support\BancosCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del reporte de pago público. authorize() es true: el acceso ya está
 * acotado por el token de la liga (la ruta resuelve/404 antes de conciliar).
 */
class ReportarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clave_rastreo'   => ['required', 'string', 'size:18', 'alpha_num'],
            'banco_emisor'    => ['required', 'string', Rule::in(BancosCatalogo::comunes())],
            'fecha_operacion' => ['required', 'date', 'before_or_equal:today'],
            'monto_reportado' => ['required', 'numeric', 'min:0.01'],
            'comprobante'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5 MB
        ];
    }

    public function messages(): array
    {
        return [
            'clave_rastreo.size'           => 'La clave de rastreo debe tener exactamente 18 caracteres.',
            'clave_rastreo.alpha_num'      => 'La clave de rastreo solo admite letras y números.',
            'banco_emisor.in'              => 'Selecciona un banco válido de la lista.',
            'fecha_operacion.before_or_equal' => 'La fecha de la transferencia no puede ser futura.',
            'comprobante.mimes'            => 'El comprobante debe ser una imagen (JPG/PNG) o PDF.',
            'comprobante.max'              => 'El comprobante no puede pesar más de 5 MB.',
        ];
    }
}
