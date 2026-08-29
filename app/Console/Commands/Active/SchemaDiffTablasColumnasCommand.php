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
 * Fase 2b (#800): agrega índices (information_schema.statistics) y llaves foráneas
 * (key_column_usage + referential_constraints), mismo criterio de ambos sentidos. La
 * comparación es por FIRMA ESTRUCTURAL (columnas en orden + tipo unique/fk + referencia +
 * ON DELETE/UPDATE), no por nombre — los nombres autogenerados de índice/constraint pueden
 * diferir entre entornos sin que la estructura real haya cambiado (decisión #739 q2). El
 * nombre mostrado en el diff es el que existe en el lado que SÍ tiene la estructura.
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
            $diff[] = ['tabla' => $tabla, 'columna' => null, 'lado_faltante' => $refDb, 'categoria' => 'tabla'];
        }
        foreach ($refTables->diff($liveTables)->sort()->values() as $tabla) {
            $diff[] = ['tabla' => $tabla, 'columna' => null, 'lado_faltante' => $liveDb, 'categoria' => 'tabla'];
        }

        $tiposDiferentes = 0;
        $indicesFaltantes = 0;
        $fksFaltantes = 0;
        foreach ($liveTables->intersect($refTables)->sort()->values() as $tabla) {
            $liveCols = $this->columns($liveDb, $tabla);
            $refCols  = $this->columns($refDb, $tabla);

            foreach ($liveCols->keys()->diff($refCols->keys())->sort()->values() as $columna) {
                $diff[] = ['tabla' => $tabla, 'columna' => $columna, 'lado_faltante' => $refDb, 'categoria' => 'columna'];
            }
            foreach ($refCols->keys()->diff($liveCols->keys())->sort()->values() as $columna) {
                $diff[] = ['tabla' => $tabla, 'columna' => $columna, 'lado_faltante' => $liveDb, 'categoria' => 'columna'];
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
                        'categoria' => 'columna',
                        'detalle' => [$liveDb => $a, $refDb => $b],
                    ];
                }
            }

            $liveIdx = $this->indexSignatures($liveDb, $tabla);
            $refIdx  = $this->indexSignatures($refDb, $tabla);
            foreach ($liveIdx->keys()->diff($refIdx->keys())->sort()->values() as $sig) {
                $diff[] = ['tabla' => $tabla, 'columna' => $liveIdx[$sig], 'lado_faltante' => $refDb, 'categoria' => 'indice'];
                $indicesFaltantes++;
            }
            foreach ($refIdx->keys()->diff($liveIdx->keys())->sort()->values() as $sig) {
                $diff[] = ['tabla' => $tabla, 'columna' => $refIdx[$sig], 'lado_faltante' => $liveDb, 'categoria' => 'indice'];
                $indicesFaltantes++;
            }

            $liveFk = $this->foreignKeySignatures($liveDb, $tabla);
            $refFk  = $this->foreignKeySignatures($refDb, $tabla);
            foreach ($liveFk->keys()->diff($refFk->keys())->sort()->values() as $sig) {
                $diff[] = ['tabla' => $tabla, 'columna' => $liveFk[$sig], 'lado_faltante' => $refDb, 'categoria' => 'fk'];
                $fksFaltantes++;
            }
            foreach ($refFk->keys()->diff($liveFk->keys())->sort()->values() as $sig) {
                $diff[] = ['tabla' => $tabla, 'columna' => $refFk[$sig], 'lado_faltante' => $liveDb, 'categoria' => 'fk'];
                $fksFaltantes++;
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

        $tablasFaltantesViva = collect($diff)->where('categoria', 'tabla')->where('lado_faltante', $refDb)->count();
        $tablasFaltantesRef  = collect($diff)->where('categoria', 'tabla')->where('lado_faltante', $liveDb)->count();
        $columnasFaltantesViva = collect($diff)->where('categoria', 'columna')->where('lado_faltante', $refDb)->count();
        $columnasFaltantesRef  = collect($diff)->where('categoria', 'columna')->where('lado_faltante', $liveDb)->count();

        $this->info("Diff guardado en {$this->option('out')} (" . count($diff) . ' entradas).');
        $this->line("  Tablas solo en {$liveDb} (faltan en {$refDb}): {$tablasFaltantesViva}");
        $this->line("  Tablas solo en {$refDb} (faltan en {$liveDb}): {$tablasFaltantesRef}");
        $this->line("  Columnas solo en {$liveDb}: {$columnasFaltantesViva}");
        $this->line("  Columnas solo en {$refDb}: {$columnasFaltantesRef}");
        $this->line("  Columnas con tipo/nullable/default diferente: {$tiposDiferentes}");
        $this->line("  Índices con diferencia estructural (solo en un lado): {$indicesFaltantes}");
        $this->line("  Llaves foráneas con diferencia estructural (solo en un lado): {$fksFaltantes}");

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

    /**
     * Firmas estructurales de los índices de una tabla (PRIMARY incluido), agrupando por
     * index_name y ordenando columnas por seq_in_index. La firma ignora el nombre del
     * índice a propósito (nombres autogenerados difieren entre entornos sin que la
     * estructura real cambie) — dos índices con la misma firma se consideran el mismo
     * índice aunque se llamen distinto.
     *
     * @return \Illuminate\Support\Collection<string, string> firma => nombre_de_indice (para mostrar en el diff)
     */
    private function indexSignatures(string $schema, string $table): \Illuminate\Support\Collection
    {
        $rows = collect(DB::select(
            'SELECT index_name AS nombre_indice, non_unique AS no_unico, column_name AS columna
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ?
             ORDER BY index_name, seq_in_index',
            [$schema, $table]
        ));

        return $rows->groupBy('nombre_indice')->map(function ($cols, $indexName) {
            $tipo = $indexName === 'PRIMARY' ? 'PRIMARY' : ((int) $cols->first()->no_unico === 0 ? 'UNIQUE' : 'INDEX');
            $columnas = $cols->pluck('columna')->implode(',');
            $firma = $tipo . '|' . $columnas;
            return [$firma => $indexName];
        })->collapse();
    }

    /**
     * Firmas estructurales de las llaves foráneas de una tabla, agrupando por
     * constraint_name y ordenando columnas por ordinal_position. Misma lógica que
     * indexSignatures(): la firma ignora el nombre del constraint.
     *
     * @return \Illuminate\Support\Collection<string, string> firma => nombre_de_constraint
     */
    private function foreignKeySignatures(string $schema, string $table): \Illuminate\Support\Collection
    {
        $rows = collect(DB::select(
            'SELECT kcu.constraint_name AS nombre_constraint, kcu.column_name AS columna,
                    kcu.referenced_table_name AS tabla_referenciada, kcu.referenced_column_name AS columna_referenciada,
                    rc.update_rule AS regla_update, rc.delete_rule AS regla_delete
             FROM information_schema.key_column_usage kcu
             JOIN information_schema.referential_constraints rc
               ON rc.constraint_schema = kcu.table_schema
              AND rc.constraint_name = kcu.constraint_name
              AND rc.table_name = kcu.table_name
             WHERE kcu.table_schema = ? AND kcu.table_name = ? AND kcu.referenced_table_name IS NOT NULL
             ORDER BY kcu.constraint_name, kcu.ordinal_position',
            [$schema, $table]
        ));

        return $rows->groupBy('nombre_constraint')->map(function ($cols, $constraintName) {
            $first = $cols->first();
            $columnas = $cols->pluck('columna')->implode(',');
            $refColumnas = $cols->pluck('columna_referenciada')->implode(',');
            $firma = $columnas . '|' . $first->tabla_referenciada . '|' . $refColumnas
                . '|' . $first->regla_update . '|' . $first->regla_delete;
            return [$firma => $constraintName];
        })->collapse();
    }
}
