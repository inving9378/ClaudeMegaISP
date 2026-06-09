<?php

namespace App\Services\Deploy;

use App\Models\DeploymentLog;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DeploymentService
{
    public function run(DeploymentLog $log, string $version, string $title = ''): void
    {
        $commitMessage = trim("release: {$version}" . ($title ? " — {$title}" : ''));

        $configSteps = collect(config('deployment.steps'))
            ->filter(fn($s) => $s['enabled'] ?? true)
            ->map(fn($s) => array_merge($s, [
                'name'    => $this->interpolate($s['name'],    $version, $title, $commitMessage),
                'command' => $this->interpolate($s['command'], $version, $title, $commitMessage),
            ]))
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
            // Caso especial: si el tag ya existe localmente, skip el paso git_tag
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

            [$exitCode, $output, $durationMs] = $this->executeStep($step);

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
        $process = Process::fromShellCommandline("git tag -l {$version}", base_path());
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
        $gitConfig = config('deployment.git', []);

        return [
            'PATH'               => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => base_path(),
            'COMPOSER_HOME'      => sys_get_temp_dir() . '/composer',
            // Identidad git para el commit automático
            'GIT_AUTHOR_NAME'    => $gitConfig['author_name']  ?? 'MegaISP Release',
            'GIT_AUTHOR_EMAIL'   => $gitConfig['author_email'] ?? 'releases@meganet.com',
            'GIT_COMMITTER_NAME' => $gitConfig['author_name']  ?? 'MegaISP Release',
            'GIT_COMMITTER_EMAIL'=> $gitConfig['author_email'] ?? 'releases@meganet.com',
        ];
    }
}
