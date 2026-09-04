<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Deriva #216 Fase 2c: reporte Markdown, legible y VERSIONADO, de la deriva de esquema
 * medida por Fase 2a (schema:diff-tablas-columnas). Solo lee el JSON crudo generado en
 * storage/app/circuito/diff-esquema-216.json y lo vuelca a docs/deriva-esquema/YYYY-MM-DD-diff.md
 * — no toca BD, no escribe fuera de docs/. Formato decidido por Irving (#739 q3, opción 1).
 *
 * Fase 2b (#800) extendió el JSON de entrada con categorías 'indice' y 'fk' (además de
 * 'tabla'/'columna' ya existentes); este comando ahora las agrupa en el resumen y en su
 * propia sección de detalle.
 */
class SchemaReporteDerivaMarkdownCommand extends Command
{
    protected $signature = 'schema:reporte-deriva-markdown
                            {--in=storage/app/circuito/diff-esquema-216.json : Ruta del JSON crudo (Fase 2a/2b)}
                            {--out= : Ruta del .md de salida (default: docs/deriva-esquema/YYYY-MM-DD-diff.md, fecha de hoy)}';

    protected $description = 'Genera el reporte Markdown legible de la deriva de esquema a partir del JSON crudo de Fase 2a/2b.';

    public function handle(): int
    {
        $in = base_path($this->option('in'));
        if (!is_file($in)) {
            $this->error("No existe el JSON de entrada en {$this->option('in')}. Corre primero `schema:diff-tablas-columnas`.");
            return 1;
        }

        $data = json_decode(file_get_contents($in), true);
        if (!is_array($data) || !isset($data['diff'])) {
            $this->error('El JSON de entrada no tiene el formato esperado (falta la llave "diff").');
            return 1;
        }

        $diff = collect($data['diff']);
        $bdViva = $data['bd_viva'] ?? '?';
        $bdRef = $data['bd_referencia'] ?? '?';
        $generadoAt = $data['generado_at'] ?? '?';

        $tablas = $diff->where('categoria', 'tabla');
        $columnas = $diff->where('categoria', 'columna')->where('lado_faltante', '!=', 'tipo_diferente');
        $tiposDiferentes = $diff->where('categoria', 'columna')->where('lado_faltante', 'tipo_diferente');
        $indices = $diff->where('categoria', 'indice');
        $fks = $diff->where('categoria', 'fk');

        $tablasFaltanEnRef = $tablas->where('lado_faltante', $bdRef)->pluck('tabla')->sort()->values();
        $tablasFaltanEnViva = $tablas->where('lado_faltante', $bdViva)->pluck('tabla')->sort()->values();
        $columnasFaltanEnRef = $columnas->where('lado_faltante', $bdRef)->sortBy(['tabla', 'columna'])->values();
        $columnasFaltanEnViva = $columnas->where('lado_faltante', $bdViva)->sortBy(['tabla', 'columna'])->values();
        $tiposDiferentesOrdenado = $tiposDiferentes->sortBy(['tabla', 'columna'])->values();
        $indicesFaltanEnRef = $indices->where('lado_faltante', $bdRef)->sortBy(['tabla', 'columna'])->values();
        $indicesFaltanEnViva = $indices->where('lado_faltante', $bdViva)->sortBy(['tabla', 'columna'])->values();
        $fksFaltanEnRef = $fks->where('lado_faltante', $bdRef)->sortBy(['tabla', 'columna'])->values();
        $fksFaltanEnViva = $fks->where('lado_faltante', $bdViva)->sortBy(['tabla', 'columna'])->values();

        $fecha = now()->toDateString();
        $outRelative = $this->option('out') ?: "docs/deriva-esquema/{$fecha}-diff.md";
        $out = base_path($outRelative);

        $md = $this->render(
            $fecha,
            $generadoAt,
            $bdViva,
            $bdRef,
            $diff->count(),
            $tablasFaltanEnRef,
            $tablasFaltanEnViva,
            $columnasFaltanEnRef,
            $columnasFaltanEnViva,
            $tiposDiferentesOrdenado,
            $indicesFaltanEnRef,
            $indicesFaltanEnViva,
            $fksFaltanEnRef,
            $fksFaltanEnViva
        );

        @mkdir(dirname($out), 0755, true);
        file_put_contents($out, $md);

        $this->info("Reporte guardado en {$outRelative}.");
        return 0;
    }

    /**
     * @param Collection<int, string> $tablasFaltanEnRef
     * @param Collection<int, string> $tablasFaltanEnViva
     * @param Collection<int, array> $columnasFaltanEnRef
     * @param Collection<int, array> $columnasFaltanEnViva
     * @param Collection<int, array> $tiposDiferentes
     * @param Collection<int, array> $indicesFaltanEnRef
     * @param Collection<int, array> $indicesFaltanEnViva
     * @param Collection<int, array> $fksFaltanEnRef
     * @param Collection<int, array> $fksFaltanEnViva
     */
    private function render(
        string $fecha,
        string $generadoAt,
        string $bdViva,
        string $bdRef,
        int $totalDiff,
        Collection $tablasFaltanEnRef,
        Collection $tablasFaltanEnViva,
        Collection $columnasFaltanEnRef,
        Collection $columnasFaltanEnViva,
        Collection $tiposDiferentes,
        Collection $indicesFaltanEnRef,
        Collection $indicesFaltanEnViva,
        Collection $fksFaltanEnRef,
        Collection $fksFaltanEnViva
    ): string {
        $lines = [];
        $lines[] = "# Deriva de esquema — {$fecha}";
        $lines[] = '';
        $lines[] = "Generado: `{$generadoAt}` · BD viva: `{$bdViva}` · BD de referencia: `{$bdRef}`";
        $lines[] = '';
        $lines[] = 'Insumo: `storage/app/circuito/diff-esquema-216.json` (Fase 2a+2b, comando `schema:diff-tablas-columnas`). '
            . 'Índices y llaves foráneas se comparan por firma estructural (columnas + tipo/referencia), no por nombre '
            . 'autogenerado — el nombre mostrado es el que existe en el lado que sí tiene la estructura.';
        $lines[] = '';
        $lines[] = '## Resumen';
        $lines[] = '';
        $lines[] = "| Categoría | Falta en `{$bdViva}` | Falta en `{$bdRef}` | Total |";
        $lines[] = '|---|---|---|---|';
        $lines[] = "| Tablas | {$tablasFaltanEnViva->count()} | {$tablasFaltanEnRef->count()} | " . ($tablasFaltanEnViva->count() + $tablasFaltanEnRef->count()) . ' |';
        $lines[] = "| Columnas | {$columnasFaltanEnViva->count()} | {$columnasFaltanEnRef->count()} | " . ($columnasFaltanEnViva->count() + $columnasFaltanEnRef->count()) . ' |';
        $lines[] = "| Columnas con tipo/nullable/default diferente | — | — | {$tiposDiferentes->count()} |";
        $lines[] = "| Índices | {$indicesFaltanEnViva->count()} | {$indicesFaltanEnRef->count()} | " . ($indicesFaltanEnViva->count() + $indicesFaltanEnRef->count()) . ' |';
        $lines[] = "| Llaves foráneas | {$fksFaltanEnViva->count()} | {$fksFaltanEnRef->count()} | " . ($fksFaltanEnViva->count() + $fksFaltanEnRef->count()) . ' |';
        $lines[] = '';
        $lines[] = "**Total de diferencias en este corte:** {$totalDiff}";
        $lines[] = '';
        $lines[] = '## Detalle';
        $lines[] = '';

        $lines[] = "### Tablas que faltan en `{$bdRef}` ({$tablasFaltanEnRef->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($tablasFaltanEnRef, fn ($t) => "- `{$t}`"));
        $lines[] = '';

        $lines[] = "### Tablas que faltan en `{$bdViva}` ({$tablasFaltanEnViva->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($tablasFaltanEnViva, fn ($t) => "- `{$t}`"));
        $lines[] = '';

        $lines[] = "### Columnas que faltan en `{$bdRef}` ({$columnasFaltanEnRef->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($columnasFaltanEnRef, fn ($d) => "- `{$d['tabla']}`.`{$d['columna']}`"));
        $lines[] = '';

        $lines[] = "### Columnas que faltan en `{$bdViva}` ({$columnasFaltanEnViva->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($columnasFaltanEnViva, fn ($d) => "- `{$d['tabla']}`.`{$d['columna']}`"));
        $lines[] = '';

        $lines[] = "### Columnas con tipo/nullable/default diferente ({$tiposDiferentes->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($tiposDiferentes, function ($d) use ($bdViva, $bdRef) {
            $detalle = $d['detalle'] ?? [];
            $vivaVal = $detalle[$bdViva] ?? '?';
            $refVal = $detalle[$bdRef] ?? '?';
            return "- `{$d['tabla']}`.`{$d['columna']}`: `{$bdViva}`=`{$vivaVal}` vs `{$bdRef}`=`{$refVal}`";
        }));
        $lines[] = '';

        $lines[] = "### Índices que faltan en `{$bdRef}` ({$indicesFaltanEnRef->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($indicesFaltanEnRef, fn ($d) => "- `{$d['tabla']}`: `{$d['columna']}`"));
        $lines[] = '';

        $lines[] = "### Índices que faltan en `{$bdViva}` ({$indicesFaltanEnViva->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($indicesFaltanEnViva, fn ($d) => "- `{$d['tabla']}`: `{$d['columna']}`"));
        $lines[] = '';

        $lines[] = "### Llaves foráneas que faltan en `{$bdRef}` ({$fksFaltanEnRef->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($fksFaltanEnRef, fn ($d) => "- `{$d['tabla']}`: `{$d['columna']}`"));
        $lines[] = '';

        $lines[] = "### Llaves foráneas que faltan en `{$bdViva}` ({$fksFaltanEnViva->count()})";
        $lines[] = '';
        $lines = array_merge($lines, $this->listaOEmpty($fksFaltanEnViva, fn ($d) => "- `{$d['tabla']}`: `{$d['columna']}`"));
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param Collection<int, mixed> $items */
    private function listaOEmpty(Collection $items, callable $render): array
    {
        if ($items->isEmpty()) {
            return ['_Ninguna._'];
        }

        return $items->map($render)->all();
    }
}
