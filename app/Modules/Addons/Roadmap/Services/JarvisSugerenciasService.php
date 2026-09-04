<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

/**
 * ITEM #805 (Jarvis Parte 3a, sub-item de #713) — motor de detección de sugerencias, SOLO
 * LECTURA. Reusa JarvisIndiceService::commitActual() (#711) para fechar la corrida; NO
 * construye un segundo índice ni toca AuditorService (#712, Parte 2) — ese es el ÚNICO que
 * puede crear items en la Hoja de Ruta (auditor_fingerprint). Esta clase NUNCA escribe en
 * roadmap_items ni en ningún otro lado: `detectar()` solo lee y devuelve un array.
 *
 * Misma regla anti-alucinación que #711/#712: cada candidato trae su cita verificable
 * (item# real, o archivo+línea existente con medición de antigüedad). Sin cita no se
 * propone — un detector que no encuentra evidencia citable devuelve [] en vez de rellenar.
 *
 * Tope de ruido: máximo TOPE_CANDIDATOS por corrida (Opción B recomendada por el Des-trabe
 * de #713 — "0-3 hallazgos", ver comentarios_claude del item #713).
 */
class JarvisSugerenciasService
{
    private const TOPE_CANDIDATOS = 3;

    /** Mínimo de items para considerar que un módulo "repite" un patrón (no solo 1-2 casos sueltos). */
    private const UMBRAL_PATRON = 3;

    public function __construct(private JarvisIndiceService $indexador)
    {
    }

    public function detectar(): array
    {
        $patrones = $this->detectarPatronesRepetidos();
        $deuda    = $this->detectarDeudaQueDuele();
        $stubs    = $this->detectarCapacidadesCasiExistentes();

        // Diversidad primero: una candidata por categoría (si existe), y solo después se
        // rellenan los huecos con lo que sobre de cada detector — así 3 candidatos casi
        // siempre tocan las 3 categorías del item, en vez de que una sola las agote.
        $candidatos = [];
        foreach ([$patrones, $deuda, $stubs] as $lista) {
            if ($lista !== [] && count($candidatos) < self::TOPE_CANDIDATOS) {
                $candidatos[] = $lista[0];
            }
        }
        foreach ([$patrones, $deuda, $stubs] as $lista) {
            foreach (array_slice($lista, 1) as $c) {
                if (count($candidatos) >= self::TOPE_CANDIDATOS) {
                    break 2;
                }
                $candidatos[] = $c;
            }
        }

        return [
            'generado_at' => now()->toIso8601String(),
            'commit'      => $this->indexador->commitActual(),
            'candidatos'  => $candidatos,
        ];
    }

    // ── Detector 1: patrones que se repiten ────────────────────────────────────────────────
    // Módulos con varios items que YA se re-encolaron por timeout (reap_count>=1) — la firma
    // medible, en la propia Hoja de Ruta, de un bucle/paraguas atascado repitiéndose ahí
    // (mismo síntoma documentado a mano varias veces en CLAUDE.md: #738/#745/#713/#778).

    private function detectarPatronesRepetidos(): array
    {
        $items = RoadmapItem::query()
            ->where('reap_count', '>=', 1)
            ->whereNotNull('modulo')
            ->get(['id', 'modulo', 'reap_count']);

        $grupos = $items->groupBy('modulo')->filter(fn ($g) => $g->count() >= self::UMBRAL_PATRON);
        $grupos = $grupos->sortByDesc(fn ($g) => $g->count());

        $out = [];
        foreach ($grupos as $modulo => $grupo) {
            $n   = $grupo->count();
            $ids = $grupo->sortByDesc('reap_count')->pluck('id')->take(5)->all();

            $out[] = [
                'categoria' => 'patron_repetido',
                'texto'     => "El módulo «{$modulo}» tiene {$n} item(s) de la Hoja de Ruta que ya se "
                    . 're-encolaron por timeout al menos una vez (reap_count>=1) — posible bucle o '
                    . 'paraguas atascado repitiéndose en ese módulo.',
                'citas'     => array_map(static fn ($id) => [
                    'tipo'      => 'item_roadmap',
                    'id'        => $id,
                    'tabla'     => 'roadmap_items',
                    'columna'   => 'reap_count',
                    'medido_at' => now()->toIso8601String(),
                ], $ids),
            ];
        }

        return $out;
    }

    // ── Detector 2: deuda que empieza a doler ──────────────────────────────────────────────
    // Marcas ⚠️ Pendiente / 📝 Nota en CLAUDE.md, con antigüedad MEDIDA vía git blame (nunca
    // inventada) — sin fecha real medible, esa línea no entra al ranking.

