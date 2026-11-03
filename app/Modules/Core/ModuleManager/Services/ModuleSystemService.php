<?php

namespace App\Modules\Core\ModuleManager\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Dependencias de sistema de los add-ons: corre los scripts que un módulo declara en
 * su module.json ("system": {"install": ..., "purge": ...}) y deja que la página de
 * add-ons siga la corrida y le pase la contraseña cuando el script la pide.
 * La escalada a root (root → sudo sin contraseña → sudo → su) vive en
 * deploy/system/lib.sh; este servicio solo lanza, vigila y hace de canal.
 *
 * Cada corrida vive en storage/app/module-system/runs/<id>/ — estado local de este
 * servidor, no de la base de datos (el script viaja en git con el add-on):
 *   state.json     slug, acción, estado, pid, quién la lanzó, fechas, código de salida
 *   output.log     salida del script (la que ve la página)
 *   password.fifo  canal por el que llega la contraseña; no guarda nada en disco
 *   need_password  lo escribe el script mientras espera la contraseña
 */
class ModuleSystemService
{
    public const ACTIONS = ['install', 'purge'];

    private const RUN_ID_PATTERN = '/^\d{8}-\d{9}-[a-f0-9]{6}$/';   // fecha-horaMilisegundos-azar: ordena cronológico
    private const LOG_TAIL_BYTES = 200000;
    private const KEEP_RUNS_DAYS = 30;

    // ── Lo que declara el módulo ────────────────────────────────────────────

    /** {install: bool, purge: bool, description: string} o null si el módulo no declara nada. */
    public function declared(array $manifest): ?array
    {
        $system = $manifest['system'] ?? null;
        if (! is_array($system)) {
            return null;
        }
        $install = $this->resolveScript($manifest, 'install') !== null;
        $purge   = $this->resolveScript($manifest, 'purge') !== null;
        if (! $install && ! $purge) {
            return null;
        }

        return [
            'install'     => $install,
            'purge'       => $purge,
            'description' => (string) ($system['description'] ?? ''),
        ];
    }

    public function manifest(string $slug): array
    {
        foreach (app(ModuleManagerService::class)->manifests() as $m) {
            if (($m['slug'] ?? null) === $slug) {
                return $m;
            }
        }
        throw new \InvalidArgumentException("Módulo '{$slug}' no encontrado.");
    }

    /** Ruta absoluta del script, solo si existe y queda DENTRO de la carpeta del módulo. */
    private function resolveScript(array $manifest, string $action): ?string
    {
        $rel = $manifest['system'][$action] ?? null;
        if (! is_string($rel) || trim($rel) === '' || ! isset($manifest['_dir'])) {
            return null;
        }
        $dir  = realpath($manifest['_dir']);
        $path = realpath($manifest['_dir'] . '/' . ltrim($rel, '/'));
        if ($dir === false || $path === false || ! is_file($path)) {
            return null;
        }

        return str_starts_with($path, $dir . DIRECTORY_SEPARATOR) ? $path : null;
    }

