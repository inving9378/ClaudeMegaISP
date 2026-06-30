<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Dry-run de migraciones: corre las migraciones PENDIENTES contra una copia
 * desechable de la BD real, para detectar fallos ANTES de tocar la BD productiva
 * (paso `migrate_dryrun` del deploy remoto, crítico → aborta el deploy si falla).
 *
 * Filosofía (ver charla de diseño): lo lento de clonar la BD son los DATOS, no las
 * migraciones. Por eso por defecto se clona SOLO el esquema (mysqldump --no-data,
 * segundos) + la tabla `migrations` (para que Laravel sepa qué ya se aplicó y corra
 * únicamente las pendientes). Eso caza la clase de fallos estructurales (columna ya
 * existe, FK mal definida, índice duplicado, sintaxis). Para los fallos dependientes
 * de datos (unique contra duplicados, data-too-long al achicar, FK contra filas reales)
 * se copian SOLO las tablas indicadas en --with-data (rápido: las tablas gigantes suelen
 * ser logs, no las que reciben índices).
 *
 * PRE-REQUISITO en el server: el usuario MySQL de la app necesita privilegio para
 * CREATE/DROP de la BD `{database}_dryrun`. Probar a mano una vez antes de confiar en
 * él como paso crítico:  php artisan deploy:dry-run-migrations
 */
class DryRunMigrationsCommand extends Command
{
    protected $signature = 'deploy:dry-run-migrations
                            {--with-data= : Tablas (csv) cuyos datos copiar para validar fallos dependientes de datos}
                            {--keep : No borrar la BD temporal al terminar (para depurar)}';

    protected $description = 'Corre las migraciones pendientes contra una copia desechable de la BD real para detectar fallos antes de migrar en producción.';

    public function handle(): int
    {
        $cfg    = config('database.connections.' . config('database.default'));
        if (($cfg['driver'] ?? null) !== 'mysql') {
            $this->error('Solo soportado para MySQL. Conexión por defecto: ' . ($cfg['driver'] ?? 'N/A'));
            return 1;
        }

        $dbName = $cfg['database'];
        $tempDb = $dbName . '_dryrun';
        $this->line("Dry-run de migraciones → BD temporal `{$tempDb}`");

        // 1. (Re)crear la BD temporal limpia. Un fallo aquí es de SETUP/infra (típicamente
        //    falta el grant CREATE DATABASE) → NO debe bloquear el deploy: se omite con un
        //    warning ruidoso y se cae al migrate real. Solo un fallo REAL de migración aborta.
        $charset   = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';
        if (!$this->mysql("DROP DATABASE IF EXISTS `{$tempDb}`; CREATE DATABASE `{$tempDb}` CHARACTER SET {$charset} COLLATE {$collation};", $cfg)) {
            return $this->skip("No se pudo crear la BD temporal `{$tempDb}`. Falta el grant: "
                . "GRANT ALL PRIVILEGES ON `{$tempDb}`.* TO '{$cfg['username']}'@'<host>'. "
                . "El dry-run se omite (no bloquea); corre el grant para activarlo.");
        }

        $originalDefault = config('database.default');

        try {
            // 2. Clonar SOLO el esquema (sin datos) — rápido. Fallo = setup → skip, no aborta.
            if (!$this->cloneSchema($cfg, $dbName, $tempDb)) {
                return $this->skip('Falló el clonado de esquema (mysqldump | mysql).');
            }

            // 3. Copiar SIEMPRE `migrations` (para correr solo las pendientes) + las tablas --with-data.
            $tables = ['migrations'];
            if ($withData = trim((string) $this->option('with-data'))) {
                foreach (explode(',', $withData) as $t) {
                    if (($t = trim($t)) !== '') {
                        $tables[] = $t;
                    }
                }
            }
            foreach (array_values(array_unique($tables)) as $t) {
                if (!$this->copyTableData($cfg, $dbName, $tempDb, $t)) {
                    return $this->skip("No se pudieron copiar datos de la tabla `{$t}` (¿nombre mal escrito en --with-data?).");
                }
            }

            // 4. Correr las pendientes vía el Migrator (NO el comando migrate: el prompt de
            //    confirmación de producción vive en el comando, no en el Migrator → no se cuelga
            //    en el contexto nohup del deploy). setConnection cambia la conexión por defecto
            //    GLOBAL del proceso → se restaura SIEMPRE en finally (el resto del deploy corre
            //    en este mismo proceso y debe seguir hablando con la BD real).
            config(['database.connections.dryrun' => array_merge($cfg, ['database' => $tempDb])]);
            DB::purge('dryrun');

            /** @var \Illuminate\Database\Migrations\Migrator $migrator */
            $migrator = app('migrator');
            $migrator->setOutput($this->output);
            $paths = array_merge($migrator->paths(), [database_path('migrations')]);

            $ran = [];
            try {
                $migrator->setConnection('dryrun');
                $ran = $migrator->run($paths);
            } catch (\Throwable $e) {
                $this->error('MIGRACIÓN FALLÓ en dry-run: ' . $e->getMessage());
                return 1;
            } finally {
                $migrator->setConnection($originalDefault);
                DB::setDefaultConnection($originalDefault);
                DB::purge('dryrun');
            }

            if (empty($ran)) {
                $this->info('Sin migraciones pendientes — nada que validar. OK.');
            } else {
                $this->info('Dry-run OK: ' . count($ran) . ' migración(es) pendiente(s) corrieron sin error contra la copia.');
            }

            return 0;
        } finally {
            // 5. Tirar la BD temporal (salvo --keep).
            if (!$this->option('keep')) {
                $this->mysql("DROP DATABASE IF EXISTS `{$tempDb}`;", $cfg);
            }
        }
    }

