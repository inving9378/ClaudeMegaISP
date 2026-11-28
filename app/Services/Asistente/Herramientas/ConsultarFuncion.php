<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Services\Asistente\Sistema\CatalogoFunciones;
use App\Services\Asistente\Sistema\DespachadorInterno;

/**
 * Corre una función de CONSULTA del sistema como si el usuario abriera la pantalla (mismos
 * permisos y validaciones). Las que cambian algo van por hacer_en_sistema.
 */
class ConsultarFuncion extends Herramienta
{
    use UsaArchivos;
    use UsaFunciones;

    public function nombre(): string
    {
        return 'consultar_funcion';
    }

    public function descripcion(): string
    {
        return 'Usa una función de CONSULTA del sistema (tipo «consulta» en buscar_funciones), igual que si el usuario abriera la pantalla: '
            . 'con sus permisos. Sirve para reportes, cálculos, estados de equipos y todo lo que una pantalla muestra. Si la función '
            . 'devuelve un archivo, te da el enlace para descargarlo. Las funciones que cambian algo no se pueden usar aquí: van con hacer_en_sistema.';
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
                'ruta'       => ['type' => 'string', 'description' => 'Ruta con sus valores reales (ej. /cliente/get-client-status/7692). Puede llevar ?filtros.'],
                'metodo'     => ['type' => 'string', 'description' => 'GET (lo normal) o POST para las consultas que lo piden.'],
                'datos_json' => ['type' => 'string', 'description' => 'Datos que pide la función, como objeto JSON en texto. Vacío si no pide nada.'],
            ],
            'required' => ['ruta'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $metodo = strtoupper(trim((string) ($args['metodo'] ?? 'GET'))) ?: 'GET';
        $ruta = $this->rutaLimpia((string) ($args['ruta'] ?? ''));
        $datos = $this->datosDe($args['datos_json'] ?? '');
        if (is_string($datos)) {
            return ['error' => $datos];
        }

        $catalogo = app(CatalogoFunciones::class);
        $f = $catalogo->encontrar($metodo, $ruta);
        if (!$f) {
            return ['error' => "No encuentro la función {$metodo} {$ruta} (o no está disponible para JARVIS). Búscala con buscar_funciones."];
        }
        if ($f['tipo'] !== 'consulta') {
            return ['error' => 'Esa función cambia cosas en el sistema: prepárala con hacer_en_sistema para que el usuario la confirme.'];
        }
        if (!$catalogo->puedeUsar($user, $f)) {
            return ['error' => 'Este usuario no tiene permiso para esa función.'];
        }

        $r = app(DespachadorInterno::class)->ejecutar($user, $metodo, $ruta, $datos, $this->conversacionDe($user));

        return $this->resultadoParaIa($r);
    }
}
