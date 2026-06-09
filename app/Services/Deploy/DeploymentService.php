<?php

namespace App\Services\Deploy;

use App\Models\DeploymentLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DeploymentService
{
    public function run(DeploymentLog $log, string $version, string $title = ''): void
    {
        $commitMessage = trim("release: {$version}" . ($title ? " — {$title}" : ''));

        $configSteps = collect(config('deployment.steps'))
            ->filter(fn($s) => $s['enabled'] ?? true)
            ->map(function ($s) use ($version, $title, $commitMessage) {
                $s['name'] = $this->interpolate($s['name'], $version, $title, $commitMessage);
                if (isset($s['command'])) {
                    $s['command'] = $this->interpolate($s['command'], $version, $title, $commitMessage);
                }
                return $s;
            })
            ->values()
            ->all();

        $initialSteps = array_map(fn($s) => [
            'key'         => $s['key'],
            'name'        => $s['name'],
            'status'      => 'pending',
            'output'      => '',
            'exit_code'   => null,
            'duration_ms' => 0,
            'ran_at'      => null,
        ], $configSteps);

        $log->update([
            'status'     => 'running',
            'steps'      => $initialSteps,
            'started_at' => now(),
        ]);

        foreach ($configSteps as $step) {
            // Skip si DEPLOY_REMOTE_URL no está definido
            if (($step['skip_if_no_remote'] ?? false) && empty(config('deployment.remote_url'))) {
                $log->updateStep($step['key'], [
                    'status'      => 'skipped',
                    'output'      => 'DEPLOY_REMOTE_URL no configurado — paso omitido.',
                    'exit_code'   => 0,
                    'duration_ms' => 0,
                    'ran_at'      => now()->toIso8601String(),
                ]);
                continue;
            }

            // Skip si el tag ya existe localmente
            if (($step['skip_if_tag_exists'] ?? false) && $this->tagExists($version)) {
                $log->updateStep($step['key'], [
                    'status'      => 'skipped',
                    'output'      => "Tag {$version} ya existe localmente.",
                    'exit_code'   => 0,
                    'duration_ms' => 0,
                    'ran_at'      => now()->toIso8601String(),
                ]);
                continue;
            }

            $log->updateStep($step['key'], [
                'status' => 'running',
                'ran_at' => now()->toIso8601String(),
            ]);

            [$exitCode, $output, $durationMs] = ($step['type'] ?? 'shell') === 'http'
                ? $this->executeHttpDeploy($step, $version, $title, $log)
                : $this->executeStep($step);

            // Caso especial: git commit con "nothing to commit" (exit 1) → skip, no error
            if ($exitCode === 1 && ($step['skip_on_nothing_to_commit'] ?? false)) {
                if (str_contains($output, 'nothing to commit') || str_contains($output, 'nothing added')) {
                    $log->updateStep($step['key'], [
                        'status'      => 'skipped',
                        'output'      => 'Sin cambios pendientes — commit omitido.',
                        'exit_code'   => 0,
                        'duration_ms' => $durationMs,
                    ]);
                    Log::channel('single')->info("Deploy #{$log->id} — paso '{$step['key']}': SKIPPED (nothing to commit)");
                    continue;
                }
            }

            $success = $exitCode === 0;

            $log->updateStep($step['key'], [
                'status'      => $success ? 'success' : 'failed',
                'output'      => mb_substr(trim($output), -4000),
                'exit_code'   => $exitCode,
                'duration_ms' => $durationMs,
            ]);

            Log::channel('single')->info("Deploy #{$log->id} — '{$step['key']}': " . ($success ? 'OK' : "FAILED (exit {$exitCode})"));

            if (!$success && ($step['critical'] ?? true)) {
                $log->update([
                    'status'           => 'failed',
                    'error_message'    => "Paso «{$step['name']}» falló con código {$exitCode}.",
                    'finished_at'      => now(),
                    'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
                ]);
                return;
            }
        }

        $log->update([
            'status'           => 'success',
            'finished_at'      => now(),
            'duration_seconds' => (int) now()->diffInSeconds($log->started_at),
        ]);
    }

    private function executeHttpDeploy(array $step, string $version, string $title, DeploymentLog $log): array
    {
        $remoteUrl = rtrim(config('deployment.remote_url'), '/');
        $secret    = config('deployment.webhook_secret', '');
        $startedAt = microtime(true);

        $release = $log->release;
        $payload = [
            'version'      => $version,
            'title'        => $title,
            'summary'      => $release?->summary,
            'release_date' => $release?->release_date,
        ];

        // — Paso 1: disparar el deploy remoto —
        try {
            $trigger = Http::timeout(30)
                ->withHeader('X-Deploy-Token', $secret)
                ->post("{$remoteUrl}/api/webhook/deploy", $payload);
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            return [1, 'Error de conexión con servidor remoto: ' . $e->getMessage(), $durationMs];
        }

        if (!$trigger->successful()) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            return [1, "Webhook rechazado ({$trigger->status()}): " . $trigger->body(), $durationMs];
        }

        $remoteLogId = $trigger->json('log_id');
        if (!$remoteLogId) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            return [1, 'El servidor remoto no devolvió log_id. Respuesta: ' . $trigger->body(), $durationMs];
        }

        // — Paso 2: polling hasta que el deploy remoto termine —
        $maxWait    = max(60, ($step['timeout'] ?? 700) - 30);
        $deadline   = microtime(true) + $maxWait;
        $lastOutput = "Deploy remoto iniciado (log #{$remoteLogId}). Esperando resultados...";
        $pollUrl    = "{$remoteUrl}/api/webhook/deploy/{$remoteLogId}/status";

        while (microtime(true) < $deadline) {
            sleep(5);

            try {
                $poll = Http::timeout(15)
                    ->withHeader('X-Deploy-Token', $secret)
                    ->get($pollUrl);

                if (!$poll->successful()) continue;

                $data         = $poll->json();
                $remoteStatus = $data['status'] ?? 'unknown';
                $lastOutput   = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                // Actualizar output del paso en el log local (visible en el modal)
                $log->updateStep('remote_deploy', ['output' => $lastOutput]);

                if (in_array($remoteStatus, ['success', 'failed'])) {
                    $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                    return [$remoteStatus === 'success' ? 0 : 1, $lastOutput, $durationMs];
                }
            } catch (\Throwable $e) {
                $lastOutput .= "\n[poll error: {$e->getMessage()}]";
            }
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        return [1, "Timeout ({$maxWait}s) esperando al servidor remoto.\n{$lastOutput}", $durationMs];
    }

    private function interpolate(string $text, string $version, string $title, string $message): string
    {
        return str_replace(
            ['{version}', '{title}', '{message}'],
            [$version,    $title,    $message],
            $text
        );
    }

    private function tagExists(string $version): bool
    {
        $process = Process::fromShellCommandline("git tag -l {$version}", base_path(), $this->buildEnv());
        $process->run();
        return trim($process->getOutput()) === $version;
    }

    private function executeStep(array $step): array
    {
        $output    = '';
        $exitCode  = 0;
        $startedAt = microtime(true);

        try {
            $process = Process::fromShellCommandline(
                $step['command'],
                base_path(),
                $this->buildEnv(),
                null,
                $step['timeout'] ?? 60
            );

            $process->run(function ($type, $buffer) use (&$output) {
                $output .= $buffer;
            });

            $exitCode = $process->getExitCode() ?? 0;
        } catch (\Throwable $e) {
            $exitCode = 1;
            $output   = $e->getMessage();
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        return [$exitCode, $output, $durationMs];
    }

    private function buildEnv(): array
    {
        $gitConfig  = config('deployment.git', []);
        $passphrase = env('SSH_KEY_PASSPHRASE', '');

        $env = [
            'PATH'               => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => '/root',
            'COMPOSER_HOME'      => sys_get_temp_dir() . '/composer',
            // Permite que el proceso PHP opere git aunque el dueño del directorio sea distinto
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
            // Identidad git para el commit automático
            'GIT_AUTHOR_NAME'    => $gitConfig['author_name']  ?? 'MegaISP Release',
            'GIT_AUTHOR_EMAIL'   => $gitConfig['author_email'] ?? 'releases@meganet.com',
            'GIT_COMMITTER_NAME' => $gitConfig['author_name']  ?? 'MegaISP Release',
            'GIT_COMMITTER_EMAIL'=> $gitConfig['author_email'] ?? 'releases@meganet.com',
        ];

        // Si la llave SSH tiene passphrase, crea un script SSH_ASKPASS temporal
        // para que SSH lo use en lugar de pedir input interactivo
        if (!empty($passphrase)) {
            $askPassScript = sys_get_temp_dir() . '/ssh_askpass_deploy.sh';
            file_put_contents($askPassScript, "#!/bin/sh\necho " . escapeshellarg($passphrase) . "\n");
            chmod($askPassScript, 0700);

            $env['SSH_ASKPASS']         = $askPassScript;
            $env['SSH_ASKPASS_REQUIRE'] = 'force';
            $env['DISPLAY']             = '';
        }

        return $env;
    }
}
