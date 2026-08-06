<?php

namespace App\Http\Controllers;

use App\Jobs\SelfUpdateJob;
use App\Models\DeploymentLog;
use App\Services\Deploy\DeploymentLock;
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

        // "No se pudo consultar" NO es "estás al día" (item #529): sin esta distinción, un
        // token vencido o un corte de red se veían idénticos a no tener actualizaciones.
        $checkFailed = $enabled && ($result['check_failed'] ?? false);

        return [
            'enabled'           => $enabled,
            'update_available'  => $enabled && $result !== null && !$checkFailed,
            'release'           => ($enabled && !$checkFailed) ? $result : null,
            'check_failed'      => $checkFailed,
            'check_error'       => $checkFailed ? ($result['error'] ?? null) : null,
            'can_check'         => $canCheck,
            'show_check_button' => $enabled && $canCheck && (bool) config('updates.manual_check_button', true),
            // Deploy en curso (si lo hay) → el banner del dashboard se re-engancha solo:
            // sobrevive a reloads, a cerrar la pestaña y lo ve cualquier admin, sin tener
            // que haber sido quien apretó el botón.
            'active_deployment' => $enabled ? $this->activeDeployment() : null,
        ];
    }

    /**
     * El deploy más reciente que sigue en curso (pending/running), para que el frontend
     * se re-enganche al recargar. Null si no hay ninguno activo.
     */
    private function activeDeployment(): ?array
    {
        // Solo deploys RECIENTES: un deploy real termina en minutos. Un log que lleva
        // horas en «running» es un zombie (proceso muerto sin cerrar el log) → no debe
        // secuestrar el banner con un "aplicando…" perpetuo.
        $log = DeploymentLog::whereIn('status', ['pending', 'running'])
            ->where('created_at', '>=', now()->subHours(2))
            ->orderByDesc('id')
            ->first();

        if (!$log) {
            return null;
        }

        return [
            'id'      => $log->id,
            'version' => $log->payload['version'] ?? null,
            'status'  => $log->status,
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

        // Blindaje: un estado "no se pudo consultar" trae check_failed y NO trae 'tag'.
        // Sin este corte, se dispararía un deploy con versión nula.
        if (($result['check_failed'] ?? false) || empty($result['tag'])) {
            $motivo = $result['error'] ?? 'No hay actualización disponible.';
            return response()->json(['success' => false, 'message' => $motivo], 422);
        }

        // Evita arrancar un 2º deploy si ya hay uno en curso (el comando remote:deploy
        // también lo protege con el mismo lock; esto solo da feedback inmediato al usuario).
        if (DeploymentLock::isLocked()) {
            return response()->json([
                'success'       => false,
                'message'       => 'Ya hay una actualización en curso.',
                'deployment_id' => DeploymentLock::currentId(),
            ], 409);
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

        if (config('queue.default') === 'sync') {
            // Sin worker (prod en sync): el deploy tarda minutos y bloquearía el request.
            // Se lanza como proceso desprendido (nohup) que corre remote:deploy en segundo
            // plano; el lock del propio comando evita solapes. El front sigue el avance por
            // polling de /releases/deployment/{id}/status.
            $artisan = base_path('artisan');
            $logFile = storage_path('logs/self-update-' . $log->id . '.log');
            $cmd = sprintf(
                'nohup %s %s remote:deploy %d --app-version=%s --title=%s --summary=%s --release-date=%s > %s 2>&1 &',
                PHP_BINARY,
                escapeshellarg($artisan),
                $log->id,
                escapeshellarg($result['tag']),
                escapeshellarg($result['name'] ?? $result['tag']),
                escapeshellarg($result['body'] ?? ''),
                escapeshellarg(now()->toDateString()),
                escapeshellarg($logFile)
            );
            shell_exec($cmd);
        } else {
            SelfUpdateJob::dispatch($log)->onConnection('database')->onQueue('deploy');
        }

        Log::info("UpdateController: auto-actualización a {$result['tag']} disparada por user " . auth()->id() . " (log #{$log->id})");

        return response()->json([
            'success'       => true,
            'deployment_id' => $log->id,
            'version'       => $result['tag'],
            'message'       => "Actualización a {$result['tag']} iniciada.",
        ]);
    }
}
