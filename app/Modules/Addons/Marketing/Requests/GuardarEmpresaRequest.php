<?php

namespace App\Modules\Addons\Marketing\Requests;

use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Alta (POST /api/marketing/empresas) y edición (PUT /api/marketing/empresas/{id}) de una empresa.
 * En el alta `activo` no se acepta (siempre nace activa); en la edición todo es opcional.
 */
class GuardarEmpresaRequest extends FormRequest
{
    private const REGEX_SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*\z/';

    private const REGEX_COLOR = '/^#[0-9A-Fa-f]{6}\z/';

    public function authorize(): bool
    {
        return true;
    }

    private function esAlta(): bool
    {
        return $this->isMethod('post');
    }

    protected function prepareForValidation(): void
    {
        // Edición: la empresa debe ser una que la persona administra; si no, 404 antes de validar.
        if (! $this->esAlta()) {
            EmpresaUsuario::empresaAdministradaOFallar((int) $this->user()->getKey(), $this->route('id'));
        }

        $nombre = $this->input('nombre');
        if (is_string($nombre)) {
            $this->merge(['nombre' => trim($nombre)]);
        }

        // Alta sin slug: se deriva del nombre (máx. 60 caracteres, sin guion colgando).
        if ($this->esAlta() && ($this->input('slug') === null || $this->input('slug') === '') && is_string($nombre)) {
            $slug = trim(Str::limit(Str::slug($nombre), 60, ''), '-');
            $this->merge(['slug' => $slug]);
        }
    }

    public function rules(): array
    {
        $ignorar = $this->esAlta() ? null : (int) $this->route('id');
        $vez = $this->esAlta() ? 'required' : 'sometimes';

        $reglas = [
            'nombre' => [$vez, 'required', 'string', 'min:2', 'max:120', $this->nombreUnico($ignorar)],
            'slug'   => [$vez, 'required', 'string', 'min:2', 'max:60', 'regex:' . self::REGEX_SLUG,
                Rule::unique('marketing_empresas', 'slug')->ignore($ignorar)],
            'color_primario'   => ['sometimes', 'nullable', 'string', 'regex:' . self::REGEX_COLOR],
            'color_secundario' => ['sometimes', 'nullable', 'string', 'regex:' . self::REGEX_COLOR],
        ];

        if (! $this->esAlta()) {
            $reglas['activo'] = ['sometimes', 'required', 'boolean'];
        }

        return $reglas;
    }

    /**
     * Nombre único sin distinguir mayúsculas (consulta con bindings).
     */
    private function nombreUnico(?int $ignorar): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($ignorar) {
            if (! is_string($valor)) {
                return;
            }

            $query = Empresa::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($valor))]);
            if ($ignorar) {
                $query->where('id', '!=', $ignorar);
            }

            if ($query->exists()) {
                $fallar('Ya existe una empresa con ese nombre.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la empresa.',
            'nombre.string'   => 'El nombre debe ser texto.',
            'nombre.min'      => 'El nombre debe tener al menos 2 caracteres.',
            'nombre.max'      => 'El nombre no puede pasar de 120 caracteres.',
            'slug.required'   => 'El identificador (slug) es obligatorio.',
            'slug.string'     => 'El identificador (slug) debe ser texto.',
            'slug.min'        => 'El identificador (slug) debe tener al menos 2 caracteres.',
            'slug.max'        => 'El identificador (slug) no puede pasar de 60 caracteres.',
            'slug.regex'      => 'El identificador (slug) solo admite minúsculas, números y guiones entre palabras (p. ej. meganet-empresarial).',
            'slug.unique'     => 'Ese identificador (slug) ya lo usa otra empresa.',
            'color_primario.string'   => 'El color primario debe tener el formato #RRGGBB.',
            'color_primario.regex'    => 'El color primario debe tener el formato #RRGGBB.',
            'color_secundario.string' => 'El color secundario debe tener el formato #RRGGBB.',
            'color_secundario.regex'  => 'El color secundario debe tener el formato #RRGGBB.',
            'activo.required' => 'Indica si la empresa queda activa.',
            'activo.boolean'  => 'El campo activo debe ser verdadero o falso.',
        ];
    }
}
