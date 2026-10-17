<?php

namespace App\Modules\Addons\PortalCliente\Services;

use RuntimeException;

/**
 * Otro administrador cambió el borrador (o lo publicó/descartó) después de que éste lo cargó.
 * El controlador lo convierte en HTTP 409 para que el panel pida recargar en vez de sobrescribir.
 */
class AparienciaConflictoException extends RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly ?string $por = null,
        public readonly ?string $fecha = null,
    ) {
        parent::__construct($mensaje);
    }
}