    /**
     * Setup del sandbox falló (no es un fallo de migración) → warning ruidoso y exit 0
     * para NO bloquear el deploy. El mensaje queda en el output del paso del deploy.
     */
    private function skip(string $reason): int
    {
        $this->warn('⚠️  DRY-RUN OMITIDO (no bloquea el deploy): ' . $reason);
        return 0;
    }

    /**
     * Argumentos de conexión para el CLI de mysql/mysqldump. Prefiere socket (como
     * backup_db:process). La contraseña va por MYSQL_PWD (env del proceso), nunca en
     * la línea de comando.
     */
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

    /** Ejecuta SQL arbitrario por el CLI de mysql. */
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

    /** Clona el esquema (sin datos) de $from a $to vía pipe mysqldump | mysql. */
    private function cloneSchema(array $cfg, string $from, string $to): bool
    {
        $args = implode(' ', array_map('escapeshellarg', $this->connArgs($cfg)));
        $cmd  = sprintf(
            'mysqldump %s --no-data --skip-triggers %s | mysql %s %s',
            $args,
            escapeshellarg($from),
            $args,
            escapeshellarg($to)
        );
        $p = Process::fromShellCommandline($cmd, base_path(), $this->procEnv($cfg), null, 300);
        $p->run();
        if (!$p->isSuccessful()) {
            $this->error(trim($p->getErrorOutput()) ?: trim($p->getOutput()));
        }
        return $p->isSuccessful();
    }

    /** Copia los datos de una tabla entre dos BD del mismo server (rápido, sin round-trip de dump). */
    private function copyTableData(array $cfg, string $from, string $to, string $table): bool
    {
        $sql = sprintf(
            'SET FOREIGN_KEY_CHECKS=0; INSERT INTO `%s`.`%s` SELECT * FROM `%s`.`%s`; SET FOREIGN_KEY_CHECKS=1;',
            $to, $table, $from, $table
        );
        return $this->mysql($sql, $cfg);
    }

    private function procEnv(array $cfg): array
    {
        return array_merge(getenv() ?: [], ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
    }
}
