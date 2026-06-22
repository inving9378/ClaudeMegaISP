<?php

namespace App\Http\Controllers;

use App\Jobs\SelfUpdateJob;
use App\Models\DeploymentLog;
use App\Services\Updates\GitHubUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller
{
    public function __construct(private GitHubUpdateService $github)
    {
    }

    /**
     * Devuelve el estado de actualización cacheado (para el banner en el dashboard).
     * No requiere permiso especial: cualquier usuario autenticado puede consultarlo.
     */
    public function status(): JsonResponse
    {
        return response()->json($this->buildStatusPayload($this->github->check()));
    }

    /**
     * Fuerza un chequeo on-demand contra GitHub (botón "Buscar actualizaciones").
     * En el publicador (updates.enabled=false) no actúa. Mismo gate que status() (web+auth).
     */
    public function check(): JsonResponse
    {
        if (!config('updates.enabled')) {
            return response()->json($this->buildStatusPayload(null));
        }

        $this->github->refresh();            // Cache::forget + refetch + Cache::put

        return response()->json($this->buildStatusPayload($this->github->check()));
    }

    /**
     * Shape único que consume el banner: si !enabled, todo queda en false/null.
     * - can_check / show_check_button: gobiernan la visibilidad del botón en el front.
     */
    private function buildStatusPayload(?array $result): array
    {
        $enabled  = (bool) config('updates.enabled');
        $canCheck = auth()->check() && auth()->user()->can('updates.apply');

        return [
            'enabled'           => $enabled,
            'update_available'  => $enabled && $result !== null,
            'release'           => $enabled ? $result : null,
            'can_check'         => $canCheck,
            'show_check_button' => $enabled && $canCheck && (bool) config('updates.manual_check_button', true),
        ];
    }

    /**
     * Dispara la actualización de la instancia al último tag de GitHub.
     * Requiere permiso updates.apply.
     */
    public function apply(Request $request): JsonResponse
    {
        if (!config('updates.enabled')) {
            return response()->json(['success' => false, 'message' => 'Auto-actualización no habilitada en esta instancia.'], 422);
        }

        if (!auth()->user()->can('updates.apply')) {
            return response()->json(['success' => false, 'message' => 'Sin permiso para aplicar actualizaciones.'], 403);
        }

        $result = $this->github->check();
        if (!$result) {
            return response()->json(['success' => false, 'message' => 'No hay actualización disponible o no se pudo consultar GitHub.'], 422);
        }

        $log = DeploymentLog::create([
            'release_id'   => null,
            'triggered_by' => auth()->id(),
            'status'       => 'pending',
            'steps'        => [],
            'payload'      => [
                'version'      => $result['tag'],
                'title'        => $result['name'] ?? $result['tag'],
                'summary'      => $result['body'] ?? '',
                'release_date' => now()->toDateString(),
                'source'       => 'github_banner',
            ],
        ]);

        SelfUpdateJob::dispatch($log)->onConnection('database')->onQueue('deploy');

        Log::info("UpdateController: auto-actualización a {$result['tag']} disparada por user " . auth()->id() . " (log #{$log->id})");

        return response()->json([
            'success'       => true,
            'deployment_id' => $log->id,
            'version'       => $result['tag'],
            'message'       => "Actualización a {$result['tag']} iniciada.",
        ]);
    }
}
