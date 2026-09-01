<?php

namespace App\Console\Commands\Active;

use App\Console\Commands\Concerns\MideContencionDryrun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Fase 1a-i (item #817, sub-item de #797): candados de seguridad + drop/recreate de la
 * BD `{database}_dryrun` vacía. Fase 1a-ii parte 1/2 (item #821): añade el bloque de
 * Migrator para correr TODAS las migraciones de `database/migrations` (NO `migrations_old`)
 * contra esa BD recién creada — construcción del código solamente, la corrida real completa
 * de las ~555 migraciones (con su verificación) queda para la parte 2/2.
 *
 * Candados (decisión ya tomada en las preguntas del item #797, opción recomendada q1):
 *   (a) el nombre de BD objetivo se DERIVA de la conexión configurada + sufijo '_dryrun'
 *       fijo (nunca se acepta un nombre arbitrario por parámetro);
 *   (b) bloqueo duro si app()->environment('production') — ni siquiera se parsea --force;
 *   (c) --force explícito obligatorio, si falta se aborta sin tocar nada;
 *   (d) verificación de que el nombre objetivo no coincida con la BD principal
 *       (imposible dropear `megaisp` por accidente).
 *
 * Limitación conocida (documentada, no resuelta aquí — ver decisión registrada en el item
 * #821 con circuito:reportar --tipo=decision): el Migrator aborta en la PRIMERA migración
 * que falle: no hay corrida lote-por-lote que permita reportar "cuáles fallaron" en plural.
 * Se captura y reporta el mensaje de la migración que truena; suficiente para esta fase.
 */
class RebuildDryrunSchemaCommand extends Command
{
    use MideContencionDryrun;

    protected $signature = 'schema:rebuild-dryrun
                            {--force : Requerido explícitamente para (re)crear la BD dryrun}';

    protected $description = 'Candados de seguridad + drop/recreate de la BD auxiliar {database}_dryrun vacía + corre todas las migraciones (Fase 1a de #797).';

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
        $this->iniciarMedicionContencion('schema:rebuild-dryrun', $tempDb);

        $charset   = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';

        $start = microtime(true);
        if (!$this->mysql("DROP DATABASE IF EXISTS `{$tempDb}`; CREATE DATABASE `{$tempDb}` CHARACTER SET {$charset} COLLATE {$collation};", $cfg)) {
            $this->error("No se pudo (re)crear la BD `{$tempDb}`. Verifica el grant: "
                . "GRANT ALL PRIVILEGES ON `{$tempDb}`.* TO '{$cfg['username']}'@'<host>'; FLUSH PRIVILEGES;");
            $this->cerrarMedicionContencion('schema:rebuild-dryrun', $tempDb, false, 'No se pudo (re)crear la BD (grant)');
            return 1;
        }
        $elapsedRecreate = round(microtime(true) - $start, 2);
        $this->info("BD `{$tempDb}` recreada vacía en {$elapsedRecreate}s. Corriendo migraciones de database/migrations…");

        $exit = $this->runMigrations($cfg, $tempDb, $elapsedRecreate);
        $this->cerrarMedicionContencion('schema:rebuild-dryrun', $tempDb, $exit === 0);

        return $exit;
    }

    /**
     * Registra la conexión 'dryrun' apuntando a la BD ya vacía y corre TODAS las
     * migraciones de database/migrations (NO migrations_old) vía el Migrator directo
     * — nunca el comando 'migrate' interactivo, que se cuelga esperando confirmación
     * de producción en contexto no interactivo. La conexión default del proceso se
     * restaura SIEMPRE en el finally (este mismo proceso puede seguir hablando con la
     * BD real después).
     */
    private function runMigrations(array $cfg, string $tempDb, float $elapsedRecreate): int
    {
        $originalDefault = config('database.default');

        config(['database.connections.dryrun' => array_merge($cfg, ['database' => $tempDb])]);
        DB::purge('dryrun');

        /** @var \Illuminate\Database\Migrations\Migrator $migrator */
        $migrator = app('migrator');
        $migrator->setOutput($this->output);

        $start = microtime(true);
        $ran   = [];
        $error = null;

        try {
            $migrator->setConnection('dryrun');
            if (!$migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }
            $ran = $migrator->run([database_path('migrations')]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        } finally {
            $migrator->setConnection($originalDefault);
            DB::setDefaultConnection($originalDefault);
            DB::purge('dryrun');
        }

        $elapsedMigrate = round(microtime(true) - $start, 2);

        $this->table(
            ['BD', 'Migraciones corridas', 'Tiempo recreate', 'Tiempo migrate', 'Resultado'],
            [[$tempDb, count($ran), "{$elapsedRecreate}s", "{$elapsedMigrate}s", $error ? 'FALLÓ' : 'OK']]
        );

        if ($error !== null) {
            $this->error('MIGRACIÓN FALLÓ contra la BD dryrun: ' . $error);
            $this->warn('El Migrator aborta en la primera migración que falla — este es el mensaje de esa migración, no un resumen de todas las pendientes.');
            return 1;
        }

        $this->info('Dry-run de esquema OK: ' . count($ran) . " migración(es) corrieron sin error contra `{$tempDb}`.");
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
