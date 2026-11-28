<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\Asistente\Adjunto;
use App\Models\User;
use App\Services\Asistente\AdjuntosAsistente;

/**
 * Relee un archivo que el usuario mandó: filas de un Excel (con búsqueda, páginas y un resumen de
 * cada columna) o el texto de un PDF, Word, audio o video. No necesita permisos extra: solo lee lo
 * que el propio usuario adjuntó.
 */
class LeerArchivo extends Herramienta
{
    private const MAX_FILAS = 200;

    private const CARACTERES = 12000;

    public function nombre(): string
    {
        return 'leer_archivo';
    }

    public function descripcion(): string
    {
        return 'Lee un archivo que te mandaron en el chat (por su número). Excel/CSV: filas por páginas («desde», «cuantas» hasta '
            . self::MAX_FILAS . '), solo ciertas «columnas», las filas que contengan un texto («buscar») o «resumen» = estadísticas de cada '
            . 'columna (vacíos, distintos, suma, mínimo, máximo, valores más comunes). PDF, Word, texto, audio y video: su texto '
            . '(o lo que se oye) a partir del carácter «desde».';
    }

    public function maxCaracteresResultado(): ?int
    {
        return 24000;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'archivo'  => ['type' => 'integer', 'description' => 'Número del archivo (#).'],
                'hoja'     => ['type' => 'integer', 'description' => 'Número de hoja del Excel (1 = la primera).'],
                'desde'    => ['type' => 'integer', 'description' => 'Excel: fila desde la que se lee (0 = la primera). Texto: carácter desde el que se lee.'],
                'cuantas'  => ['type' => 'integer', 'description' => 'Excel: cuántas filas (máximo ' . self::MAX_FILAS . ').'],
                'columnas' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Excel: identificadores de las columnas a traer.'],
                'buscar'   => ['type' => 'string', 'description' => 'Excel: trae solo las filas que contienen este texto.'],
                'resumen'  => ['type' => 'boolean', 'description' => 'Excel: estadísticas de cada columna en lugar de filas.'],
            ],
            'required' => ['archivo'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $a = Adjunto::where('id', (int) ($args['archivo'] ?? 0))->where('user_id', $user->id)->first();
        if (!$a) {
            return ['error' => 'No encuentro ese archivo entre los que te mandó este usuario.'];
        }
        if (!$a->disponible()) {
            return ['error' => "El archivo «{$a->nombre}» ya se borró (los archivos se borran solos). Pide que lo vuelvan a mandar."];
        }

        return match ($a->tipo) {
            'hoja'   => $this->hoja($a, $args),
            'imagen' => ['nota' => "«{$a->nombre}» es una foto: la viste en el mensaje donde llegó. Si ya no la tienes a la vista, pide que la vuelvan a mandar."],
            default  => $this->texto($a, $args),
        };
    }

    private function hoja(Adjunto $a, array $args): array
    {
        $servicio = app(AdjuntosAsistente::class);
        $n = max(1, (int) ($args['hoja'] ?? 1));
        $datos = $servicio->hoja($a, $n);
        if (!$datos) {
            return ['error' => "El archivo no tiene la hoja {$n}. Tiene " . count((array) $a->meta('hojas', [])) . '.'];
        }
        $ids = array_column($datos['columnas'], 'id');

        if (!empty($args['resumen'])) {
            return ['archivo' => $a->nombre, 'hoja' => $datos['nombre'], 'filas' => count($datos['filas']), 'columnas' => $this->resumen($datos)];
        }

        $elegidas = array_values(array_intersect($ids, array_map('strval', (array) ($args['columnas'] ?? [])))) ?: $ids;
        $indices = array_map(fn ($c) => array_search($c, $ids, true), $elegidas);

        $filas = [];
        $buscar = mb_strtolower(trim((string) ($args['buscar'] ?? '')));
        foreach ($datos['filas'] as $i => $fila) {
            if ($buscar !== '' && !str_contains(mb_strtolower(implode(' ', array_map('strval', array_filter($fila, fn ($v) => $v !== null)))), $buscar)) {
                continue;
            }
            $filas[] = array_merge([$i + 1], array_map(fn ($x) => $fila[$x] ?? null, $indices));
        }

        $desde = max(0, (int) ($args['desde'] ?? 0));
        $cuantas = min(self::MAX_FILAS, max(1, (int) ($args['cuantas'] ?? 50)));
        $pagina = array_slice($filas, $desde, $cuantas);

        return array_filter([
            'archivo'      => $a->nombre,
            'hoja'         => $datos['nombre'],
            'columnas'     => array_merge(['_fila'], $elegidas),
            'titulos'      => array_merge(['Fila'], array_map(fn ($x) => $datos['columnas'][$x]['titulo'], $indices)),
            'filas'        => $pagina,
            'total_filas'  => count($datos['filas']),
            'coinciden'    => $buscar !== '' ? count($filas) : null,
            'desde'        => $desde,
            'hay_mas'      => $desde + $cuantas < count($filas) ? true : null,
        ], fn ($v) => $v !== null);
    }

    private function resumen(array $datos): array
    {
        $salida = [];
        foreach ($datos['columnas'] as $i => $col) {
            $valores = array_column($datos['filas'], $i);
            $llenos = array_values(array_filter($valores, fn ($v) => $v !== null && $v !== ''));
            $r = ['id' => $col['id'], 'titulo' => $col['titulo'], 'tipo' => $col['tipo'], 'llenos' => count($llenos), 'vacios' => count($valores) - count($llenos)];
            $distintos = array_count_values(array_map('strval', $llenos));
            $r['distintos'] = count($distintos);
            if ($col['tipo'] === 'numero' && $llenos) {
                $r += ['suma' => round(array_sum($llenos), 2), 'minimo' => min($llenos), 'maximo' => max($llenos), 'promedio' => round(array_sum($llenos) / count($llenos), 2)];
            } elseif ($col['tipo'] === 'fecha' && $llenos) {
                $r += ['desde' => min($llenos), 'hasta' => max($llenos)];
            }
            if ($col['tipo'] !== 'numero' || count($distintos) <= 20) {
                arsort($distintos);
                $r['mas_comunes'] = array_map(fn ($v, $n) => mb_substr((string) $v, 0, 60) . " ({$n})", array_keys(array_slice($distintos, 0, 5, true)), array_slice($distintos, 0, 5, true));
            }
            $salida[] = $r;
        }

        return $salida;
    }

    private function texto(Adjunto $a, array $args): array
    {
        $texto = app(AdjuntosAsistente::class)->texto($a);
        if (trim($texto) === '') {
            return ['nota' => "«{$a->nombre}» no tiene texto que se pueda leer" . ($a->tipo === 'pdf' && $a->meta('escaneado') ? ' (es un PDF escaneado: sus páginas las viste como imágenes).' : '.')];
        }
        $desde = max(0, (int) ($args['desde'] ?? 0));
        $total = mb_strlen($texto);
        $trozo = mb_substr($texto, $desde, self::CARACTERES);

        return array_filter([
            'archivo'          => $a->nombre,
            'tipo'             => $a->tipo,
            'texto'            => $trozo,
            'desde'            => $desde,
            'hasta'            => $desde + mb_strlen($trozo),
            'total_caracteres' => $total,
            'hay_mas'          => $desde + mb_strlen($trozo) < $total ? true : null,
        ], fn ($v) => $v !== null);
    }
}
