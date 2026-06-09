<?php

namespace App\Console\Commands\Active;

use App\Models\DeploymentLog;
use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
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

    protected $description = 'Ejecuta el deploy en el servidor remoto (git pull, npm, migrate, optimize) actualizando el log.';

    public function handle(): int
    {
        $log = DeploymentLog::find($this->argument('logId'));
        if (!$log) {
            $this->error("DeploymentLog #{$this->argument('logId')} no encontrado.");
            return 1;
        }

        $version     = $this->option('app-version') ?? '';
        $title       = $this->option('title') ?? '';
        $summary     = $this->option('summary') ?? '';
        $releaseDate = $this->option('release-date') ?? now()->toDateString();

        $stepDefs = [
            // fetch + reset --hard en vez de pull: el remoto compila assets (npm run prod) que
            // ensucian archivos trackeados (public/js, mix-manifest) y romperían un merge. El
            // reset descarta esos cambios locales y sincroniza exacto con origin/main. Solo toca
            // archivos TRACKEADOS — .env, storage y subidas (untracked/gitignored) quedan intactos.
            ['key' => 'git_pull',      'name' => 'Sincronizar con origin/main (fetch + reset)', 'type' => 'shell', 'cmd' => 'git fetch origin main && git reset --hard origin/main', 'timeout' => 120, 'critical' => true],
            ['key' => 'npm_build',     'name' => 'Compilar assets (npm run prod)',  'type' => 'shell',   'cmd' => 'npm run prod',         'timeout' => 600, 'critical' => false],
            ['key' => 'migrate',       'name' => 'Ejecutar migraciones',            'type' => 'artisan', 'cmd' => 'migrate',              'timeout' => 120, 'critical' => false, 'params' => ['--force' => true]],
            ['key' => 'optimize',      'name' => 'Optimizar cachés',                'type' => 'artisan', 'cmd' => 'optimize',             'timeout' => 30,  'critical' => false],
            ['key' => 'queue_restart', 'name' => 'Reiniciar workers',               'type' => 'artisan', 'cmd' => 'queue:restart',        'timeout' => 10,  'critical' => false],
            ['key' => 'save_release',  'name' => 'Guardar release en DB remota',    'type' => 'inline',  'critical' => false],
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

        foreach ($stepDefs as $step) {
            $log->updateStep($step['key'], ['status' => 'running', 'ran_at' => now()->toIso8601String()]);

            if ($step['type'] === 'inline') {
                $msg = $this->saveRelease($version, $title, $summary, $releaseDate);
                $log->updateStep($step['key'], ['status' => 'success', 'output' => $msg, 'exit_code' => 0, 'duration_ms' => 0]);
                $this->line("  [save_release]: OK — {$msg}");
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
                $log->update([
                    'status'        => 'failed',
                    'error_message' => "Paso «{$step['name']}» falló con código {$exitCode}.",
                    'finished_at'   => now(),
                    'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
                ]);
                return 1;
            }
        }

        $log->update([
            'status'           => 'success',
            'finished_at'      => now(),
            'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
        ]);

        $this->info("Deploy remoto completado.");
        return 0;
    }

    private function buildEnv(): array
    {
        $parent = getenv() ?: [];
        return array_merge($parent, [
            'PATH'               => ($parent['PATH'] ?? '') . ':/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => '/root',
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
        ]);
    }

    private function runShell(string $command, int $timeout): array
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

    private function runArtisan(string $command, array $params = []): array
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
            return "Release {$version} creada en DB remota.";
        }
        return "Release {$version} ya existía — sin cambios.";
    }
}
