<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ITEM #711 (Jarvis Parte 1) — capa de PREGUNTAS sobre el índice vivo.
 *
 * ANTI-ALUCINACIÓN (la regla que manda en #644): toda afirmación de hecho viaja con su
 * procedencia (archivo+línea, tabla+columna, o medición con su hora); si no se puede citar,
 * contesta "no lo sé" — nunca rellena. Y si la respuesta sale del índice ESTÁTICO (estructura
 * de módulos, derivada de archivos) y el repo avanzó desde que se construyó, lo avisa ANTES de
 * contestar.
 *
 * Dos fuentes distintas, con distinta caducidad:
 *   - ÍNDICE (JarvisIndiceService): módulos/tablas-declaradas/rutas/comandos/schedule. Solo
 *     cambia con un commit → se compara el commit del índice contra HEAD y se avisa si difiere.
 *   - EN VIVO (esta clase, sin caché): esquema real de una tabla (information_schema), valor
 *     efectivo de una clave de config, e historial de la Hoja de Ruta (BD). Estos se miden en
 *     el momento de cada pregunta, así que NUNCA pueden quedar viejos — no llevan aviso de
 *     desactualización porque no hay caché que desactualizar.
 */
class JarvisConocimientoService
{
    public function __construct(private JarvisIndiceService $indexador)
    {
    }

    public function preguntar(string $pregunta): array
    {
        $pregunta = trim($pregunta);
        $texto = mb_strtolower($pregunta);

        $indice = $this->indexador->cargar();
        if ($indice === null) {
            $indice = $this->indexador->construirYGuardar();
        }

        $commitIndice = $indice['commit'] ?? null;
        $commitActual = $this->indexador->commitActual();
        $desactualizado = $commitIndice !== null && $commitActual !== null && $commitIndice !== $commitActual;

        // 1) Item de la Hoja de Ruta — SIEMPRE en vivo (lee la BD, nunca el índice estático).
        if (preg_match('/#\s?(\d{1,6})\b/', $pregunta, $m) || preg_match('/\bitem\s+(\d{1,6})\b/i', $pregunta, $m)) {
            $item = RoadmapItem::find((int) $m[1]);
            if ($item) {
                return $this->respuesta(
                    true,
                    "Item #{$item->id} «{$item->title}» — estado: {$item->status} / {$item->estado_aprobacion}. "
                        . 'Módulo: ' . ($item->modulo ?: 'sin clasificar') . '.',
                    [['tipo' => 'item_roadmap', 'id' => $item->id, 'tabla' => 'roadmap_items', 'medido_at' => now()->toIso8601String()]],
                    false,
                    $commitIndice,
                    $commitActual
                );
            }
        }

        // 2) Comando circuito:xxx — estructural, del índice.
        if (preg_match('/circuito:[a-z0-9\-]+/i', $texto, $m)) {
            foreach ($indice['comandos_circuito'] as $c) {
                if (mb_strtolower((string) $c['comando']) === mb_strtolower($m[0])) {
                    return $this->respuesta(
                        true,
                        "Comando `{$c['comando']}` — clase {$c['clase']}. " . ($c['descripcion'] ?: 'sin $description declarado') . '.',
                        [['tipo' => 'archivo_linea', 'archivo' => $c['archivo'], 'linea' => $c['linea']]],
                        $desactualizado,
                        $commitIndice,
                        $commitActual
                    );
                }
            }
        }

        // 3) Tabla conocida (declarada por alguna migración de módulo) — el nombre de tabla se
        // resuelve contra el índice, pero el ESQUEMA se mide siempre en vivo (nunca del índice).
        $tablasConocidas = [];
        foreach ($indice['modulos'] as $mod) {
            foreach ($mod['tablas'] as $t) {
                $tablasConocidas[$t['tabla']] = $mod['slug'];
            }
        }
        foreach ($tablasConocidas as $tabla => $moduloSlug) {
            if (preg_match('/\b' . preg_quote(mb_strtolower($tabla), '/') . '\b/u', $texto)) {
                return $this->respuestaTabla($tabla, $moduloSlug);
            }
        }

        // 4) Módulo por slug o nombre — estructural, del índice. Con LÍMITE DE PALABRA (\b): un
        // candidato corto como "ia" NO debe disparar dentro de "Francia" — sin \b, str_contains
        // lo haría (medido: "cuál es la capital de Francia" contestaba el módulo IA).
        foreach ($indice['modulos'] as $mod) {
            $candidatos = array_unique(array_filter([
                mb_strtolower($mod['slug']),
                mb_strtolower(str_replace('addon-', '', $mod['slug'])),
                mb_strtolower(str_replace('core-', '', $mod['slug'])),
                mb_strtolower($mod['name']),
            ]));
            foreach ($candidatos as $cand) {
                if ($cand !== '' && preg_match('/\b' . preg_quote($cand, '/') . '\b/u', $texto)) {
                    return $this->respuestaModulo($mod, $desactualizado, $commitIndice, $commitActual);
                }
            }
        }

        // 5) Clave de config con puntos ("circuito.jarvis.escalamiento") — en vivo.
        if (preg_match('/\b([a-z_]+(?:\.[a-z0-9_]+){1,5})\b/i', $pregunta, $m)) {
            $cfg = $this->configKey($m[1]);
            if ($cfg !== null) {
                $citas = $cfg['cita'] ? [array_merge(['tipo' => 'archivo_linea'], $cfg['cita'])] : [];
                $valorTxt = is_scalar($cfg['valor']) || $cfg['valor'] === null
                    ? json_encode($cfg['valor'], JSON_UNESCAPED_UNICODE)
                    : json_encode($cfg['valor'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return $this->respuesta(
                    true,
                    "config('{$m[1]}') = {$valorTxt} (medido ahora, no es del índice — no puede quedar viejo).",
                    $citas ?: [['tipo' => 'medicion', 'detalle' => "config('{$m[1]}') evaluado en vivo", 'medido_at' => $cfg['medido_at']]],
                    false,
                    $commitIndice,
                    $commitActual
                );
            }
        }

        return $this->respuesta(
            false,
            'No lo sé — no tengo eso derivado todavía. Dame un módulo (nombre o slug), una tabla, '
                . 'un comando circuito:*, un item #N de la Hoja de Ruta, o una clave de config con puntos, y lo busco.',
            [],
            false,
            $commitIndice,
            $commitActual
        );
    }

    // ── En vivo (sin caché, no puede quedar viejo) ─────────────────────────

    public function tablaReal(string $tabla): ?array
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $tabla)) {
            return null;
        }

        $medidoAt = now()->toIso8601String();

        if (! Schema::hasTable($tabla)) {
            return ['existe' => false, 'columnas' => [], 'medido_at' => $medidoAt];
        }

        $columnas = DB::select('SHOW FULL COLUMNS FROM `' . $tabla . '`');

        return [
            'existe'   => true,
            'columnas' => array_map(static fn ($c) => [
                'nombre'  => $c->Field,
                'tipo'    => $c->Type,
                'nulo'    => $c->Null === 'YES',
                'default' => $c->Default,
                'extra'   => $c->Extra,
            ], $columnas),
            'medido_at' => $medidoAt,
        ];
    }

    public function configKey(string $key): ?array
    {
        if (! preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/i', $key)) {
            return null;
        }

        [$archivoConfig] = explode('.', $key, 2);
        $rutaArchivo = config_path($archivoConfig . '.php');
        if (! is_file($rutaArchivo)) {
            return null;
        }

        $ultimaClave = (string) Str::afterLast($key, '.');
        $cita = null;
        foreach (file($rutaArchivo) ?: [] as $i => $linea) {
            if (preg_match('/[\'"]' . preg_quote($ultimaClave, '/') . '[\'"]\s*=>/', $linea)) {
                $cita = ['archivo' => $this->relativo($rutaArchivo), 'linea' => $i + 1];
                break;
            }
        }

        return [
            'valor'     => config($key),
            'cita'      => $cita,
            'medido_at' => now()->toIso8601String(),
        ];
    }

    // ── Helpers de armado de respuesta ─────────────────────────────────────

    private function respuestaTabla(string $tabla, string $moduloSlug): array
    {
        $real = $this->tablaReal($tabla);
        // Grep en vivo sobre TODOS los módulos (barato, on-demand): nunca depende de que el
        // índice estático esté al día para citar la migración correcta.
        $migraciones = $this->grepTablaEnTodosLosModulos($tabla);

        if (! $real || ! $real['existe']) {
            return $this->respuesta(
                false,
                "La tabla `{$tabla}` aparece declarada en una migración pero no existe en la BD real "
                    . '(¿migración pendiente de correr?). Eso es un hallazgo, no un detalle.',
                array_map(fn ($m) => ['tipo' => 'archivo_linea', 'archivo' => $m['archivo'], 'linea' => $m['linea']], $migraciones),
                false,
                null,
                null
            );
        }

        $nombresColumnas = implode(', ', array_map(fn ($c) => $c['nombre'], array_slice($real['columnas'], 0, 12)));
        $sufijo = count($real['columnas']) > 12 ? '… (+' . (count($real['columnas']) - 12) . ' más)' : '';

        $citas = array_map(fn ($m) => ['tipo' => 'archivo_linea', 'archivo' => $m['archivo'], 'linea' => $m['linea']], $migraciones);
        $citas[] = ['tipo' => 'tabla_columna', 'tabla' => $tabla, 'medido_at' => $real['medido_at']];

        return $this->respuesta(
            true,
            "Tabla `{$tabla}` (módulo {$moduloSlug}) — " . count($real['columnas']) . " columnas reales, medidas ahora: "
                . "{$nombresColumnas}{$sufijo}.",
            $citas,
            false,
            null,
            null
        );
    }

    private function grepTablaEnTodosLosModulos(string $tabla): array
    {
        $out = [];
        foreach (['Core', 'Addons'] as $tier) {
            foreach (glob(app_path("Modules/{$tier}/*/migrations"), GLOB_ONLYDIR) ?: [] as $dirAbs) {
                $rel = $this->relativo($dirAbs);
                $out = array_merge($out, $this->indexador->grepSchemaEnDir($dirAbs, $rel, $tabla));
            }
        }
        if (is_dir(base_path('database/migrations'))) {
            $out = array_merge($out, $this->indexador->grepSchemaEnDir(base_path('database/migrations'), 'database/migrations', $tabla));
        }

        return $out;
    }

    private function respuestaModulo(array $mod, bool $desactualizado, ?string $commitIndice, ?string $commitActual): array
    {
        $nTablas = count($mod['tablas']);
        $nRutas = count($mod['rutas']);
        $nComandos = count($mod['comandos']);
        $nPermisos = count($mod['permisos']);

        $texto = "Módulo «{$mod['name']}» ({$mod['slug']}), " . ($mod['activo'] ? 'activo' : 'INACTIVO') . '. '
            . "{$nTablas} tabla(s) declarada(s), {$nRutas} ruta(s) en routes.php, {$nComandos} comando(s) artisan, "
            . "{$nPermisos} permiso(s) en module.json. Directorio: {$mod['dir']}.";

        $citas = [['tipo' => 'archivo_linea', 'archivo' => $mod['dir'] . '/module.json', 'linea' => 1]];
        foreach (array_slice($mod['tablas'], 0, 5) as $t) {
            $citas[] = ['tipo' => 'archivo_linea', 'archivo' => $t['archivo'], 'linea' => $t['linea'], 'nota' => "tabla {$t['tabla']}"];
        }

        return $this->respuesta(true, $texto, $citas, $desactualizado, $commitIndice, $commitActual);
    }

    private function respuesta(bool $seSabe, string $texto, array $citas, bool $desactualizado, ?string $commitIndice, ?string $commitActual): array
    {
        if ($desactualizado) {
            $texto = 'Aviso: mi índice es del commit ' . substr((string) $commitIndice, 0, 12)
                . ', y main ya avanzó a ' . substr((string) $commitActual, 0, 12)
                . ' — esta parte de la respuesta puede no reflejar los cambios más recientes. ' . $texto;
        }

        return [
            'se_sabe'   => $seSabe,
            'respuesta' => $texto,
            'citas'     => $citas,
            'indice'    => [
                'commit'         => $commitIndice,
                'commit_actual'  => $commitActual,
                'desactualizado' => $desactualizado,
            ],
        ];
    }

    private function relativo(string $abs): string
    {
        return ltrim(str_replace(base_path(), '', $abs), DIRECTORY_SEPARATOR);
    }
}
