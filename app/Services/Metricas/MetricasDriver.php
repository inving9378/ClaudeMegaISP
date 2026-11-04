<?php

namespace App\Services\Metricas;

use App\Models\Router;

/** De dónde salen las métricas de los dedicados: contadores de la VLAN y ping (item #988). */
interface MetricasDriver
{
    /** 'mikrotik' | 'simulado' — se guarda como origen de cada fila. */
    public function nombre(): string;

    /**
     * Abre la conexión con un router para leerle todo lo de esta corrida.
     *
     * @throws \RuntimeException si no hay conexión con el router
     */
    public function abrir(Router $router): SesionRouter;
}
