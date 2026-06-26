<?php

namespace App\Services\BackupDb;

use Symfony\Component\Process\Process;

/**
 * Motor de respaldo compartido: ejecuta el binario `mysqldump` vía Symfony\Process.
 *
 * Robusto frente a MySQL 8.4 (plugin `mysql_native_password` deshabilitado):
 *  - conexión por SOCKET Unix (`--host=localhost`, no TCP), que no pasa por el plugin
 *  - credenciales desde `config('database.connections.mysql.*')`, nunca `env()` directo
 *  - contraseña vía `MYSQL_PWD` para no exponerla en `ps`/historial
 *
 * Devuelve el volcado SQL como string para que cada llamador lo empaquete a su manera
 * (ZIP, gzip, etc.). Para volúmenes grandes, `dumpToGzipFile()` canaliza la salida a
 * gzip con memoria constante.
 */
class MysqldumpEngine
{
    private const TIMEOUT = 3600;

    /**
     * Ejecuta mysqldump y DEVUELVE el volcado SQL como string.
     *
     * @param array $options flags/tablas (ver buildShellCommand)
     */
    public function dump(array $options = []): string
    {
        $process = Process::fromShellCommandline($this->buildShellCommand($options));
        $process->setEnv($this->env());
        $process->setTimeout(self::TIMEOUT);
        $process->run();

        $this->guard($process);

        return $process->getOutput();
    }

    /**
     * Ejecuta mysqldump y canaliza la salida comprimida (gzip) a un archivo.
     * Memoria constante: no carga el volcado completo en PHP.
     *
     * Nota: el código de salida del pipe refleja a `gzip`, por lo que el llamador
     * debe validar el .gz resultante (existencia, tamaño, `gzip -t`).
     */
    public function dumpToGzipFile(string $targetGzFile, array $options = []): void
    {
        $cmd = $this->buildShellCommand($options) . ' | gzip > ' . escapeshellarg($targetGzFile);

        $process = Process::fromShellCommandline($cmd);
        $process->setEnv($this->env());
        $process->setTimeout(self::TIMEOUT);
        $process->run();

        $this->guard($process);
    }

    /**
     * Ejecuta mysqldump y vuelca la salida SQL (sin comprimir) a un archivo por streaming.
     * Memoria constante: no carga el volcado en PHP (a diferencia de dump():string).
     *
     * A diferencia de dumpToGzipFile, aquí NO hay pipe, por lo que el código de salida
     * refleja directamente a mysqldump y `guard()` detecta fallos correctamente.
     *
     * @param bool $append true → anexa (>>) al archivo; false → lo sobrescribe (>)
     */
    public function dumpToFile(string $targetSqlFile, array $options = [], bool $append = false): void
    {
        $redirect = $append ? '>>' : '>';
        $cmd = $this->buildShellCommand($options) . ' ' . $redirect . ' ' . escapeshellarg($targetSqlFile);

        $process = Process::fromShellCommandline($cmd);
        $process->setEnv($this->env());
        $process->setTimeout(self::TIMEOUT);
        $process->run();

        $this->guard($process);
    }

    /**
     * Construye el comando mysqldump con conexión robusta + las opciones del llamador.
     *
     * Núcleo compartido (siempre): --host=localhost (socket), --port, --user.
     * Opciones por llamador:
     *  - 'flags'         => array de flags literales (definidos en código, sin input externo)
     *  - 'ignore_tables' => array de tablas a excluir (--ignore-table=db.tabla)
     *  - 'tables'        => array de tablas a incluir (posicional: solo esas)
     */
    private function buildShellCommand(array $options): string
    {
        $db   = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $port = config('database.connections.mysql.port', '3306');

        $parts = [
            'mysqldump',
            '--host=localhost',
            '--port=' . escapeshellarg((string) $port),
            '--user=' . escapeshellarg((string) $user),
            // mysqldump 8.x intenta volcar tablespaces, que exige el privilegio PROCESS
            // (que el usuario de la app no tiene) → "Access denied ... PROCESS privilege".
            // No necesitamos tablespaces en estos respaldos lógicos.
            '--no-tablespaces',
        ];

        foreach (($options['flags'] ?? []) as $flag) {
            $parts[] = $flag; // flags estáticos definidos en código
        }

        foreach (($options['ignore_tables'] ?? []) as $table) {
            $parts[] = '--ignore-table=' . escapeshellarg($db . '.' . $table);
        }

        $parts[] = escapeshellarg($db);

        foreach (($options['tables'] ?? []) as $table) {
            $parts[] = escapeshellarg($table);
        }

        return implode(' ', $parts);
    }

    private function env(): array
    {
        return ['MYSQL_PWD' => (string) config('database.connections.mysql.password', '')];
    }

    private function guard(Process $process): void
    {
        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'mysqldump falló (exit ' . $process->getExitCode() . '): ' . trim($process->getErrorOutput())
            );
        }
    }
}
