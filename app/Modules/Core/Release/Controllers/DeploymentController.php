<?php

namespace App\Modules\Core\Release\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\DeployJob;
use App\Models\DeploymentLog;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    /**
     * Estado en vivo de un deploy — usado por el modal de polling.
     */
    public function status(int $id)
    {
        $log = DeploymentLog::findOrFail($id);

        return response()->json([
            'deployment_id'    => $log->id,
            'release_id'       => $log->release_id,
            'status'           => $log->status,
            'steps'            => $log->steps ?? [],
            'started_at'       => $log->started_at?->toIso8601String(),
            'finished_at'      => $log->finished_at?->toIso8601String(),
            'duration_seconds' => $log->duration_seconds,
            'error_message'    => $log->error_message,
        ]);
    }

    /**
     * Tail del log del proceso desprendido del deploy en prod (nohup remote:deploy →
     * storage/logs/self-update-{id}.log). Permite ver el avance EN VIVO desde el navegador
     * (sobre todo durante npm_build, el paso lento) sin entrar a la consola del servidor.
     * Devuelve solo la cola del archivo para no transferir megas en cada poll.
     */
    public function log(int $id)
    {
        $log  = DeploymentLog::findOrFail($id);
        $path = storage_path("logs/self-update-{$id}.log");

        $content = '';
        $exists  = is_file($path);

        if ($exists) {
            $maxBytes = 24576; // últimos ~24KB
            $size     = filesize($path);
            $fh       = fopen($path, 'rb');
            if ($fh !== false) {
                if ($size > $maxBytes) {
                    fseek($fh, -$maxBytes, SEEK_END);
                }
                $content = (string) stream_get_contents($fh);
                fclose($fh);
                // Si truncamos, descartar la primera línea (probablemente parcial).
                if ($size > $maxBytes && ($nl = strpos($content, "\n")) !== false) {
                    $content = substr($content, $nl + 1);
                }
            }
        }

        return response()->json([
            'deployment_id' => $log->id,
            'status'        => $log->status,
            'exists'        => $exists,
            'log'           => $content,
        ]);
    }

    /**
     * Historial paginado de todos los deploys.
     */
    public function index(Request $request)
    {
        $logs = DeploymentLog::with(['release:id,version', 'triggeredBy:id,name'])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($logs);
    }

    /**
     * Reintentar un deploy fallido.
     */
    public function retry(int $id)
    {
        $log = DeploymentLog::findOrFail($id);

        if (!in_array($log->status, ['failed', 'rolled_back'])) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden reintentar deploys fallidos.',
            ], 422);
        }

        $newLog = DeploymentLog::create([
            'release_id'   => $log->release_id,
            'triggered_by' => auth()->id(),
            'status'       => 'pending',
        ]);

        // DeployJob exige (log, version, title): version es obligatoria. Se toma de la
        // release del deploy original; sin ella el pipeline no podría taggear/publicar.
        $release = $log->release;

        DeployJob::dispatch($newLog, $release?->version ?? '', $release?->title ?? '')
            ->onConnection('database')
            ->onQueue('deploy');

        return response()->json([
            'success'       => true,
            'deployment_id' => $newLog->id,
            'message'       => 'Reintento iniciado.',
        ]);
    }
}
