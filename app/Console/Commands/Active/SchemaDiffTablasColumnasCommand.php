<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deriva #216 Fase 2a (MVP): diff de SOLO LECTURA entre la BD viva (megaisp) y una BD de
 * referencia (por default `{db}_dryrun`, poblada por el trabajo de #797/#817/#818),
 * introspeccionando information_schema.tables/columns de ambas — nunca escribe en ninguna.
 *
 * Compara EN AMBOS SENTIDOS: tabla/columna que existe en la BD viva pero no en la
 * referencia (posible tabla creada a mano, fuera de migraciones) y viceversa (migración
 * que la referencia sí corrió pero que en la BD viva nunca se aplicó/se borró a mano).
 * Para columnas presentes en ambos lados también compara tipo/nullable/default.
 *
 * Índices y FKs quedan fuera (sub-item hermano 2b).
 */
class SchemaDiffTablasColumnasCommand extends Command
{
    protected $signature = 'schema:diff-tablas-columnas
                            {--reference-db= : Nombre de la BD de referencia (default: {db}_dryrun)}
                            {--out=storage/app/circuito/diff-esquema-216.json : Ruta de salida del JSON}';

    protected $description = 'Diff de solo lectura (tablas+columnas) entre la BD viva y la BD de referencia de migraciones.';

    public function handle(): int
    {
        $cfg = config('database.connections.' . config('database.default'));
        if (($cfg['driver'] ?? null) !== 'mysql') {
            $this->error('Solo soportado para MySQL. Conexión por defecto: ' . ($cfg['driver'] ?? 'N/A'));
            return 1;
        }

        $liveDb = $cfg['database'];
        $refDb  = trim((string) $this->option('reference-db')) ?: ($liveDb . '_dryrun');

        if (!$this->schemaExists($refDb)) {
            $this->error("La BD de referencia `{$refDb}` no existe. Este comando depende del trabajo de #797/#817/#818 (poblarla) o de #798 (storage/schema/reference.sql, aún sin lector). Nada que comparar.");
            return 1;
        }

        $this->line("Diff de esquema: `{$liveDb}` (viva) vs `{$refDb}` (referencia)");

        $liveTables = $this->tables($liveDb);
        $refTables  = $this->tables($refDb);

        $diff = [];

        foreach ($liveTables->diff($refTables)->sort()->values() as $tabla) {
            $diff[] = ['tabla' => $tabla, 'columna' => null, 'lado_faltante' => $refDb];
        }
        foreach ($refTables->diff($liveTables)->sort()->values() as $tabla) {
            $diff[] = ['tabla' => $tabla, 'columna' => null, 'lado_faltante' => $liveDb];
        }

        $tiposDiferentes = 0;
        foreach ($liveTables->intersect($refTables)->sort()->values() as $tabla) {
            $liveCols = $this->columns($liveDb, $tabla);
            $refCols  = $this->columns($refDb, $tabla);

            foreach ($liveCols->keys()->diff($refCols->keys())->sort()->values() as $columna) {
                $diff[] = ['tabla' => $tabla, 'columna' => $columna, 'lado_faltante' => $refDb];
            }
            foreach ($refCols->keys()->diff($liveCols->keys())->sort()->values() as $columna) {
                $diff[] = ['tabla' => $tabla, 'columna' => $columna, 'lado_faltante' => $liveDb];
            }

            foreach ($liveCols->keys()->intersect($refCols->keys())->sort()->values() as $columna) {
                $a = $liveCols[$columna];
                $b = $refCols[$columna];
                if ($a !== $b) {
                    $tiposDiferentes++;
                    $diff[] = [
                        'tabla' => $tabla,
                        'columna' => $columna,
                        'lado_faltante' => 'tipo_diferente',
                        'detalle' => [$liveDb => $a, $refDb => $b],
                    ];
                }
            }
        }

        $out = base_path($this->option('out'));
        @mkdir(dirname($out), 0755, true);
        file_put_contents($out, json_encode([
            'generado_at' => now()->toIso8601String(),
            'bd_viva' => $liveDb,
            'bd_referencia' => $refDb,
            'diff' => $diff,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $tablasFaltantesViva = collect($diff)->where('columna', null)->where('lado_faltante', $refDb)->count();
        $tablasFaltantesRef  = collect($diff)->where('columna', null)->where('lado_faltante', $liveDb)->count();
        $columnasFaltantesViva = collect($diff)->whereNotNull('columna')->where('lado_faltante', $refDb)->count();
        $columnasFaltantesRef  = collect($diff)->whereNotNull('columna')->where('lado_faltante', $liveDb)->count();

        $this->info("Diff guardado en {$this->option('out')} (" . count($diff) . ' entradas).');
        $this->line("  Tablas solo en {$liveDb} (faltan en {$refDb}): {$tablasFaltantesViva}");
        $this->line("  Tablas solo en {$refDb} (faltan en {$liveDb}): {$tablasFaltantesRef}");
        $this->line("  Columnas solo en {$liveDb}: {$columnasFaltantesViva}");
        $this->line("  Columnas solo en {$refDb}: {$columnasFaltantesRef}");
        $this->line("  Columnas con tipo/nullable/default diferente: {$tiposDiferentes}");

        return 0;
    }

    private function schemaExists(string $schema): bool
    {
        $row = DB::select('SELECT COUNT(*) AS c FROM information_schema.schemata WHERE schema_name = ?', [$schema]);
        return (int) $row[0]->c > 0;
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function tables(string $schema): \Illuminate\Support\Collection
    {
        return collect(DB::select(
            'SELECT table_name AS nombre FROM information_schema.tables WHERE table_schema = ?',
            [$schema]
        ))->pluck('nombre');
    }

    /** @return \Illuminate\Support\Collection<string, string> columna => "tipo|nullable|default" */
    private function columns(string $schema, string $table): \Illuminate\Support\Collection
    {
        return collect(DB::select(
            'SELECT column_name AS nombre, column_type AS tipo, is_nullable AS nulo, column_default AS por_defecto
             FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
            [$schema, $table]
        ))->mapWithKeys(fn ($c) => [
            $c->nombre => sprintf('%s|%s|%s', $c->tipo, $c->nulo, $c->por_defecto ?? '<NULL>'),
        ]);
    }
}
