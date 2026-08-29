<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Fase 1a-i (item #817, sub-item de #797): SOLO candados de seguridad + drop/recreate
 * de la BD `{database}_dryrun` vacía. NO corre migraciones todavía — eso es la Fase 1a-ii
 * (sub-item hermano), que reusará este mismo comando añadiendo el bloque de Migrator.
 *
 * Candados (decisión ya tomada en las preguntas del item #797, opción recomendada q1):
 *   (a) el nombre de BD objetivo se DERIVA de la conexión configurada + sufijo '_dryrun'
 *       fijo (nunca se acepta un nombre arbitrario por parámetro);
 *   (b) bloqueo duro si app()->environment('production') — ni siquiera se parsea --force;
 *   (c) --force explícito obligatorio, si falta se aborta sin tocar nada;
 *   (d) verificación de que el nombre objetivo no coincida con la BD principal
 *       (imposible dropear `megaisp` por accidente).
 */
class RebuildDryrunSchemaCommand extends Command
{
    protected $signature = 'schema:rebuild-dryrun
                            {--force : Requerido explícitamente para (re)crear la BD dryrun}';

    protected $description = 'Candados de seguridad + drop/recreate de la BD auxiliar {database}_dryrun vacía (sin migrar aún — Fase 1a-i de #797).';

    public function handle(): int
    {
        // Candado (b): ni siquiera se evalúa --force en producción.
        if (app()->environment('production')) {
            $this->error('Bloqueado: este comando NUNCA corre en producción (app()->environment(production)).');
            return 1;
        }

        // Candado (c): --force explícito.
        if (!$this->option('force')) {
            $this->error('Faltó --force. Nada se tocó. Vuelve a correr: php artisan schema:rebuild-dryrun --force');
            return 1;
        }

        $cfg = config('database.connections.' . config('database.default'));
        if (($cfg['driver'] ?? null) !== 'mysql') {
            $this->error('Solo soportado para MySQL. Conexión por defecto: ' . ($cfg['driver'] ?? 'N/A'));
            return 1;
        }

        $dbName = $cfg['database'];
        $tempDb = $dbName . '_dryrun';

        // Candado (a)+(d): el nombre objetivo SIEMPRE termina en '_dryrun' y nunca puede
        // coincidir con la BD principal (defensa en profundidad aunque el punto anterior
        // ya lo garantice matemáticamente).
        if ($tempDb === $dbName || !str_ends_with($tempDb, '_dryrun')) {
            $this->error("Candado de nombre falló: objetivo `{$tempDb}` inválido respecto a la BD principal `{$dbName}`.");
            return 1;
        }

        $this->line("schema:rebuild-dryrun → BD objetivo `{$tempDb}` (drop/recreate vacía)");

        $charset   = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';

        $start = microtime(true);
        if (!$this->mysql("DROP DATABASE IF EXISTS `{$tempDb}`; CREATE DATABASE `{$tempDb}` CHARACTER SET {$charset} COLLATE {$collation};", $cfg)) {
            $this->error("No se pudo (re)crear la BD `{$tempDb}`. Verifica el grant: "
                . "GRANT ALL PRIVILEGES ON `{$tempDb}`.* TO '{$cfg['username']}'@'<host>'; FLUSH PRIVILEGES;");
            return 1;
        }
        $elapsed = round(microtime(true) - $start, 2);

        $this->table(
            ['BD', 'Charset', 'Collation', 'Tablas actuales', 'Tiempo'],
            [[$tempDb, $charset, $collation, 0, "{$elapsed}s"]]
        );
        $this->info("BD `{$tempDb}` recreada vacía — lista para recibir migraciones en la Fase 1a-ii.");

        return 0;
    }

    /**
     * Argumentos de conexión para el CLI de mysql. Prefiere socket (como
     * DryRunMigrationsCommand/backup_db:process). La contraseña va por MYSQL_PWD
     * (env del proceso), nunca en la línea de comando.
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

    private function procEnv(array $cfg): array
    {
        return array_merge(getenv() ?: [], ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
    }
}
