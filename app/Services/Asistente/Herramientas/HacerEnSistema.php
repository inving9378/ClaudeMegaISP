<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Services\Asistente\Sistema\CatalogoFunciones;
use App\Services\Asistente\Sistema\DespachadorInterno;

/**
 * Cualquier cambio que el usuario podría hacer con clics, en cualquier módulo: una o varias
 * operaciones (p. ej. una por renglón de un Excel) que se aplican con un solo «sí». Cada operación
 * pasa por la ruta real de la pantalla: sus permisos, su validación y su controlador.
 */
class HacerEnSistema extends Herramienta
{
    use UsaArchivos;
    use UsaFunciones;

    public function nombre(): string
    {
        return 'hacer_en_sistema';
    }

    public function descripcion(): string
    {
        return 'Prepara uno o varios CAMBIOS en cualquier módulo del sistema usando sus funciones (las de tipo «cambio» en buscar_funciones), '
            . 'como si el usuario hiciera los clics: crear, editar, borrar, asignar, suspender, registrar… Acepta hasta '
            . $this->maximo() . ' operaciones para automatizar trabajo repetitivo (por ejemplo, una por cada renglón de un Excel). '
            . 'Nada se aplica hasta que el usuario confirma. Antes de usarla, revisa el detalle de la función (buscar_funciones con ruta y metodo) '
            . 'para mandar exactamente los datos que pide. Para cambios que ya tienen su propia herramienta (crear ticket, prospecto o cliente, '
            . 'actualizar contacto) usa esa.';
    }

