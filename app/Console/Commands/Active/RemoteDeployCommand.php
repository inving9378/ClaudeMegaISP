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
            //     Timeout alineado con el del migrate real (900s): si el dry-run fuera más corto,
            //     una migración lenta pasaría el dry-run pero timeoutearía el migrate real.
            ['key' => 'migrate_dryrun', 'name' => 'Validar migraciones (dry-run)',     'type' => 'shell', 'cmd' => 'php artisan deploy:dry-run-migrations', 'timeout' => 900, 'critical' => true],
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
            //    código → flag 'fail_deploy_no_rollback'. Timeout 900s (antes 300s: una migración
            //    lenta timeouteaba y marcaba el paso failed, pero el deploy cerraba 'success').
            ['key' => 'migrate',       'name' => 'Ejecutar migraciones',            'type' => 'shell',   'cmd' => 'php artisan migrate --force', 'timeout' => 900, 'critical' => false, 'fail_deploy_no_rollback' => true],
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

            [$exitCode, $output, $ms] = $step['type'] === 'artisan'
                ? $this->runArtisan($step['cmd'], $step['params'] ?? [])
                : $this->runShell($step['cmd'], $step['timeout']);

            $success = $exitCode === 0;
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
            $process->run(fn($t, $b) => $output .= $b);
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
