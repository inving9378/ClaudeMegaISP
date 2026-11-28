<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Services\Asistente\Datos\ConsultaRechazada;
use App\Services\Asistente\Datos\LectorBaseDatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * El mapa de TODA la información del sistema (todos los módulos): qué tablas hay, sus columnas,
 * cómo se relacionan y ejemplos. Es el paso previo a consultar_datos.
 */
class ExplorarDatos extends Herramienta
{
    public const PERMISO = 'ia-chat-consultar-todo';

    private const SINONIMOS = [
        'cliente' => ['client'], 'pago' => ['payment'], 'factura' => ['invoice', 'billing', 'document', 'factura'],
        'ticket' => ['ticket', 'task'], 'tarea' => ['task'], 'plan' => ['plan', 'package', 'bundle'], 'colonia' => ['colon'],
        'vendedor' => ['seller', 'vendedor'], 'servicio' => ['service'], 'red' => ['network', 'red', 'mikrotik', 'olt'],
        'inventario' => ['inventory'], 'usuario' => ['user'], 'equipo' => ['equipment', 'device', 'inventory', 'onu'],
        'prospecto' => ['crm', 'lead', 'prospect'], 'gasto' => ['expense', 'gasto'], 'empleado' => ['talento', 'colaborador', 'employee'],
        'vehiculo' => ['fleet', 'vehicle'], 'llamada' => ['call', 'voip', 'cdr', 'megavoz'], 'campana' => ['campaign', 'campana'],
        'caja' => ['caja', 'box', 'nap', 'mapared'], 'promocion' => ['promo'], 'nota' => ['note', 'comment'],
        'mensaje' => ['message', 'whatsapp', 'mensaje'], 'cobranza' => ['cobranza', 'billing', 'blaster'], 'saldo' => ['balance', 'saldo'],
        'contrato' => ['contrat', 'contract'], 'direccion' => ['address', 'direccion'], 'telefono' => ['phone', 'telefono'],
    ];

    public function nombre(): string
    {
        return 'explorar_datos';
    }

    public function descripcion(): string
    {
        return 'Mapa de TODA la información del sistema, de todos los módulos (clientes, pagos, facturas, red, inventario, '
            . 'talento, flotas, marketing, VoIP, etc.): qué tablas hay, qué columnas tienen, cómo se relacionan y ejemplos de sus datos. '
            . 'Úsala antes de consultar_datos cuando no sepas dónde está algo. Con «buscar» encuentra tablas por palabra '
            . '(en el nombre, sus columnas o su descripción; acepta español o inglés). Con «tabla» te da sus columnas, relaciones y 3 filas de ejemplo.';
    }

    public function permiso(): string|array|null
    {
        return self::PERMISO;
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
                'buscar' => ['type' => 'string', 'description' => 'Palabras para encontrar tablas, por ejemplo «pagos cliente» o «onu potencia».'],
                'tabla'  => ['type' => 'string', 'description' => 'Nombre exacto de una tabla para ver su detalle.'],
            ],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $lector = app(LectorBaseDatos::class);
        $tabla = trim((string) ($args['tabla'] ?? ''));
        if ($tabla !== '') {
            return $this->describir($lector, $tabla);
        }

        $buscar = trim((string) ($args['buscar'] ?? ''));

