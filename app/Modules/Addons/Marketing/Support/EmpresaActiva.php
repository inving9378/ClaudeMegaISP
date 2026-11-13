<?php

namespace App\Modules\Addons\Marketing\Support;

use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use InvalidArgumentException;

/**
 * Empresa (marca) activa de la petición en curso.
 *
 * La fija ÚNICAMENTE el middleware `marketing.empresa` (rutas autenticadas del módulo Marketing).
 * Registrada con $app->scoped(): se limpia sola entre peticiones y entre jobs de cola, así que
 * webhooks, jobs, comandos y otros módulos nunca la ven puesta (el scope no filtra sin contexto).
 */
class EmpresaActiva
{
    private ?Empresa $empresa = null;

    private ?string $rol = null;

    public function establecer(Empresa $empresa, string $rol): void
    {
        if (! in_array($rol, EmpresaUsuario::ROLES, true)) {
            throw new InvalidArgumentException("Rol de empresa no válido: {$rol}");
        }

        $this->empresa = $empresa;
        $this->rol = $rol;
    }

    public function limpiar(): void
    {
        $this->empresa = null;
        $this->rol = null;
    }

    public function hay(): bool
    {
        return $this->empresa !== null;
    }

    public function id(): ?int
    {
        return $this->empresa ? (int) $this->empresa->getKey() : null;
    }

    public function empresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function rol(): ?string
    {
        return $this->rol;
    }
}
