<?php

namespace App\Modules\Core\ModuleManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\ModuleManager\Services\ModuleSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dependencias de sistema de un add-on desde /admin/modules (ver ModuleSystemService).
 *
 * Estas rutas NO están en config/route_permission.php a propósito: corren scripts que
 * pueden escalar a root, así que CheckRoutePermission solo deja pasar a administrador,
 * desarrollador y super-administrador.
 */
class ModuleSystemController extends Controller
{
    public function __construct(private ModuleSystemService $system)
    {
    }

    /** POST /admin/modules/{slug}/system/{action} — instalar o purgar las dependencias solas. */
    public function start(string $slug, string $action): JsonResponse
    {
        return $this->respond(fn () => [
            'success' => true,
            'run'     => $this->system->start($slug, $action),
        ]);
    }

    /** GET /admin/modules/{slug}/system/runs/{run} — estado y salida de la corrida. */
    public function show(string $slug, string $run): JsonResponse
    {
        return $this->respond(fn () => ['success' => true, 'run' => $this->system->state($slug, $run)]);
    }

    /** POST /admin/modules/{slug}/system/runs/{run}/password */
    public function password(Request $request, string $slug, string $run): JsonResponse
    {
        return $this->respond(function () use ($request, $slug, $run) {
            $this->system->submitPassword($slug, $run, (string) $request->input('password', ''));

            return ['success' => true];
        });
    }

    /** POST /admin/modules/{slug}/system/runs/{run}/cancel */
    public function cancel(string $slug, string $run): JsonResponse
    {
        return $this->respond(function () use ($slug, $run) {
            $this->system->cancel($slug, $run);

            return ['success' => true];
        });
    }

    private function respond(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (\LogicException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
