<?php

namespace App\Services\Metricas;

use RuntimeException;
use Throwable;

/** No se pudo abrir la conexión con el router; trae el host que se intentó (item #988). */
final class SinConexionRouter extends RuntimeException
{
    public function __construct(public readonly string $host, string $mensaje, ?Throwable $previo = null)
    {
        parent::__construct("Sin conexión con {$host}: {$mensaje}", 0, $previo);
    }
}
