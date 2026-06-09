<?php

namespace App\Http\Controllers;

use App\Jobs\RemoteDeployJob;
use App\Models\DeploymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployWebhookController extends Controller
{
    /**
     * Recibe la llamada del servidor local tras hacer git push.
     * Despacha el deploy como proceso en background y devuelve el log_id para polling.
     */
    public function handle(Request $request)
    {
        $secret = config('deployment.webhook_secret', '');

        if (empty($secret) || $request->header('X-Deploy-Token') !== $secret) {
            Log::warning('DeployWebhook: token inválido desde ' . $request->ip());
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        $log = DeploymentLog::create([
            'release_id'   => null,
            'triggered_by' => 1,
            'status'       => 'pending',
            'steps'        => [],
        ]);

        $version     = $request->input('version', '');
        $title       = $request->input('title', '');
        $summary     = $request->input('summary', '');
        $releaseDate = $request->input('release_date', now()->toDateString());

        // Usar queue si está disponible (remote tiene supervisor workers con QUEUE_CONNECTION=database)
        // Fallback a nohup para entornos con queue=sync (local dev)
        if (config('queue.default') !== 'sync') {
            RemoteDeployJob::dispatch($log->id, $version, $title, $summary, $releaseDate)
                ->onConnection(config('queue.default'))
                ->onQueue('default');
        } else {
            // PHP_BINARY en FPM apunta al daemon (/usr/sbin/php-fpm8.2), no al CLI.
            // Detectar el intérprete CLI real.
            $phpBin = PHP_BINARY;
            if (str_contains($phpBin, 'fpm') || str_contains($phpBin, 'cgi') || !is_executable($phpBin)) {
                $v = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
                foreach (["/usr/bin/php{$v}", '/usr/bin/php', 'php'] as $candidate) {
                    if (@is_executable($candidate)) { $phpBin = $candidate; break; }
                }
            }

            $logFile = sys_get_temp_dir() . "/remote-deploy-{$log->id}.log";
            $cmd = sprintf(
                'nohup %s %s remote:deploy %d --version=%s --title=%s --summary=%s --release-date=%s > %s 2>&1 &',
                $phpBin,
                escapeshellarg(base_path('artisan')),
                $log->id,
                escapeshellarg($version),
                escapeshellarg($title),
                escapeshellarg($summary),
                escapeshellarg($releaseDate),
                escapeshellarg($logFile)
            );
            shell_exec($cmd);
        }

        Log::info("DeployWebhook: deploy iniciado — log #{$log->id}, version={$version}");

        return response()->json([
            'dispatched' => true,
            'log_id'     => $log->id,
        ]);
    }

    /**
     * Devuelve el estado actual del deploy remoto para polling.
     */
    public function status(int $id, Request $request)
    {
        $secret = config('deployment.webhook_secret', '');

        if (empty($secret) || $request->header('X-Deploy-Token') !== $secret) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $log = DeploymentLog::find($id);
        if (!$log) {
            return response()->json(['message' => 'Log no encontrado.'], 404);
        }

        return response()->json([
            'status'           => $log->status,
            'steps'            => $log->steps ?? [],
            'error_message'    => $log->error_message,
            'duration_seconds' => $log->duration_seconds,
            'started_at'       => $log->started_at,
            'finished_at'      => $log->finished_at,
        ]);
    }
}