    public function scriptPath(string $slug, string $action): string
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Acción '{$action}' no válida (install|purge).");
        }
        $path = $this->resolveScript($this->manifest($slug), $action);
        if ($path === null) {
            throw new \InvalidArgumentException("El módulo '{$slug}' no declara un script de sistema '{$action}' válido.");
        }

        return $path;
    }

    // ── Lanzar desde la página ──────────────────────────────────────────────

    /**
     * Crea la corrida y lanza `php artisan module:system` desprendido (mismo patrón que
     * "Buscar actualizaciones"): el script puede tardar minutos y esperar una contraseña.
     */
    public function start(string $slug, string $action, bool $withLifecycle = false, bool $force = false, ?string $startedBy = null): array
    {
        $script = $this->scriptPath($slug, $action);
        $startedBy ??= $this->currentUser();

        $lock = fopen($this->baseDir() . '/start.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->prune();
            $active = $this->activeRun($slug);
            if ($active !== null) {
                throw new \LogicException("Ya hay una corrida de dependencias en curso para '{$slug}' ({$active['id']}).");
            }

            $id  = now()->format('Ymd-Hisv') . '-' . bin2hex(random_bytes(3));
            $dir = $this->runDir($id);
            if (! mkdir($dir, 0700, true)) {
                throw new \RuntimeException("No pude crear {$dir}.");
            }
            if (! posix_mkfifo($dir . '/password.fifo', 0600)) {
                throw new \RuntimeException('No pude crear el canal de la contraseña.');
            }
            touch($dir . '/output.log');
            $this->writeState($id, [
                'id'             => $id,
                'slug'           => $slug,
                'action'         => $action,
                'script'         => str_replace(base_path() . '/', '', $script),
                'with_lifecycle' => $withLifecycle,
                'force'          => $force,
                'status'         => 'starting',
                'started_by'     => $startedBy,
                'created_at'     => now()->toIso8601String(),
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $cmd = sprintf(
            'cd %s && PATH="${PATH:-/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin}" setsid nohup %s %s module:system %s %s --run=%s%s%s > %s 2>&1 < /dev/null &',
            escapeshellarg(base_path()),
            escapeshellarg($this->phpCli()),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($action),
            escapeshellarg($slug),
            escapeshellarg($id),
            $withLifecycle ? ' --lifecycle' : '',
            $force ? ' --force' : '',
            escapeshellarg($dir . '/runner.log')
        );
        shell_exec($cmd);

        Log::info("ModuleSystem: {$action} de {$slug} lanzado (corrida {$id}) por " . ($startedBy ?? 'consola'));

        return $this->state($slug, $id);
    }

    // ── Ejecutar (lo llama el comando) ──────────────────────────────────────

    /**
     * Corre el script. Con $runId (lanzado por la página) la salida va al output.log de la
     * corrida y la contraseña llega por la FIFO; sin $runId (terminal) la salida va a la
     * terminal y lib.sh pide la contraseña ahí mismo. Devuelve el código de salida.
     */
    public function execute(string $slug, string $action, ?string $runId, bool $withLifecycle, bool $force = false): int
    {
        $script = $this->scriptPath($slug, $action);
        $env = array_merge(getenv(), [
            // Lanzado desde php-fpm (clear_env) el proceso puede no traer PATH.
            'PATH'                   => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'MEGAISP_ROOT'           => base_path(),
            'MEGAISP_MODULE_SLUG'    => $slug,
            'MEGAISP_MODULE_DIR'     => $this->manifest($slug)['_dir'],
            'MEGAISP_SYSTEM_ACTION'  => $action,
            'MEGAISP_SYSTEM_RUN_DIR' => $runId !== null ? $this->runDir($runId) : '',
        ]);

        if ($runId === null) {
            $proc = proc_open(['bash', $script], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, base_path(), $env);
            $code = is_resource($proc) ? proc_close($proc) : 127;
            if ($code === 0 && $withLifecycle && $action === 'install') {
                app(ModuleLifecycleService::class)->install($slug, $force);
            }

            return $code;
        }

        $log = $this->runDir($runId) . '/output.log';
        $this->updateState($runId, [
            'status'     => 'running',
            'pid'        => getmypid(),
            'started_at' => now()->toIso8601String(),
        ]);
        $verbo = $action === 'install' ? 'Instalar' : 'Purgar';
        $this->appendLog($runId, "== {$verbo} dependencias de {$slug} ({$this->readState($runId)['script']}) — "
            . now()->format('Y-m-d H:i:s') . ' — usuario del sistema: ' . (posix_getpwuid(posix_geteuid())['name'] ?? '?') . " ==\n");

        $proc = proc_open(
            ['bash', $script],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            base_path(),
            $env
        );
        $code = is_resource($proc) ? proc_close($proc) : 127;
        @unlink($this->runDir($runId) . '/need_password');

        $this->appendLog($runId, "\n== El script terminó con código {$code} ==\n");
        $final = ['exit_code' => $code];

        if ($code === 0 && $withLifecycle && $action === 'install') {
            try {
                app(ModuleLifecycleService::class)->install($slug, $force);
                $final['lifecycle'] = 'installed';
                $this->appendLog($runId, "== Módulo {$slug} instalado (tablas, permisos y menú) ==\n");
            } catch (\Throwable $e) {
                $final['lifecycle'] = 'failed';
                $final['error'] = 'Las dependencias quedaron instaladas, pero el módulo no: ' . $e->getMessage();
                $this->appendLog($runId, '== ' . $final['error'] . " ==\n");
                $code = 1;
            }
        }

        $final['status']      = $code === 0 ? 'success' : 'failed';
        $final['finished_at'] = now()->toIso8601String();
        $this->updateState($runId, $final);
        Log::info("ModuleSystem: corrida {$runId} ({$action} {$slug}) terminó: {$final['status']} (código {$code})");

        return $code;
    }

    // ── Seguimiento y contraseña ────────────────────────────────────────────

    public function state(string $slug, string $runId): array
    {
        $state = $this->readState($runId);
        if (($state['slug'] ?? null) !== $slug) {
            throw new \InvalidArgumentException('La corrida no es de este módulo.');
        }

        // Proceso muerto sin cerrar su estado (se cayó el server, lo mataron...).
        if (in_array($state['status'] ?? '', ['running', 'starting'], true)) {
            $pid = (int) ($state['pid'] ?? 0);
            $edad = time() - strtotime($state['created_at'] ?? 'now');
            $muerto = $pid > 0 ? ! $this->alive($pid) : $edad > 60;
            if ($muerto) {
                $state = $this->updateState($runId, [
                    'status'      => 'failed',
                    'error'       => 'La corrida se interrumpió antes de terminar. Revisa el log.',
                    'finished_at' => now()->toIso8601String(),
                ]);
            }
        }

        $need = $this->runDir($runId) . '/need_password';
        $state['password_for'] = null;
        if (($state['status'] ?? '') === 'running' && is_file($need)) {
            $data = [];
            foreach (file($need, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                $data[$k] = $v;
            }
            $state['status'] = 'awaiting_password';
            $state['password_for'] = [
                'user'    => $data['user'] ?? '',
                'via'     => $data['via'] ?? '',
                'attempt' => (int) ($data['attempt'] ?? 1),
            ];
        }

        [$state['log'], $state['log_truncated']] = $this->logTail($runId);

        return $state;
    }

    /** La contraseña va directo a la FIFO que el script está leyendo: no se guarda ni se registra. */
    public function submitPassword(string $slug, string $runId, string $password): void
    {
        $this->requireAwaiting($slug, $runId);
        if ($password === '' || preg_match('/[\r\n]/', $password)) {
            throw new \InvalidArgumentException('Contraseña vacía o con saltos de línea.');
        }
        $this->writeFifo($runId, 'PW ' . $password . "\n");
    }

    public function cancel(string $slug, string $runId): void
    {
        $this->requireAwaiting($slug, $runId);
        $this->writeFifo($runId, "CANCEL\n");
    }

    /** Última corrida del módulo (para mostrarla al recargar la página). */
    public function latestRun(string $slug): ?array
    {
        $ids = [];
        foreach (glob($this->baseDir() . '/runs/*/state.json') ?: [] as $file) {
            $state = json_decode((string) file_get_contents($file), true);
            if (($state['slug'] ?? null) === $slug) {
                $ids[] = $state['id'];
            }
        }
        if (empty($ids)) {
            return null;
        }
        rsort($ids);
        $state = $this->state($slug, $ids[0]);
        unset($state['log'], $state['log_truncated']);

        return $state;
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    private function requireAwaiting(string $slug, string $runId): void
    {
        $state = $this->state($slug, $runId);
        if ($state['status'] !== 'awaiting_password') {
            throw new \LogicException('La corrida no está esperando una contraseña.');
        }
    }

    private function writeFifo(string $runId, string $data): void
    {
        // r+ (lectura+escritura): abrir una FIFO así no se bloquea aunque nadie la lea.
        $fp = @fopen($this->runDir($runId) . '/password.fifo', 'r+');
        if ($fp === false) {
            throw new \RuntimeException('No pude abrir el canal de la contraseña.');
        }
        fwrite($fp, $data);
        fclose($fp);
    }

    private function activeRun(string $slug): ?array
    {
        foreach (glob($this->baseDir() . '/runs/*/state.json') ?: [] as $file) {
            $state = json_decode((string) file_get_contents($file), true);
            if (($state['slug'] ?? null) !== $slug) {
                continue;
            }
            $actual = $this->state($slug, $state['id']);
            if (in_array($actual['status'], ['starting', 'running', 'awaiting_password'], true)) {
                return $actual;
            }
        }

        return null;
    }

    /** Borra corridas terminadas de más de KEEP_RUNS_DAYS días. */
    private function prune(): void
    {
        $limite = time() - self::KEEP_RUNS_DAYS * 86400;
        foreach (glob($this->baseDir() . '/runs/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $state = json_decode((string) @file_get_contents($dir . '/state.json'), true) ?: [];
            $terminada = in_array($state['status'] ?? 'failed', ['success', 'failed'], true);
            if ($terminada && filemtime($dir) < $limite) {
                foreach (glob($dir . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
            }
        }
    }

    private function logTail(string $runId): array
    {
        $file = $this->runDir($runId) . '/output.log';
        if (! is_file($file)) {
            return ['', false];
        }
        $size = filesize($file);
        $truncated = $size > self::LOG_TAIL_BYTES;
        $fp = fopen($file, 'r');
        if ($truncated) {
            fseek($fp, -self::LOG_TAIL_BYTES, SEEK_END);
        }
        $text = (string) stream_get_contents($fp);
        fclose($fp);
        // Sin colores ANSI ni retornos de carro (barras de progreso de npm/apt).
        $text = preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $text);
        $text = preg_replace('/\r(?!\n)/', "\n", $text);

        return [mb_convert_encoding($text, 'UTF-8', 'UTF-8'), $truncated];
    }

    private function appendLog(string $runId, string $text): void
    {
        file_put_contents($this->runDir($runId) . '/output.log', $text, FILE_APPEND);
    }

    private function readState(string $runId): array
    {
        if (! preg_match(self::RUN_ID_PATTERN, $runId)) {
            throw new \InvalidArgumentException('Id de corrida no válido.');
        }
        $file = $this->runDir($runId) . '/state.json';
        if (! is_file($file)) {
            throw new \InvalidArgumentException("No existe la corrida '{$runId}'.");
        }

        return json_decode((string) file_get_contents($file), true) ?: [];
    }

    private function writeState(string $runId, array $state): array
    {
        $file = $this->runDir($runId) . '/state.json';
        file_put_contents($file . '.tmp', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($file . '.tmp', $file);

        return $state;
    }

    private function updateState(string $runId, array $changes): array
    {
        return $this->writeState($runId, array_merge($this->readState($runId), $changes));
    }

    private function currentUser(): ?string
    {
        $user = auth()->user();

        return $user ? (string) ($user->login_user ?? $user->email ?? $user->id) : null;
    }

    private function alive(int $pid): bool
    {
        return posix_kill($pid, 0) || posix_get_last_error() === 1; // 1 = EPERM: existe pero es de otro usuario
    }

    private function runDir(string $runId): string
    {
        return $this->baseDir() . '/runs/' . $runId;
    }

    private function baseDir(): string
    {
        $dir = storage_path('app/module-system');
        if (! is_dir($dir . '/runs')) {
            @mkdir($dir . '/runs', 0700, true);
        }

        return $dir;
    }

    private function phpCli(): string
    {
        $php = (new PhpExecutableFinder())->find(false);
        if (is_string($php) && $php !== '') {
            return $php;
        }
        $fallback = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        if (is_file($fallback) && is_executable($fallback)) {
            return $fallback;
        }
        throw new \RuntimeException('No se encontró el binario PHP CLI para lanzar la corrida.');
    }
}
