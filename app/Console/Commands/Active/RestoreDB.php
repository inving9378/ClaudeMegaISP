<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class RestoreDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restore_db:process
        {zip : Ruta al archivo .zip generado por backup_db:process}
        {--fresh : Elimina todas las tablas y vistas de la BD antes de importar}
        {--force : No pedir confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restaura la base de datos desde un zip generado por backup_db:process';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        $zipPath = $this->argument('zip');

        if (!is_file($zipPath)) {
            $this->error("No se encontró el archivo: $zipPath");
            return Command::FAILURE;
        }

        $db = DB::connection()->getDatabaseName();

        $this->ensureDatabaseExists();

        if (!$this->option('force') && !$this->confirm("Se va a importar sobre la BD '$db'" . ($this->option('fresh') ? ' ELIMINANDO antes todas sus tablas' : '') . ". ¿Continuar?")) {
            $this->info('Cancelado.');
            return Command::SUCCESS;
        }

        $tmpDir = storage_path('backup/restore-tmp-' . getmypid());
        File::ensureDirectoryExists($tmpDir);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $this->error("No se pudo abrir el zip: $zipPath");
                return Command::FAILURE;
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            $sqlFiles = File::glob($tmpDir . '/*.sql');
            if (empty($sqlFiles)) {
                $this->error('El zip no contiene ningún archivo .sql en su raíz.');
                return Command::FAILURE;
            }

            if ($this->option('fresh')) {
                $this->dropAllTables();
            }

            foreach ($sqlFiles as $sqlFile) {
                $this->info('Importando ' . basename($sqlFile) . ' (' . round(filesize($sqlFile) / 1048576, 1) . ' MB)...');
                $statements = $this->importSqlFile($sqlFile);
                $this->info("Listo: $statements sentencias ejecutadas.");
            }
        } catch (\Exception $e) {
            Log::error('restore_db error: ' . $e->getMessage());
            $this->error('Error durante la restauración: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            File::deleteDirectory($tmpDir);
        }

        Log::info('Base de datos restaurada desde ' . basename($zipPath));
        activity()->log('Restauración de BD desde backup');

        $this->info('Restauración completada.');

        return Command::SUCCESS;
    }

    /**
     * Crea la base de datos configurada en .env si aún no existe.
     */
    private function ensureDatabaseExists(): void
    {
        try {
            DB::connection()->getPdo();
            return;
        } catch (\Exception $e) {
            // 1049 = Unknown database; cualquier otro error (credenciales, host) se propaga
            if (!str_contains($e->getMessage(), 'Unknown database')) {
                throw $e;
            }
        }

        $config = config('database.connections.' . config('database.default'));

        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%s', $config['host'], $config['port'] ?? 3306),
            $config['username'],
            $config['password']
        );
        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $config['database']
        ));

        // Forzar reconexión ya con la BD creada
        DB::purge();

        $this->info("La BD '{$config['database']}' no existía y fue creada.");
    }

    /**
     * Elimina todas las tablas y vistas de la BD actual.
     */
    private function dropAllTables(): void
    {
        $this->warn('Eliminando tablas y vistas existentes...');

        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $rows = DB::select('SHOW FULL TABLES');
        foreach ($rows as $row) {
            $values = array_values((array) $row);
            [$name, $type] = [$values[0], $values[1]];
            if ($type === 'VIEW') {
                $pdo->exec("DROP VIEW IF EXISTS `$name`");
            } else {
                $pdo->exec("DROP TABLE IF EXISTS `$name`");
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $this->warn(count($rows) . ' tablas/vistas eliminadas.');
    }

    /**
     * Ejecuta un dump SQL por streaming, sentencia a sentencia,
     * sin cargar el archivo completo en memoria.
     */
    private function importSqlFile(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \Exception("No se pudo abrir el archivo SQL: $path");
        }

        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET UNIQUE_CHECKS=0');

        $bar = $this->output->createProgressBar(filesize($path));
        $bar->setFormat('%current%/%max% bytes [%bar%] %percent:3s%%');

        $buffer = '';
        $statements = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $bar->advance(strlen($line));

                $trimmed = ltrim($line);

                // Saltar comentarios y líneas vacías fuera de una sentencia
                if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                    continue;
                }

                $buffer .= $line;

                // mysqldump-php escapa los saltos de línea dentro de strings,
                // por lo que una línea terminada en ';' siempre cierra la sentencia
                if (str_ends_with(rtrim($line), ';')) {
                    $pdo->exec($buffer);
                    $buffer = '';
                    $statements++;
                }
            }

            if (trim($buffer) !== '') {
                $pdo->exec($buffer);
                $statements++;
            }
        } finally {
            fclose($handle);
            $pdo->exec('SET UNIQUE_CHECKS=1');
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $bar->finish();
            $this->newLine();
        }

        return $statements;
    }
}
