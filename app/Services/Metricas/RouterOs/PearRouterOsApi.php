<?php

namespace App\Services\Metricas\RouterOs;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use RuntimeException;

/** RouterOsApi sobre pear2/net_routeros, la misma librería que usa el resto del sistema. */
final class PearRouterOsApi implements RouterOsApi
{
    public function __construct(private readonly Client $client)
    {
    }

    public function leer(string $ruta, array $argumentos = [], array $propiedades = [], array $donde = []): array
    {
        $request = new Request($ruta);
        foreach ($argumentos as $nombre => $valor) {
            $request->setArgument($nombre, $valor);
        }
        if ($propiedades !== [] && str_ends_with($ruta, '/print')) {
            $request->setArgument('.proplist', implode(',', $propiedades));
        }

        $query = null;
        foreach ($donde as [$campo, $valor]) {
            $query = $query === null ? Query::where($campo, $valor) : $query->orWhere($campo, $valor);
        }
        if ($query !== null) {
            $request->setQuery($query);
        }

        $filas = [];
        foreach ($this->client->sendSync($request) as $respuesta) {
            $tipo = $respuesta->getType();
            if ($tipo === Response::TYPE_ERROR || $tipo === Response::TYPE_FATAL) {
                throw new RuntimeException('RouterOS respondió con error en ' . $ruta . ': ' . $this->valor($respuesta->getProperty('message')));
            }
            if ($tipo !== Response::TYPE_DATA) {
                continue;
            }
            $fila = [];
            foreach ($propiedades as $p) {
                $fila[$p] = $this->valor($respuesta->getProperty($p));
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    public function cerrar(): void
    {
        $this->client->close();
    }

    /** La librería puede devolver un stream en valores largos. */
    private function valor(mixed $v): ?string
    {
        if (is_resource($v)) {
            return stream_get_contents($v) ?: null;
        }

        return $v === null ? null : (string) $v;
    }
}