    private function detectarDeudaQueDuele(): array
    {
        $archivo = base_path('CLAUDE.md');
        if (! is_file($archivo)) {
            return [];
        }

        $lineas    = file($archivo) ?: [];
        $hallazgos = [];
        foreach ($lineas as $i => $linea) {
            if (! preg_match('/⚠️\s*Pendiente|📝\s*Nota/u', $linea)) {
                continue;
            }

            $numLinea = $i + 1;
            $fecha    = $this->fechaBlame('CLAUDE.md', $numLinea);
            if ($fecha === null) {
                continue; // sin fecha medible → no se propone (anti-alucinación)
            }

            $hallazgos[] = [
                'linea'       => $numLinea,
                'dias'        => now()->diffInDays($fecha),
                'texto_linea' => trim($linea),
            ];
        }

        usort($hallazgos, static fn ($a, $b) => $b['dias'] <=> $a['dias']);

        $out = [];
        foreach (array_slice($hallazgos, 0, 2) as $h) {
            $resumen = $this->resumirCeldaMarkdown($h['texto_linea']);
            $out[]   = [
                'categoria' => 'deuda_que_duele',
                'texto'     => "CLAUDE.md:{$h['linea']} lleva {$h['dias']} día(s) sin resolverse: {$resumen}",
                'citas'     => [[
                    'tipo'    => 'archivo_linea',
                    'archivo' => 'CLAUDE.md',
                    'linea'   => $h['linea'],
                    'nota'    => "medido por git blame: {$h['dias']} día(s) de antigüedad",
                ]],
            ];
        }

        return $out;
    }

    /** Fecha del commit que introdujo esa línea (git blame, no inventada). */
    private function fechaBlame(string $archivoRel, int $linea): ?Carbon
    {
        $p = new Process(['git', 'blame', '-L', "{$linea},{$linea}", '--line-porcelain', $archivoRel], base_path());
        $p->run();
        if (! $p->isSuccessful()) {
            return null;
        }

        foreach (explode("\n", $p->getOutput()) as $l) {
            if (preg_match('/^author-time (\d+)/', $l, $m)) {
                return Carbon::createFromTimestamp((int) $m[1]);
            }
        }

        return null;
    }

    /** Extrae el nombre corto de la celda markdown (primer **negrita**, o 2ª columna de la tabla). */
    private function resumirCeldaMarkdown(string $linea): string
    {
        if (preg_match('/\*\*([^*]+)\*\*/u', $linea, $m)) {
            return $m[1];
        }

        $celdas = array_map('trim', explode('|', $linea));

        return $celdas[1] ?? $linea;
    }

    // ── Detector 3: capacidades casi existentes ────────────────────────────────────────────
    // Marcadores TODO/FIXME/HACK reales DENTRO DE COMENTARIO en servicios/comandos (mismo
    // regex "comentario-only" ya probado en AuditorService::detTodos, reescrito aquí de forma
    // independiente porque esa clase es Parte 2 (#712) y no se toca — evita "TODO el
    // historial" en español, que no es un marcador de deuda).

    private function detectarCapacidadesCasiExistentes(): array
    {
        $dirs = array_filter(array_merge([
            base_path('app/Services'),
            base_path('app/Console'),
        ], glob(base_path('app/Modules/*/*/Services'), GLOB_ONLYDIR) ?: [],
            glob(base_path('app/Modules/*/*/Console'), GLOB_ONLYDIR) ?: []), 'is_dir');

        $hallazgos = [];
        foreach ($dirs as $dir) {
            foreach ($this->archivosPhp($dir) as $file) {
                $lineas = @file($file);
                if (! $lineas) {
                    continue;
                }
                foreach ($lineas as $i => $linea) {
                    if (! preg_match('#(?://|/\*+|\#)\s*(TODO|FIXME|HACK)\b[:\s]*(.*)$#u', $linea, $m)) {
                        continue;
                    }
                    $hallazgos[] = [
                        'archivo' => $this->relativo($file),
                        'linea'   => $i + 1,
                        'marca'   => $m[1],
                        'texto'   => trim($m[2]) !== '' ? trim($m[2]) : '(sin detalle en el comentario)',
                    ];
                }
            }
        }

        $out = [];
        foreach (array_slice($hallazgos, 0, 2) as $h) {
            $out[] = [
                'categoria' => 'capacidad_casi_existente',
                'texto'     => "{$h['archivo']}:{$h['linea']} tiene un marcador [{$h['marca']}] sin cerrar: {$h['texto']}",
                'citas'     => [['tipo' => 'archivo_linea', 'archivo' => $h['archivo'], 'linea' => $h['linea']]],
            ];
        }

        return $out;
    }

    private function archivosPhp(string $dir): array
    {
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        $out = [];
        foreach ($it as $f) {
            if ($f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    }

    private function relativo(string $abs): string
    {
        return ltrim(str_replace(base_path(), '', $abs), DIRECTORY_SEPARATOR);
    }
}
