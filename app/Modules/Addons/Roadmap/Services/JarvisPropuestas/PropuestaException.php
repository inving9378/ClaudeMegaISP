<?php

namespace App\Modules\Addons\Roadmap\Services\JarvisPropuestas;

/** #1110 — error con código y mensaje llano, listo para mostrar en la pantalla. */
class PropuestaException extends \RuntimeException
{
    public function __construct(public readonly string $codigo, string $mensaje, public readonly int $http = 422)
    {
        parent::__construct($mensaje);
    }
}
