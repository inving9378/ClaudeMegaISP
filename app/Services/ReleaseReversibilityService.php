<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseReversibilityThreshold;
use App\Models\ReleaseSnapshot;
use App\Services\Deploy\ReleaseTechnicalLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #1020 (sub-item 4/5 de #1012) — ventana de reversibilidad medida en FILAS
 * nuevas, no en horas. Dos responsabilidades:
 *  1) registrarSnapshot(): al momento en que una versión queda aplicada (git_tag/save_release),
 *     guarda MAX(id) por cada tabla que sus migraciones tocaron — línea base para medir después
 *     cuánto creció cada una (decisión de Irving, q3: snapshot barato, no trigger ni polling).
 *  2) estadoPara(): a la hora de mostrar la tarjeta en la Torre, compara el MAX(id) actual
 *     contra el snapshot y decide REGRESO LIMPIO / REGRESO CON PÉRDIDA / NO REVERSIBLE.
 */
class ReleaseReversibilityService
{
    public function __construct(private ReleaseTechnicalLinkService $vinculo)
    {
    }

    /**
     * Tablas que las migraciones de esta versión (migracion_desde..migracion_hasta) crearon o
     * alteraron, leídas de `Schema::create(`/`Schema::table(` dentro de up() (down() se ignora
     * a propósito — mismo corte que el guard #1018/#1020, es sobre lo que la versión EXPANDIÓ).
     *
     * @return array<int, string>
     */
    public function tablasAfectadas(Release $release): array
    {
        if (!$release->migracion_desde || !$release->migracion_hasta) {
            return [];
        }

        $tablas = [];
        foreach ($this->vinculo->migracionesEnRango($release->migracion_desde, $release->migracion_hasta) as $nombre) {
            $path = database_path("migrations/{$nombre}.php");
            if (!is_file($path)) {
                continue;
            }

            $contenido = (string) file_get_contents($path);
            $finDeUp = null;
            if (preg_match('/function\s+down\s*\(/', $contenido, $m, PREG_OFFSET_CAPTURE)) {
                $finDeUp = $m[0][1];
            }
            $seccionUp = $finDeUp !== null ? substr($contenido, 0, $finDeUp) : $contenido;

            if (preg_match_all('/Schema::(?:create|table)\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $seccionUp, $matches)) {
                foreach ($matches[1] as $tabla) {
                    $tablas[$tabla] = true;
                }
            }
        }

        return array_keys($tablas);
    }

    /** Clasificación por patrón de nombre — ver config/release_reversibility.php. */
    public function criticidadDeTabla(string $tabla): string
    {
        $patrones = config('release_reversibility', []);

        foreach ($patrones['critica'] ?? [] as $patron) {
            if (str_contains($tabla, $patron)) {
                return 'critica';
            }
        }
        foreach ($patrones['baja'] ?? [] as $patron) {
            if (str_contains($tabla, $patron)) {
                return 'baja';
            }
        }

        return 'media';
    }

    /**
     * Guarda (o actualiza) el snapshot MAX(id) por tabla afectada. Best-effort: se llama desde
     * los pasos de deploy (git_tag / save_release), un fallo aquí NUNCA debe tumbar el pipeline.
     */
    public function registrarSnapshot(Release $release): void
    {
        try {
            foreach ($this->tablasAfectadas($release) as $tabla) {
                if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, 'id')) {
                    continue;
                }

                ReleaseSnapshot::updateOrCreate(
                    ['release_id' => $release->id, 'tabla' => $tabla],
                    [
                        'criticidad'         => $this->criticidadDeTabla($tabla),
                        'max_id_al_snapshot' => DB::table($tabla)->max('id'),
                        'created_at'         => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning("ReleaseReversibilityService::registrarSnapshot ({$release->version}) falló: " . $e->getMessage());
        }
    }

    /**
     * Estado de reversibilidad para mostrar en la tarjeta de la Torre, SIN abrirla:
     *  - no_reversible → releases.reversible === false (destructiva sin down(), o contracción
     *    ya aplicada — resultado de los sub-items 2/3 de #1012).
     *  - sin_datos → no hay snapshot para medir (release previa a este mecanismo, o sin tablas
     *    afectadas registradas todavía).
     *  - con_perdida → alguna tabla superó su umbral por criticidad (config q1); trae el peor
     *    caso para mostrar el conteo N sin tener que abrir la tarjeta.
     *  - limpio → 0 tablas por encima de su umbral.
     *
     * @return array{estado:string, motivo:?string, detalle:array, peor:?array}
     */
    public function estadoPara(Release $release): array
    {
        if ($release->reversible === false) {
            return [
                'estado'  => 'no_reversible',
                'motivo'  => $release->reversible_motivo,
                'detalle' => [],
                'peor'    => null,
            ];
        }

        $snapshots = ReleaseSnapshot::where('release_id', $release->id)->get();
        if ($snapshots->isEmpty()) {
            return [
                'estado'  => 'sin_datos',
                'motivo'  => null,
                'detalle' => [],
                'peor'    => null,
            ];
        }

        $umbrales = ReleaseReversibilityThreshold::pluck('umbral_filas', 'criticidad');
        $detalle  = [];
        $peor     = null;

        foreach ($snapshots as $snap) {
            if ($snap->max_id_al_snapshot === null || !Schema::hasTable($snap->tabla)) {
                continue;
            }

            $maxActual   = (int) DB::table($snap->tabla)->max('id');
            $filasNuevas = max(0, $maxActual - (int) $snap->max_id_al_snapshot);
            $umbral      = (int) ($umbrales[$snap->criticidad] ?? 500);

            $fila = [
                'tabla'        => $snap->tabla,
                'criticidad'   => $snap->criticidad,
                'filas_nuevas' => $filasNuevas,
                'umbral'       => $umbral,
            ];
            $detalle[] = $fila;

            if ($filasNuevas > $umbral && ($peor === null || $filasNuevas > $peor['filas_nuevas'])) {
                $peor = $fila;
            }
        }

        if ($peor !== null) {
            return [
                'estado'  => 'con_perdida',
                'motivo'  => null,
                'detalle' => $detalle,
                'peor'    => $peor,
            ];
        }

        return [
            'estado'  => 'limpio',
            'motivo'  => null,
            'detalle' => $detalle,
            'peor'    => null,
        ];
    }
}
