<?php

namespace App\Console\Commands\Schema;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Cierra la Fase 1 de la deriva #216 (items #797/#798 del roadmap): reconstruye
 * `{db}_dryrun` desde CERO corriendo TODAS las migraciones de database/migrations
 * (nunca migrations_old) y exporta el esquema resultante a storage/schema/reference.sql,
 * versionado en git para que los diffs de esquema entre commits sean visibles.
 *
 * El comando hermano de #797 (reset+migrate aislado) nunca llegó a existir — esa vuelta
 * timeouteó sin commits y quedó escalada a Irving. El spec de #798 autoriza explícitamente
 * duplicar aquí esa lógica en vez de esperar al comando hermano, así que este comando
 * hace las dos mitades de la Fase 1 en un solo paso: reset+migrate desde cero + dump.
 */
class BuildReferenceCommand extends Command
{
    protected $signature = 'schema:build-reference
                            {--force : Confirma la reconstrucción destructiva de la BD temporal *_dryrun}
                            {--keep : No borra la BD temporal al terminar (para depurar)}';

    protected $description = 'Reconstruye {db}_dryrun desde cero con TODAS las migraciones y exporta su esquema a storage/schema/reference.sql';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Bloqueado: este comando no corre en producción (candado duro).');
            return 1;
        }

        $cfg = config('database.connections.' . config('database.default'));
        if (($cfg['driver'] ?? null) !== 'mysql') {
            $this->error('Solo soportado para MySQL. Conexión por defecto: ' . ($cfg['driver'] ?? 'N/A'));
            return 1;
        }

        $dbName = $cfg['database'];
        $tempDb = $dbName . '_dryrun';

        // Triple candado: el nombre derivado SIEMPRE termina en _dryrun y nunca coincide
        // con la BD real, así que jamás se puede dropear por error algo que no sea la copia.
        if ($tempDb === $dbName || !str_ends_with($tempDb, '_dryrun')) {
            $this->error("Nombre de BD temporal inválido: `{$tempDb}`. Abortando por seguridad.");
            return 1;
        }

        if (!$this->option('force')) {
            $this->warn("Este comando BORRA y reconstruye `{$tempDb}` desde cero (TODAS las migraciones, no solo pendientes).");
            $this->warn("No toca `{$dbName}` ni ninguna BD de producción. Pasa --force para confirmar.");
            return 1;
        }

        $this->line("Reconstruyendo `{$tempDb}` desde cero…");
        $start = microtime(true);

        $charset   = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';
        if (!$this->mysql("DROP DATABASE IF EXISTS `{$tempDb}`; CREATE DATABASE `{$tempDb}` CHARACTER SET {$charset} COLLATE {$collation};", $cfg)) {
            $this->error("No se pudo (re)crear `{$tempDb}`. Verifica el grant: GRANT ALL PRIVILEGES ON `{$tempDb}`.* TO '{$cfg['username']}'@'<host>'.");
            return 1;
        }

        $originalDefault = config('database.default');
        config(['database.connections.schema_ref' => array_merge($cfg, ['database' => $tempDb])]);
        DB::purge('schema_ref');

        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $migrator->setOutput($this->output);

        $ran = [];
        try {
            $migrator->setConnection('schema_ref');

            // Repositorio recién creado (BD vacía) → getRan()=[] → TODAS las migraciones
            // cuentan como pendientes, no solo las que faltan aplicar sobre un esquema vivo.
            if (!$migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            $ran = $migrator->run([database_path('migrations')]);
        } catch (\Throwable $e) {
            $this->error('MIGRACIÓN FALLÓ reconstruyendo el esquema de referencia: ' . $e->getMessage());
            return 1;
        } finally {
            $migrator->setConnection($originalDefault);
            DB::setDefaultConnection($originalDefault);
            DB::purge('schema_ref');
        }

        $elapsed = round(microtime(true) - $start, 1);
        $this->info(count($ran) . " migración(es) corrida(s) desde cero en {$elapsed}s.");

        $outputPath = storage_path('schema/reference.sql');
        if (!$this->dumpSchema($cfg, $tempDb, $outputPath)) {
            $this->error('Falló la exportación del esquema con mysqldump.');
            return 1;
        }

        $this->info('Esquema exportado a ' . $outputPath);

        if (!$this->option('keep')) {
            $this->mysql("DROP DATABASE IF EXISTS `{$tempDb}`;", $cfg);
        }

        return 0;
    }

    /** Argumentos de conexión para el CLI de mysql/mysqldump (mismo patrón que deploy:dry-run-migrations). */
    private function connArgs(array $cfg): array
    {
        if (!empty($cfg['unix_socket'])) {
            $args = ['--socket=' . $cfg['unix_socket']];
        } else {
            $args = ['--host=' . ($cfg['host'] ?? '127.0.0.1')];
            if (!empty($cfg['port'])) {
                $args[] = '--port=' . $cfg['port'];
            }
        }
        $args[] = '--user=' . $cfg['username'];
        return $args;
    }

    private function mysql(string $sql, array $cfg): bool
    {
        $cmd = array_merge(['mysql'], $this->connArgs($cfg));
        $p = new Process($cmd, base_path(), $this->procEnv($cfg), $sql, 120);
        $p->run();
        if (!$p->isSuccessful()) {
            $this->error(trim($p->getErrorOutput()) ?: trim($p->getOutput()));
        }
        return $p->isSuccessful();
    }

    /**
     * --skip-comments quita el "Dump completed on <fecha>" y demás cabeceras variables,
     * así el diff en git solo cambia cuando el ESQUEMA cambia. --no-tablespaces evita un
     * error de mysqldump en MySQL 8 cuando el usuario no tiene el privilegio global PROCESS
     * (el grant de megaisp_dryrun es solo sobre esa BD).
     */
    private function dumpSchema(array $cfg, string $db, string $outputPath): bool
    {
        $cmd = array_merge(
            ['mysqldump'],
            $this->connArgs($cfg),
            ['--no-data', '--skip-comments', '--no-tablespaces', $db]
        );
        $p = new Process($cmd, base_path(), $this->procEnv($cfg), null, 300);
        $p->run();
        if (!$p->isSuccessful()) {
            $this->error(trim($p->getErrorOutput()) ?: trim($p->getOutput()));
            return false;
        }

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $p->getOutput());
        return true;
    }

    private function procEnv(array $cfg): array
    {
        return array_merge(getenv() ?: [], ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
    }
}
