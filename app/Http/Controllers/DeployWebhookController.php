<?php

namespace App\Http\Controllers;

use App\Models\DeploymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployWebhookController extends Controller
{
    /**
     * Recibe la llamada del servidor local tras hacer git push.
     *
     * NO ejecuta el deploy aquí: solo registra un DeploymentLog en estado `pending`
     * con el payload (version/title/summary) y devuelve el log_id de inmediato.
     * El comando programado `remote:deploy-run-pending` (Kernel, cada minuto) detecta
     * los logs pendientes y corre el deploy real. El local hace polling a /status.
     *
     * Este desacople evita por completo ejecutar trabajo después de cerrar la respuesta
     * HTTP (fastcgi_finish_request / app()->terminating() / shell_exec), que en este host
     * nunca funcionó. El trigger confiable es el cron por minuto, ya operativo.
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
            'payload'      => [
                'version'      => $request->input('version', ''),
                'title'        => $request->input('title', ''),
                'summary'      => $request->input('summary', ''),
                'release_date' => $request->input('release_date', now()->toDateString()),
            ],
        ]);

        Log::info("DeployWebhook: deploy encolado — log #{$log->id}, version={$log->payload['version']}");

        return response()->json(['dispatched' => true, 'log_id' => $log->id]);
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
