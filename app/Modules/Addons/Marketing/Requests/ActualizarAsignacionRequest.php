<?php

namespace App\Modules\Addons\Marketing\Requests;

use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/marketing/empresas/{id}/usuarios/{userId} — cambia el rol y/o activa/desactiva.
 */
class ActualizarAsignacionRequest extends FormRequest
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
            'rol'    => ['required_without:activo', 'nullable', 'string', Rule::in(EmpresaUsuario::ROLES)],
            'activo' => ['required_without:rol', 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rol.required_without'    => 'Indica el rol nuevo o si la persona queda activa.',
            'rol.string'              => 'El rol debe ser admin, marketing o ventas.',
            'rol.in'                  => 'El rol debe ser admin, marketing o ventas.',
            'activo.required_without' => 'Indica el rol nuevo o si la persona queda activa.',
            'activo.boolean'          => 'El campo activo debe ser verdadero o falso.',
        ];
    }
}
