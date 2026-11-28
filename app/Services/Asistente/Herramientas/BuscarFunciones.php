<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Services\Asistente\Sistema\CatalogoFunciones;

/**
 * Encuentra las funciones del sistema (las que usan las pantallas) que el usuario puede usar, y el
 * detalle de una: qué datos pide y cómo los valida.
 */
class BuscarFunciones extends Herramienta
{
    public function nombre(): string
    {
        return 'buscar_funciones';
    }

    public function descripcion(): string
    {
        return 'Busca, entre TODAS las funciones del sistema (las mismas que usan sus pantallas, de cualquier módulo), las que sirven para '
            . 'lo que te piden y que este usuario puede usar. Cada una es «consulta» (se corre con consultar_funcion) o «cambio» (se prepara '
            . 'con hacer_en_sistema y el usuario la confirma). Con «ruta» y «metodo» te da el detalle de una función: qué datos pide, cómo los '
            . 'valida y su código, para mandarle los datos correctos.';
    }

    public function maxCaracteresResultado(): ?int
    {
        return 20000;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'buscar' => ['type' => 'string', 'description' => 'Qué se quiere hacer, en pocas palabras (ej. «suspender servicio cliente», «crear gasto»).'],
                'ruta'   => ['type' => 'string', 'description' => 'Ruta de una función para ver su detalle (ej. /cliente/update/{id}).'],
                'metodo' => ['type' => 'string', 'description' => 'GET, POST, PUT, PATCH o DELETE (con «ruta»).'],
            ],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $catalogo = app(CatalogoFunciones::class);
        $ruta = trim((string) ($args['ruta'] ?? ''));
        if ($ruta !== '') {
            $metodo = strtoupper(trim((string) ($args['metodo'] ?? 'GET'))) ?: 'GET';
            $f = $catalogo->encontrar($metodo, preg_replace('/\{\w+\??\}/', '1', $ruta));
            if (!$f) {
                return ['error' => "No encuentro la función {$metodo} {$ruta} (o no está disponible para JARVIS). Búscala con «buscar»."];
            }
            if (!$catalogo->puedeUsar($user, $f)) {
                return ['error' => 'Este usuario no tiene permiso para esa función.'];
            }

            return $catalogo->detalle($f);
        }

        $buscar = trim((string) ($args['buscar'] ?? ''));
        if ($buscar === '') {
            return ['error' => 'Dime qué buscar o la ruta de una función.'];
        }
        $funciones = $catalogo->buscar($user, $buscar);

        return $funciones
            ? ['funciones' => $funciones, 'nota' => 'Antes de usar una que no conoces, pide su detalle (ruta + metodo) para saber qué datos manda.']
            : ['funciones' => [], 'nota' => 'No encontré funciones con esas palabras. Prueba con otras (en español o inglés).'];
    }
}
