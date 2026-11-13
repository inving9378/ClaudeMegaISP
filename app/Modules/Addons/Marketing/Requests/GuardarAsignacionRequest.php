<?php

namespace App\Modules\Addons\Marketing\Requests;

use App\Models\User;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/marketing/empresas/{id}/usuarios — agrega a una persona a la empresa con un rol.
 * Solo escribe marketing_empresa_usuario: nunca da ni quita roles/permisos del sistema.
 */
class GuardarAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        EmpresaUsuario::empresaAdministradaOFallar((int) $this->user()->getKey(), $this->route('id'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1', self::personalValido()],
            'rol'     => ['required', 'string', Rule::in(EmpresaUsuario::ROLES)],
        ];
    }

    /**
     * Persona asignable: existe, está activa, no es cuenta de sistema y tiene al menos un rol distinto de client.
     */
    public static function personalValido(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            if (! is_int($valor) && ! (is_string($valor) && ctype_digit($valor))) {
                return;
            }

            $valido = User::query()
                ->whereKey((int) $valor)
                ->where('estado', 'activo')
                ->where(fn ($q) => $q->whereNull('is_system')->orWhere('is_system', false))
                ->whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))
                ->exists();

            if (! $valido) {
                $fallar('Esa persona no se puede agregar: no existe, no está activa o no es personal del sistema.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Elige a la persona que quieres agregar.',
            'user_id.integer'  => 'La persona elegida no es válida.',
            'user_id.min'      => 'La persona elegida no es válida.',
            'rol.required'     => 'Elige el rol de la persona en esta empresa.',
            'rol.string'       => 'El rol debe ser admin, marketing o ventas.',
            'rol.in'           => 'El rol debe ser admin, marketing o ventas.',
        ];
    }
}
