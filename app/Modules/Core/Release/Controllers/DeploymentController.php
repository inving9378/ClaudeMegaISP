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

        DeployJob::dispatch($newLog)->onConnection('database')->onQueue('deploy');

        return response()->json([
            'success'       => true,
            'deployment_id' => $newLog->id,
            'message'       => 'Reintento iniciado.',
        ]);
    }
}
