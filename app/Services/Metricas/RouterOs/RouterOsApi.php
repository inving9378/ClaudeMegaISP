<?php

namespace App\Services\Metricas\RouterOs;

/**
 * Lo único que el monitoreo le pide a la API de RouterOS: comandos de lectura que devuelven
 * filas. Así la sesión se prueba con fixtures sin un router real (item #988).
 */
interface RouterOsApi
{
    /**
     * @param  string  $ruta  p. ej. '/interface/print', '/system/identity/print', '/ping'
     * @param  array<string, string>  $argumentos
     * @param  string[]  $propiedades  las que se quieren de cada fila (en un print, también .proplist)
     * @param  array<int, array{0: string, 1: string}>  $donde  filtros campo = valor, unidos con O
     * @return array<int, array<string, string|null>>
     *
     * @throws \RuntimeException si el router contesta con error
     */
    public function leer(string $ruta, array $argumentos = [], array $propiedades = [], array $donde = []): array;

    public function cerrar(): void;
}
