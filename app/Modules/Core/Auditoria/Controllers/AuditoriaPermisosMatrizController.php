<?php

namespace App\Modules\Core\Auditoria\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Auditoria\Services\PermisosMatrizRolesService;
use Illuminate\Http\JsonResponse;

/**
 * Vista de solo lectura de la matriz de permisos por rol — asignados vs. efectivos
 * (Fase 4c del item #9990765, sub-item #9990766). No modifica roles, permisos ni
 * asignaciones; solo lee y muestra lo que ya calcula PermisosMatrizRolesService.
 */
class AuditoriaPermisosMatrizController extends Controller
{
    public function index()
    {
        return view('core-auditoria::auditoria_permisos_matriz.index');
    }

    public function data(PermisosMatrizRolesService $service): JsonResponse
    {
        return response()->json($service->build());
    }
}