        return $buscar !== '' ? $this->buscar($lector, $buscar) : $this->panorama($lector);
    }

    private function panorama(LectorBaseDatos $lector): array
    {
        $grupos = [];
        foreach ($lector->tablas() as $min => $t) {
            if ($lector->tablaBloqueada($min)) {
                continue;
            }
            $prefijo = Str::before($min, '_');
            $grupos[$prefijo] = ($grupos[$prefijo] ?? 0) + 1;
        }
        arsort($grupos);

        return [
            'tablas'   => array_sum($grupos),
            'familias' => array_map(fn ($p, $n) => "{$p}_* ({$n})", array_keys($grupos), $grupos),
            'nota'     => 'Usa «buscar» con lo que necesitas para encontrar la tabla exacta.',
        ];
    }

    private function buscar(LectorBaseDatos $lector, string $texto): array
    {
        $grupos = [];
        foreach (preg_split('/[\s,;]+/', Str::lower(Str::ascii($texto))) as $p) {
            if (mb_strlen($p) < 3) {
                continue;
            }
            $raiz = preg_replace('/(es|s)$/', '', $p);
            $grupos[] = array_values(array_unique(array_filter(array_merge([$p, $raiz], self::SINONIMOS[$p] ?? [], self::SINONIMOS[$raiz] ?? []), fn ($v) => strlen($v) >= 3)));
        }
        if (!$grupos) {
            return ['error' => 'Dime al menos una palabra de 3 letras o más.'];
        }

        $columnas = [];
        foreach (DB::select('SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?', [DB::getDatabaseName()]) as $c) {
            $columnas[strtolower($c->t)][] = $c->c;
        }

        $resultados = [];
        foreach ($lector->tablas() as $min => $t) {
            if ($lector->tablaBloqueada($min)) {
                continue;
            }
            $puntos = 0;
            $todas = true;
            $coinciden = [];
            foreach ($grupos as $variantes) {
                $hit = 0;
                foreach ($variantes as $v) {
                    if (str_contains($min, $v)) {
                        $hit = max($hit, 5);
                    }
                    if ($t['comentario'] !== '' && str_contains(Str::lower(Str::ascii($t['comentario'])), $v)) {
                        $hit = max($hit, 3);
                    }
                    foreach ($columnas[$min] ?? [] as $col) {
                        if (str_contains(strtolower($col), $v)) {
                            $hit = max($hit, 1);
                            $coinciden[$col] = true;
                        }
                    }
                }
                $puntos += $hit;
                $todas = $todas && $hit > 0;
            }
            if ($puntos > 0) {
                $resultados[] = [
                    'p' => $puntos + ($todas ? 10 : 0) + min(3, log10(max(1, $t['filas']))),
                    'r' => array_filter([
                        'tabla'       => $t['nombre'],
                        'filas_aprox' => $t['filas'],
                        'descripcion' => $t['comentario'] ?: null,
                        'columnas_que_coinciden' => array_slice(array_keys($coinciden), 0, 6) ?: null,
                    ], fn ($v) => $v !== null),
                ];
            }
        }
        usort($resultados, fn ($a, $b) => $b['p'] <=> $a['p']);

        return [
            'tablas' => array_column(array_slice($resultados, 0, 30), 'r'),
            'nota'   => count($resultados) > 30 ? 'Hay ' . count($resultados) . ' coincidencias; se muestran las 30 mejores. Sé más concreto si no está la que buscas.' : null,
        ];
    }

    private function describir(LectorBaseDatos $lector, string $tabla): array
    {
        $tablas = $lector->tablas();
        $min = strtolower($tabla);
        if (!isset($tablas[$min])) {
            return ['error' => "No existe la tabla «{$tabla}». Búscala con «buscar»."];
        }
        if ($lector->tablaBloqueada($min)) {
            return ['error' => 'Esa información es interna del sistema (sesiones, llaves o colas) y no la consulto.'];
        }
        $nombre = $tablas[$min]['nombre'];
        $base = DB::getDatabaseName();

        $columnas = [];
        $visibles = [];
        $pk = null;
        foreach (DB::select('SELECT COLUMN_NAME n, COLUMN_TYPE t, IS_NULLABLE nu, COLUMN_KEY k, COLUMN_COMMENT c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$base, $nombre]) as $c) {
            $sensible = LectorBaseDatos::esSensible($c->n);
            $columnas[] = trim($c->n . ' ' . $c->t . ($c->k === 'PRI' ? ' (llave)' : '') . ($c->nu === 'NO' ? '' : ' null')
                . ($sensible ? ' (confidencial: no se consulta)' : '') . ($c->c !== '' ? ' — ' . $c->c : ''));
            if (!$sensible) {
                $visibles[] = $c->n;
            }
            if ($c->k === 'PRI' && $pk === null) {
                $pk = $c->n;
            }
        }

        $salen = array_map(fn ($r) => "{$r->c} → {$r->rt}.{$r->rc}", DB::select('SELECT COLUMN_NAME c, REFERENCED_TABLE_NAME rt, REFERENCED_COLUMN_NAME rc FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$base, $nombre]));
        $llegan = array_map(fn ($r) => "{$r->t}.{$r->c} → {$r->rc}", DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_COLUMN_NAME rc FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ? LIMIT 30', [$base, $nombre]));

        $ejemplo = null;
        if ($visibles) {
            $cols = implode(', ', array_map(fn ($c) => '`' . $c . '`', array_slice($visibles, 0, 40)));
            try {
                $ejemplo = $lector->consultar("SELECT {$cols} FROM `{$nombre}`" . ($pk ? " ORDER BY `{$pk}` DESC" : '') . ' LIMIT 3', [], 3, 80, 10);
            } catch (ConsultaRechazada) {
                $ejemplo = null;
            }
        }

        $notas = [];
        foreach ($ejemplo['filas'] ?? [] as $fila) {
            foreach ($fila as $i => $v) {
                $col = $ejemplo['columnas'][$i];
                if (is_string($v) && preg_match('#^\d{2}/\d{2}/\d{4}#', $v) && !isset($notas[$col])) {
                    $notas[$col] = "{$col} es una fecha guardada como texto día/mes/año: para compararla usa COALESCE(STR_TO_DATE({$col}, '%d/%m/%Y'), …), nunca como texto.";
                }
            }
        }

        return array_filter([
            'tabla'        => $nombre,
            'filas_aprox'  => $tablas[$min]['filas'],
            'descripcion'  => $tablas[$min]['comentario'] ?: null,
            'columnas'     => $columnas,
            'relaciones'   => $salen ?: null,
            'la_usan'      => $llegan ?: null,
            'ejemplo'      => $ejemplo ? ['columnas' => $ejemplo['columnas'], 'filas' => $ejemplo['filas']] : null,
            'notas'        => $notas ? array_values($notas) : null,
        ], fn ($v) => $v !== null);
    }
}
