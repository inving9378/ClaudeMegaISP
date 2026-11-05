<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoRoleDepartment;
use Illuminate\Support\Str;

/**
 * Catálogo de departamentos de Talento (UNI-T C4b): config base ∪ talento_role_departments.
 */
class DepartamentoCatalogo
{
    public static function activo(): bool
    {
        return (bool) config('unificacion.interruptores.departamentos_catalogo_c4b', false);
    }

    /** @return string[] */
    public static function lista(): array
    {
        $base = (array) config('unificacion.departamentos.catalogo', []);
        $roles = TalentoRoleDepartment::query()->pluck('department')->all();

        return array_values(array_unique(array_filter([...$base, ...$roles])));
    }

    /** Departamento del catálogo que corresponde a un valor guardado, o null si no hay equivalencia. */
    public static function normalizar(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        $clave = self::clave($valor);

        foreach (self::lista() as $dep) {
            if (self::clave($dep) === $clave) {
                return $dep;
            }
        }
        foreach ((array) config('unificacion.departamentos.equivalencias', []) as $origen => $dep) {
            if (self::clave($origen) === $clave) {
                return $dep;
            }
        }

        return null;
    }

    private static function clave(string $v): string
    {
        return Str::lower(Str::ascii(trim($v)));
    }
}
