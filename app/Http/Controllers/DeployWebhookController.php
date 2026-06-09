<?php

namespace App\Http\Controllers;

use App\Models\DeploymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

        // Ejecutar el deploy DESPUÉS de enviar la respuesta HTTP al cliente.
        // app()->terminating() corre tras fastcgi_finish_request() en PHP-FPM,
        // por lo que el request se cierra inmediatamente sin bloquear nginx.
        // Esto evita depender de queue workers, shell_exec o PHP_BINARY.
        $logId       = $log->id;
        $deployArgs  = [
            'logId'          => $logId,
            '--version'      => $version,
            '--title'        => $title,
            '--summary'      => $summary,
            '--release-date' => $releaseDate,
        ];

        app()->terminating(function () use ($deployArgs) {
            Artisan::call('remote:deploy', $deployArgs);
        });

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
