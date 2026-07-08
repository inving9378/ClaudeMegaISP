<?php

namespace App\Console\Commands\Active;

use App\Models\DeploymentLog;
use App\Models\Release;
use App\Services\Deploy\DeploymentLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class RemoteDeployCommand extends Command
{
    // OJO: la opción NO puede llamarse --version: colisiona con la opción global
    // reservada -V/--version de Symfony Console, que imprime el banner y aborta
    // el comando sin ejecutar handle(). Por eso se usa --app-version.
    protected $signature = 'remote:deploy {logId}
                            {--app-version=}
                            {--title=}
                            {--summary=}
                            {--release-date=}';

    protected $description = 'Ejecuta el deploy en el servidor receptor (backup, git checkout tag, migrate, optimize) con rollback automático al código anterior si un paso crítico falla.';

    public function handle(): int
    {
        $log = DeploymentLog::find($this->argument('logId'));
        if (!$log) {
            $this->error("DeploymentLog #{$this->argument('logId')} no encontrado.");
            return 1;
        }

        // El comando es el dueño del lock: protege por igual los dos caminos de disparo
        // (job en cola y proceso nohup en modo sync). Si ya hay un deploy en curso, no
        // arranca otro. Se libera SIEMPRE en el finally, incluso ante excepción.
        if (!DeploymentLock::acquire($log->id)) {
            $log->update([
                'status'        => 'failed',
                'error_message' => 'Ya hay un deploy en curso (ID: ' . DeploymentLock::currentId() . '). Espera a que termine.',
                'finished_at'   => now(),
            ]);
            $this->error('Ya hay un deploy en curso (ID: ' . DeploymentLock::currentId() . ').');
            return 1;
        }

        try {
            return $this->runDeploy($log);
        } finally {
            DeploymentLock::release();
        }
    }

    private function runDeploy(DeploymentLog $log): int
    {
        // M2 — Higiene previa: matar procesos huérfanos de deploys anteriores (migrate/npm colgados
        // con stdout abierto) ANTES de arrancar, para que no cuelguen ni contaminen este deploy. El
        // DeploymentLock ya garantiza que ningún deploy legítimo corre en paralelo → cualquier proceso
        // que empareje un patrón exclusivo de deploy es un zombie de una corrida previa.
        $orphans = $this->killOrphanDeployProcesses();
        if ($orphans) {
            $this->warn('  [higiene]: procesos huérfanos de deploy terminados → ' . implode(', ', $orphans));
        } else {
            $this->line('  [higiene]: sin procesos huérfanos de deploy.');
        }

        $version     = $this->option('app-version') ?? '';
        $title       = $this->option('title') ?? '';
        $summary     = $this->option('summary') ?? '';
        $releaseDate = $this->option('release-date') ?? now()->toDateString();

        // Capturar el commit/tag actual ANTES de tocar nada — es el punto de rollback.
        $previousCommit = $this->getCurrentHead();
        if ($previousCommit) {
            $log->update(['rollback_to_version' => $previousCommit]);
        }

        // Construir el comando git para sincronizar:
        // - Si hay versión/tag específico: fetch + checkout del tag (exacto, sin sobrescribir .env/storage)
        // - Sin versión: fallback a reset --hard origin/main (webhook legacy sin tag)
        // El frontend se compila EN el servidor (paso npm_build) — los assets ya NO
        // viajan en git. Por eso el checkout/reset solo trae el código fuente.
        $gitSyncCmd = $version
            ? "git fetch origin && git checkout tags/{$version}"
            : 'git fetch origin main && git reset --hard origin/main';

        $stepDefs = [
            // 1. Respaldo de BD ANTES de tocar nada — la restauración es MANUAL si algo falla
            ['key' => 'backup_db',     'name' => 'Respaldar base de datos',         'type' => 'artisan', 'cmd' => 'backup_db:process',    'timeout' => 300, 'critical' => true],
            // 2. Checkout del tag (o reset a main si no hay tag)
            ['key' => 'git_sync',      'name' => 'Sincronizar código (' . ($version ?: 'origin/main') . ')', 'type' => 'shell', 'cmd' => $gitSyncCmd, 'timeout' => 120, 'critical' => true],
            // 2.5 Dry-run de migraciones contra una copia de la BD — DESPUÉS de git_sync (necesita
            //     los archivos de migración nuevos) y ANTES de npm_build (el paso lento) y del
            //     migrate real. Crítico: si una migración va a tronar, abortamos barato aquí
            //     (solo se hizo backup + checkout → rollback = git reset limpio, sin esquema tocado).
            //     Esquema-only por defecto; para validar fallos dependientes de datos, añadir
            //     --with-data=tabla1,tabla2 al comando.
            //     Timeout alineado con el del migrate real (config('deployment.migrate_timeout'),
            //     default 600s): si el dry-run fuera más corto, una migración lenta pasaría el
            //     dry-run pero timeoutearía el migrate real. Ambos leen la MISMA clave de config.
            ['key' => 'migrate_dryrun', 'name' => 'Validar migraciones (dry-run)',     'type' => 'shell', 'cmd' => 'php artisan deploy:dry-run-migrations', 'timeout' => config('deployment.migrate_timeout', 2400), 'critical' => true],
            // 3. Compilar el frontend EN el servidor (assets fuera de git) — crítico:
            //    si falla, se aborta y hace rollback (nunca deja prod con código nuevo y JS roto)
            //    NOTA: no se corre composer aquí a propósito — este prod usa deps de dev y
            //    `--no-dev` las eliminaba rompiendo la app. Los cambios de composer.json se
            //    aplican a mano cuando haga falta (como históricamente en este server).
            ['key' => 'npm_build',     'name' => 'Compilar frontend (npm)',         'type' => 'shell', 'cmd' => 'npm ci && npm run prod', 'timeout' => 600, 'critical' => true],
            // 4. Migraciones aditivas. Se corre como SUBPROCESO `php artisan migrate --force`
            //    (NO Artisan::call): en el contexto nohup el --force programático no frenaba
            //    el prompt de confirmación de producción y el deploy se colgaba esperando stdin.
            //    El subproceso CLI con --force sí lo salta (verificado en prod).
            //    NO es critical (un git reset sobre un esquema ya modificado es peligroso), pero
            //    tampoco es decorativo: si falla, el deploy debe terminar en 'failed' SIN revertir
            //    código → flag 'fail_deploy_no_rollback'. Timeout config('deployment.migrate_timeout')
            //    (default 600s; antes 2400s: un migrate colgado hacía esperar 40 min inútiles). Combinado
            //    con la verificación M1 (migrate:status): tras el timeout se comprueba el estado real y,
            //    si no quedan migraciones pendientes, el fallo por exit se trata como éxito y el deploy sigue.
            ['key' => 'migrate',       'name' => 'Aplicando cambios en la base de datos — puede tardar varios minutos, no cierres la ventana', 'type' => 'shell',   'cmd' => 'php artisan migrate --force', 'timeout' => config('deployment.migrate_timeout', 2400), 'critical' => false, 'fail_deploy_no_rollback' => true],
            // 5. Warm-up de cachés
            ['key' => 'optimize',      'name' => 'Optimizar cachés',                'type' => 'artisan', 'cmd' => 'optimize',             'timeout' => 30,  'critical' => false],
            // 6. Reiniciar workers de cola
            ['key' => 'queue_restart', 'name' => 'Reiniciar workers',               'type' => 'artisan', 'cmd' => 'queue:restart',        'timeout' => 10,  'critical' => false],
            // 7. Registrar el release en la DB local
            ['key' => 'save_release',  'name' => 'Guardar release en DB local',     'type' => 'inline',  'critical' => false],
        ];

        $initialSteps = array_map(fn($s) => [
            'key'         => $s['key'],
            'name'        => $s['name'],
            'status'      => 'pending',
            'output'      => '',
            'exit_code'   => null,
            'duration_ms' => 0,
            'ran_at'      => null,
        ], $stepDefs);

        $log->update([
            'status'     => 'running',
            'steps'      => $initialSteps,
            'started_at' => now(),
        ]);

        // Estado de fallo diferido: un paso 'fail_deploy_no_rollback' (migrate) que falla NO corta
        // el loop ni revierte código; marca el deploy para cerrar en 'failed' al terminar.
        $deployFailed   = false;
        $failureMessage = null;

        foreach ($stepDefs as $step) {
            $log->updateStep($step['key'], ['status' => 'running', 'ran_at' => now()->toIso8601String()]);

            if ($step['type'] === 'inline') {
                // El paso es no-crítico: registrar la release es un efecto secundario.
                // Si falla (p.ej. dato muy largo para una columna), NO debe propagar la
                // excepción y matar el comando dejando el DeploymentLog colgado en
                // «running» con todos los pasos reales ya completados (el caso del log #29).
                try {
                    $msg = $this->saveRelease($version, $title, $summary, $releaseDate);
                    $log->updateStep($step['key'], ['status' => 'success', 'output' => $msg, 'exit_code' => 0, 'duration_ms' => 0]);
                    $this->line("  [save_release]: OK — {$msg}");
                } catch (\Throwable $e) {
                    $log->updateStep($step['key'], ['status' => 'failed', 'output' => $e->getMessage(), 'exit_code' => 1, 'duration_ms' => 0]);
                    $this->error("  [save_release]: FAILED — {$e->getMessage()}");
                }
                continue;
            }

            // M3 — Saltar la compilación de frontend si ni las dependencias ni el frontend cambiaron
            // respecto al deploy anterior: los assets ya compilados persisten en el servidor (están fuera
            // de git → un checkout no los borra). Ahorra el grueso del tiempo cuando el deploy es solo PHP.
            // git_sync (paso previo) ya corrió → HEAD apunta ya al código nuevo. Falla-seguro: ante cualquier
            // duda compila (ver canSkipNpmBuild).
            if ($step['key'] === 'npm_build' && $this->canSkipNpmBuild($previousCommit)) {
                $log->updateStep($step['key'], [
                    'status'      => 'success',
                    'output'      => 'Omitido: package-lock.json y frontend sin cambios respecto al deploy anterior — se conservan los assets ya compilados.',
                    'exit_code'   => 0,
                    'duration_ms' => 0,
                ]);
                $this->line('  [npm_build]: OMITIDO (sin cambios en dependencias/frontend) — M3.');
                continue;
            }

            [$exitCode, $output, $ms] = $step['type'] === 'artisan'
                ? $this->runArtisan($step['cmd'], $step['params'] ?? [])
                : $this->runShell($step['cmd'], $step['timeout']);

            $success = $exitCode === 0;

            // M1 — Verificar en vez de confiar en el exit code (LA clave anti-cuelgue): para el paso
            // migrate, un proceso zombie con stdout abierto puede colgar/tumbar el pipeline por timeout
            // aunque las migraciones SÍ se hayan aplicado. Antes de darlo por fallido consultamos el
            // estado REAL con `migrate:status`: 0 pendientes → el migrate fue exitoso y el deploy sigue.
            if ($step['key'] === 'migrate' && !$success) {
                $pending = $this->pendingMigrationsCount();
                if ($pending === 0) {
                    $success = true;
                    $output  = trim($output) . "\n[verificación migrate:status] 0 migraciones pendientes → "
                             . "aplicadas por completo pese a exit {$exitCode} (probable proceso colgado). Deploy continúa.";
                    $this->warn("  [migrate]: exit {$exitCode} pero migrate:status = 0 pendientes → tratado como OK (M1).");
                } elseif ($pending > 0) {
                    $output = trim($output) . "\n[verificación migrate:status] {$pending} migración(es) pendiente(s) → fallo real.";
                } else {
                    // -1 = no se pudo verificar (migrate:status falló) → conservador: se mantiene el fallo.
                    $output = trim($output) . "\n[verificación migrate:status] no verificable → se mantiene el fallo por exit {$exitCode}.";
                }
            }
            $log->updateStep($step['key'], [
                'status'      => $success ? 'success' : 'failed',
                'output'      => mb_substr(trim($output), -2000),
                'exit_code'   => $exitCode,
                'duration_ms' => $ms,
            ]);

            $this->line("  [{$step['key']}]: " . ($success ? 'OK' : "FAILED (exit {$exitCode}): " . substr($output, -200)));

            if (!$success && ($step['critical'] ?? false)) {
                $this->performRollback($log, $step, $exitCode, $previousCommit);
                return 1;
            }

            if (!$success && ($step['fail_deploy_no_rollback'] ?? false)) {
                // Fatal-sin-rollback (migrate): el esquema pudo modificarse a medias → revertir
                // código (git reset) sería peligroso. Marcamos el deploy para cerrar 'failed' al
                // final del loop, SIN performRollback. NO se reintroduce el rollback de migrate.
                $deployFailed   = true;
                $failureMessage = "Paso «{$step['name']}» falló (exit {$exitCode}). Deploy marcado FAILED "
                                . "SIN revertir código (posible esquema a medias — revisar migraciones/BD "
                                . "manualmente). Últimas líneas: " . mb_substr(trim($output), -300);
                $this->error("  [{$step['key']}]: FATAL sin rollback — el deploy se marcará failed.");
            }
        }

        if ($deployFailed) {
            $log->update([
                'status'           => 'failed',
                'error_message'    => $failureMessage,
                'finished_at'      => now(),
                'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
            ]);

            $this->error("Deploy remoto terminó en FAILED (migración falló, código NO revertido).");
            return 1;
        }

        $log->update([
            'status'           => 'success',
            'finished_at'      => now(),
            'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
        ]);

        $this->info("Deploy remoto completado.");
        return 0;
    }

    /**
     * Rollback de código al commit anterior cuando un paso crítico falla.
     * La BD NO se restaura automáticamente — el respaldo queda disponible para restauración manual.
     */
    private function performRollback(DeploymentLog $log, array $failedStep, int $exitCode, ?string $previousCommit): void
    {
        $errorMsg = "Paso crítico «{$failedStep['name']}» falló (exit {$exitCode}). ";

        if ($previousCommit) {
            $this->warn("  Iniciando rollback de código a {$previousCommit}…");
            [$rbCode, $rbOutput] = $this->runShell("git reset --hard {$previousCommit}", 60);

            if ($rbCode === 0) {
                // Warm-up mínimo tras revertir el código
                $this->runArtisan('optimize');
                $errorMsg .= "Código revertido a {$previousCommit}. ";
            } else {
                $errorMsg .= "ROLLBACK DE CÓDIGO FALLÓ: {$rbOutput}. ";
            }
        }

        $errorMsg .= "El respaldo de BD está disponible para restauración manual si es necesario.";

        $log->update([
            'status'           => 'rolled_back',
            'error_message'    => $errorMsg,
            'finished_at'      => now(),
            'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
        ]);

        $this->error("  {$errorMsg}");
    }

    private function getCurrentHead(): ?string
    {
        try {
            $process = Process::fromShellCommandline('git rev-parse HEAD', base_path(), $this->buildEnv());
            $process->run();
            $head = trim($process->getOutput());
            return $head ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildEnv(): array
    {
        $parent = getenv() ?: [];

        // HOME real del usuario que corre el deploy (www-data en prod, NO /root): git lo
        // necesita para la llave SSH (~/.ssh) al hacer fetch, y npm/composer para su caché.
        // php-fpm a veces NO exporta HOME → lo resolvemos por posix como respaldo.
        $home = $parent['HOME'] ?? '';
        if ($home === '' && function_exists('posix_getpwuid')) {
            $home = posix_getpwuid(posix_getuid())['dir'] ?? '';
        }

        return array_merge($parent, [
            'PATH'               => ($parent['PATH'] ?? '') . ':/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => $home ?: '/root',
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
        ]);
    }

    protected function runShell(string $command, int $timeout): array
    {
        $output    = '';
        $startedAt = microtime(true);
        try {
            $process = Process::fromShellCommandline($command, base_path(), $this->buildEnv(), null, $timeout);
            // OJO: el callback DEBE capturar $output por REFERENCIA. Una arrow fn (`fn(...) => $output .= $b`)
            // captura por VALOR → nunca acumulaba y el output de todo paso shell salía vacío (bug latente).
            // La verificación M1 (migrate:status) y la limpieza M2 (pgrep) dependen de leer este output.
            $process->run(function ($type, $buffer) use (&$output) {
                $output .= $buffer;
            });
            $exitCode = $process->getExitCode() ?? 0;
            if ($exitCode !== 0 && empty(trim($output))) {
                $output = sprintf('[exit %d] Sin output — PATH=%s', $exitCode, $this->buildEnv()['PATH'] ?? 'N/A');
            }
            return [$exitCode, $output, (int) ((microtime(true) - $startedAt) * 1000)];
        } catch (\Throwable $e) {
            return [1, get_class($e) . ': ' . $e->getMessage(), (int) ((microtime(true) - $startedAt) * 1000)];
        }
    }

    protected function runArtisan(string $command, array $params = []): array
    {
        $startedAt = microtime(true);
        try {
            $exitCode = Artisan::call($command, $params);
            return [$exitCode, Artisan::output(), (int) ((microtime(true) - $startedAt) * 1000)];
        } catch (\Throwable $e) {
            return [1, $e->getMessage(), (int) ((microtime(true) - $startedAt) * 1000)];
        }
    }

    /**
     * M1 — Cuenta las migraciones pendientes vía `migrate:status`. Devuelve -1 si NO se pudo verificar
     * (el comando falló) para que el caller sea conservador y no dé por bueno un migrate incierto.
     * En la salida de `migrate:status` cada fila termina en "Ran" (con su lote) o "Pending".
     */
    private function pendingMigrationsCount(): int
    {
        [$code, $output] = $this->runShell('php artisan migrate:status --no-ansi', 120);
        if ($code !== 0) {
            return -1;
        }
        return (int) preg_match_all('/\bPending\b/', $output);
    }

    /**
     * M3 — ¿Se puede omitir `npm ci && npm run prod`? Sí cuando entre el commit anterior y el actual
     * NO cambió nada que afecte al build (package.json, package-lock.json, webpack.mix.js, resources/)
     * y ya existen assets compilados en el servidor. Falla-seguro: ante cualquier duda, compila.
     */
    private function canSkipNpmBuild(?string $previousCommit): bool
    {
        if (!$previousCommit) {
            return false;
        }
        // Sin manifest previo no hay assets que conservar → hay que compilar.
        if (!is_file(base_path('public/mix-manifest.json'))) {
            return false;
        }
        // git diff --quiet: exit 0 = sin cambios (omitible) · exit 1 = hubo cambios · otro = error → no omitir.
        [$code] = $this->runShell(
            "git diff --quiet {$previousCommit} HEAD -- package.json package-lock.json webpack.mix.js resources/",
            60
        );
        return $code === 0;
    }

    /**
     * M2 — Mata procesos huérfanos de deploys anteriores (migrate/npm colgados) que podrían quedar con
     * stdout abierto y colgar/contaminar este deploy. Solo toca procesos cuyo patrón de comando es
     * EXCLUSIVO de un deploy; nunca procesos legítimos. El primer carácter de cada patrón va en clase de
     * regex (`[p]hp`) para que ni el propio `pgrep` ni su shell se auto-emparejen. Se corre ANTES del
     * primer paso: en este punto el deploy actual aún no lanzó su migrate/npm → toda coincidencia es huérfana.
     *
     * @return array<int,string> descripciones "pid (patrón)" de lo que se terminó
     */
    private function killOrphanDeployProcesses(): array
    {
        $self     = getmypid();
        $patterns = [
            '[p]hp artisan migrate --force',
            '[p]hp artisan deploy:dry-run-migrations',
            '[n]pm ci',
            '[n]pm run prod',
        ];

        $killed = [];
        foreach ($patterns as $pattern) {
            [, $out] = $this->runShell('pgrep -f ' . escapeshellarg($pattern) . ' 2>/dev/null || true', 15);
            foreach (preg_split('/\s+/', trim($out)) as $pid) {
                if ($pid === '' || !ctype_digit($pid) || (int) $pid === $self) {
                    continue;
                }
                // TERM primero; si sigue vivo, KILL. Silencioso: el proceso pudo morir entre el pgrep y el kill.
                $this->runShell('kill -TERM ' . (int) $pid . ' 2>/dev/null; sleep 1; kill -KILL ' . (int) $pid . ' 2>/dev/null; true', 10);
                $killed[] = "{$pid} ({$pattern})";
            }
        }

        return $killed;
    }

    private function saveRelease(string $version, string $title, string $summary, string $releaseDate): string
    {
        if (!$version) return 'Sin versión especificada — release omitida.';

        $existing = Release::where('version', $version)->first();
        if (!$existing) {
            Release::create([
                'version'      => $version,
                'title'        => $title,
                'summary'      => $summary ?: null,
                'release_date' => $releaseDate,
                'created_by'   => 1,
            ]);
            // La versión instalada cambió → invalida el cache que lee el badge del topbar.
            Cache::forget('megaisp_installed_version');
            return "Release {$version} creada en DB local.";
        }
        return "Release {$version} ya existía — sin cambios.";
    }
}
