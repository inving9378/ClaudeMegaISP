<?php

namespace App\Services\Deploy;

use App\Models\DeploymentLog;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DeploymentService
{
    public function run(DeploymentLog $log): void
    {
        $configSteps = collect(config('deployment.steps'))
            ->filter(fn($s) => $s['enabled'] ?? true)
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
            $log->updateStep($step['key'], [
                'status' => 'running',
                'ran_at' => now()->toIso8601String(),
            ]);

            [$exitCode, $output, $durationMs] = $this->executeStep($step);
            $success = $exitCode === 0;

            $log->updateStep($step['key'], [
                'status'      => $success ? 'success' : 'failed',
                'output'      => mb_substr(trim($output), -4000),
                'exit_code'   => $exitCode,
                'duration_ms' => $durationMs,
            ]);

            Log::channel('single')->info("Deploy #{$log->id} — paso '{$step['key']}': " . ($success ? 'OK' : "FAILED (exit {$exitCode})"));

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
        return [
            'PATH'         => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'         => base_path(),
            'COMPOSER_HOME' => sys_get_temp_dir() . '/composer',
        ];
    }
}
