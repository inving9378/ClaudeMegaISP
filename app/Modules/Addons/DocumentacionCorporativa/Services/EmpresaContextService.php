<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcEmpresa;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Resuelve qué empresa está viendo el usuario.
 *
 * Selector en el header, persistido en sesión. Si sólo hay una empresa activa el
 * selector se oculta y se usa esa — el caso de Meganet hoy, y el de casi
 * cualquier ISP que arriende MegaISP mañana.
 */
class EmpresaContextService
{
    private const CLAVE_SESION = 'dc.empresa_id';

    public function activas(): Collection
    {
        return DcEmpresa::activas()->orderBy('razon_social')->get();
    }

    public function actual(): DcEmpresa
    {
        $activas = $this->activas();

        if ($activas->isEmpty()) {
            throw new RuntimeException(
                'No hay ninguna empresa activa en `dc_empresas`. Corre el seeder del módulo.'
            );
        }

        $elegida = session(self::CLAVE_SESION);
        $empresa = $elegida ? $activas->firstWhere('id', (int) $elegida) : null;

        // Sesión apuntando a una empresa desactivada o borrada: se corrige sola.
        if (! $empresa) {
            $empresa = $activas->first();
            session([self::CLAVE_SESION => $empresa->id]);
        }

        return $empresa;
    }

    public function actualId(): int
    {
        return $this->actual()->id;
    }

    /** Cambia la empresa activa. Devuelve false si el id no es una empresa activa. */
    public function cambiar(int $empresaId): bool
    {
        if (! $this->activas()->contains('id', $empresaId)) {
            return false;
        }

        session([self::CLAVE_SESION => $empresaId]);

        return true;
    }

    /** El selector sólo tiene sentido con más de una empresa. */
    public function mostrarSelector(): bool
    {
        return $this->activas()->count() > 1;
    }
}