    public function esCambio(): bool
    {
        return true;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'resumen'     => ['type' => 'string', 'description' => 'Qué se va a hacer, en lenguaje del usuario y sin términos técnicos (ej. «Registrar los 48 pagos del Excel de Oxxo»). Es lo que verá para confirmar.'],
                'operaciones' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'metodo'     => ['type' => 'string', 'description' => 'POST, PUT, PATCH o DELETE (o GET si la función así lo pide).'],
                            'ruta'       => ['type' => 'string', 'description' => 'Ruta con sus valores reales, ej. /cliente/update/7692.'],
                            'datos_json' => ['type' => 'string', 'description' => 'Datos de la operación como objeto JSON en texto.'],
                            'etiqueta'   => ['type' => 'string', 'description' => 'A quién o qué afecta, para el reporte (ej. «fila 12 · Juan Pérez»).'],
                        ],
                        'required' => ['metodo', 'ruta'],
                    ],
                ],
            ],
            'required' => ['resumen', 'operaciones'],
        ];
    }

    public function preparar(User $user, array $args): array
    {
        $resumen = trim(mb_substr((string) ($args['resumen'] ?? ''), 0, 600));
        $operaciones = array_values((array) ($args['operaciones'] ?? []));
        if ($resumen === '') {
            return ['error' => 'Falta el resumen de lo que se va a hacer, en palabras del usuario.'];
        }
        if (!$operaciones) {
            return ['error' => 'No hay operaciones que preparar.'];
        }
        if (count($operaciones) > $this->maximo()) {
            return ['error' => 'Son ' . count($operaciones) . ' operaciones; el máximo por confirmación es ' . $this->maximo() . '. Divídelas en tandas.'];
        }

        $catalogo = app(CatalogoFunciones::class);
        $limpias = [];
        foreach ($operaciones as $i => $op) {
            $n = $i + 1;
            $metodo = strtoupper(trim((string) ($op['metodo'] ?? '')));
            $ruta = $this->rutaLimpia((string) ($op['ruta'] ?? ''));
            if (!in_array($metodo, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return ['error' => "Operación {$n}: el método «{$metodo}» no es válido."];
            }
            $datos = $this->datosDe($op['datos_json'] ?? ($op['datos'] ?? ''));
            if (is_string($datos)) {
                return ['error' => "Operación {$n}: {$datos}"];
            }
            $f = $catalogo->encontrar($metodo, $ruta);
            if (!$f) {
                return ['error' => "Operación {$n}: no encuentro la función {$metodo} {$ruta} (o no está disponible para JARVIS)."];
            }
            if (!$catalogo->puedeUsar($user, $f)) {
                return ['error' => "Operación {$n}: este usuario no tiene permiso para {$metodo} {$ruta}."];
            }
            $limpias[] = array_filter([
                'metodo'   => $metodo,
                'ruta'     => $ruta,
                'datos'    => $datos,
                'etiqueta' => trim(mb_substr((string) ($op['etiqueta'] ?? ''), 0, 120)) ?: null,
            ], fn ($v) => $v !== null);
        }

        $n = count($limpias);
        $cuantos = $n === 1 ? '1 cambio' : "{$n} cambios";

        return ['resumen' => "{$resumen}\n\n_({$cuantos} en el sistema)_", 'argumentos' => ['resumen' => $resumen, 'operaciones' => $limpias]];
    }

    public function ejecutar(User $user, array $args): array
    {
        @set_time_limit(600);
        $despachador = app(DespachadorInterno::class);
        $conversacion = $this->conversacionDe($user);
        $operaciones = (array) ($args['operaciones'] ?? []);

        $hechas = 0;
        $fallas = [];
        $enlace = null;
        foreach ($operaciones as $i => $op) {
            try {
                $r = $despachador->ejecutar($user, (string) $op['metodo'], (string) $op['ruta'], (array) ($op['datos'] ?? []), $conversacion);
            } catch (\Throwable $e) {
                $r = ['ok' => false, 'estado' => 500, 'tipo' => 'texto', 'cuerpo' => 'falló sin respuesta'];
            }
            if ($r['ok']) {
                $hechas++;
                $enlace ??= $r['enlace'] ?? ($r['tipo'] === 'redireccion' && is_string($r['cuerpo']) && count($operaciones) === 1 ? $r['cuerpo'] : null);
                continue;
            }
            $fallas[] = '- ' . ($op['etiqueta'] ?? ('Operación ' . ($i + 1))) . ': ' . $this->motivo($r);
        }

        $total = count($operaciones);
        if ($hechas === 0) {
            return ['error' => ($total === 1 ? 'No se pudo hacer el cambio: ' . ltrim(substr($fallas[0], strpos($fallas[0], ':') + 1)) : "No se pudo hacer ninguno de los {$total} cambios:\n" . implode("\n", array_slice($fallas, 0, 15)))];
        }

        $mensaje = $hechas === $total
            ? ($total === 1 ? 'Listo, quedó hecho.' : "Listo, se hicieron los {$total} cambios.")
            : "Se hicieron {$hechas} de {$total} cambios. Estos no se pudieron:\n" . implode("\n", array_slice($fallas, 0, 15)) . (count($fallas) > 15 ? "\n- …y " . (count($fallas) - 15) . ' más.' : '');

        return array_filter(['mensaje' => $mensaje, 'enlace' => $enlace, 'hechas' => $hechas, 'fallidas' => count($fallas)], fn ($v) => $v !== null);
    }

    /** Por qué falló, en palabras de la propia pantalla (sus mensajes de validación o de error). */
    private function motivo(array $r): string
    {
        $c = $r['cuerpo'];
        if (is_array($c)) {
            if (!empty($c['errors']) && is_array($c['errors'])) {
                return implode(' ', array_map(fn ($e) => is_array($e) ? implode(' ', $e) : (string) $e, array_slice($c['errors'], 0, 3)));
            }
            foreach (['message', 'mensaje', 'error', 'msg'] as $k) {
                if (!empty($c[$k]) && is_string($c[$k])) {
                    return mb_substr($c[$k], 0, 200);
                }
            }
        }
        if (!empty($r['avisos']['errors'])) {
            return implode(' ', array_slice((array) $r['avisos']['errors'], 0, 3));
        }
        if (!empty($r['avisos']['error'])) {
            return (string) $r['avisos']['error'];
        }

        return match (true) {
            $r['estado'] === 403 => 'sin permiso',
            $r['estado'] === 404 => 'no se encontró',
            $r['estado'] === 419 => 'la sesión venció',
            $r['estado'] >= 500  => 'error del sistema',
            default              => 'no se aplicó',
        };
    }

    private function maximo(): int
    {
        return (int) $this->conocimiento->ajuste('max_operaciones_por_cambio', 100);
    }
}
