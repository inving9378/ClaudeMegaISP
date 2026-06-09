<?php

namespace App\Http\Controllers;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DeployWebhookController extends Controller
{
    /**
     * Recibe la llamada del servidor local tras hacer git push.
     * Ejecuta: git pull → npm build → migrate → optimize → queue:restart → guarda release en DB remota.
     */
    public function handle(Request $request)
    {
        $secret = config('deployment.webhook_secret', '');

        if (empty($secret) || $request->header('X-Deploy-Token') !== $secret) {
            Log::warning('DeployWebhook: token inválido desde ' . $request->ip());
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        $startedAt  = now();
        $steps      = [];
        $npmScript  = 'prod';

        try {
            // 1. git pull
            $steps[] = $this->runShell('git_pull', 'Git pull origin main', 'git pull origin main', 60);

            if (!$steps[0]['success']) {
                return response()->json([
                    'success' => false,
                    'steps'   => $steps,
                    'error'   => 'git pull falló — revisa conflictos en el servidor remoto.',
                ], 500);
            }

            // 2. npm build
            $steps[] = $this->runShell('npm_build', "Compilar assets (npm run {$npmScript})", "npm run {$npmScript}", 600);

            // 3. migrate
            $steps[] = $this->runArtisan('migrate', 'Ejecutar migraciones', 'migrate', ['--force' => true]);

            // 4. optimize
            $steps[] = $this->runArtisan('optimize', 'Optimizar cachés', 'optimize');

            // 5. queue:restart
            $steps[] = $this->runArtisan('queue_restart', 'Reiniciar workers', 'queue:restart');

            // 5. Guardar release en la DB de este servidor
            $version = $request->input('version');
            if ($version) {
                $existing = Release::where('version', $version)->first();
                if (!$existing) {
                    Release::create([
                        'version'      => $version,
                        'title'        => $request->input('title'),
                        'summary'      => $request->input('summary'),
                        'release_date' => $request->input('release_date', now()->toDateString()),
                        'created_by'   => 1,
                    ]);
                    $releaseMsg = "Release {$version} creada en DB remota.";
                } else {
                    $releaseMsg = "Release {$version} ya existía en DB remota — sin cambios.";
                }
                $steps[] = [
                    'key'         => 'save_release',
                    'name'        => 'Guardar release en DB remota',
                    'success'     => true,
                    'output'      => $releaseMsg,
                    'duration_ms' => 0,
                ];
            }

            Log::info('DeployWebhook: deploy completado para ' . ($version ?? 'desconocido') . ' en ' . now()->diffInSeconds($startedAt) . 's');

            return response()->json([
                'success'          => true,
                'steps'            => $steps,
                'duration_seconds' => now()->diffInSeconds($startedAt),
            ]);

        } catch (\Throwable $e) {
            Log::error('DeployWebhook error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'steps'   => $steps,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function buildShellEnv(): array
    {
        // Heredar el env del proceso padre (PHP-FPM) y sobreescribir lo necesario.
        // NO pasar array vacío — Process::fromShellCommandline reemplaza el env completo
        // y entonces PATH desaparece y ningún binario (git/npm) se encuentra.
        $parentEnv = getenv();

        return array_merge($parentEnv, [
            'PATH'               => ($parentEnv['PATH'] ?? '') . ':/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => '/root',
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
        ]);
    }

    private function runShell(string $key, string $name, string $command, int $timeout = 60): array
    {
        $startedAt = microtime(true);
        $output    = '';
        $success   = false;

        try {
            $process = Process::fromShellCommandline($command, base_path(), $this->buildShellEnv(), null, $timeout);
            $process->run(fn($type, $buf) => $output .= $buf);
            $success = $process->isSuccessful();
            if (!$success && empty(trim($output))) {
                $output = sprintf(
                    "[exit %d] Sin output — posible binario no encontrado. PATH=%s",
                    $process->getExitCode() ?? -1,
                    $this->buildShellEnv()['PATH'] ?? 'N/A'
                );
            }
        } catch (\Throwable $e) {
            $output = get_class($e) . ': ' . $e->getMessage();
        }

        return [
            'key'         => $key,
            'name'        => $name,
            'success'     => $success,
            'output'      => mb_substr(trim($output), -2000),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function runArtisan(string $key, string $name, string $command, array $params = []): array
    {
        $startedAt = microtime(true);
        $success   = false;
        $output    = '';

        try {
            $exitCode = Artisan::call($command, $params);
            $output   = Artisan::output();
            $success  = $exitCode === 0;
        } catch (\Throwable $e) {
            $output = $e->getMessage();
        }

        return [
            'key'         => $key,
            'name'        => $name,
            'success'     => $success,
            'output'      => mb_substr(trim($output), -2000),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ];
    }
}
