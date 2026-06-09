<?php

namespace App\Http\Controllers;

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

        // Limpiar cache de artisan para que detecte nuevos comandos tras git pull
        @shell_exec(PHP_BINARY . ' ' . escapeshellarg(base_path('artisan')) . ' optimize:clear 2>/dev/null');

        // Log en /tmp para evitar problemas de permisos con www-data
        $logFile = sys_get_temp_dir() . "/remote-deploy-{$log->id}.log";

        $cmd = sprintf(
            'nohup %s %s remote:deploy %d --version=%s --title=%s --summary=%s --release-date=%s > %s 2>&1 &',
            PHP_BINARY,
            escapeshellarg(base_path('artisan')),
            $log->id,
            escapeshellarg($version),
            escapeshellarg($title),
            escapeshellarg($summary),
            escapeshellarg($releaseDate),
            escapeshellarg($logFile)
        );

        shell_exec($cmd);

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
