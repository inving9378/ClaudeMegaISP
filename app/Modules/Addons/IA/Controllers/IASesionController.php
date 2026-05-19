<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IASesionTrabajo;
use App\Modules\Addons\IA\Services\Contexto\SesionTrabajoService;
use Illuminate\Http\Request;

class IASesionController extends Controller
{
    public function __construct(protected SesionTrabajoService $service)
    {
    }

    public function index()
    {
        $sesiones = IASesionTrabajo::where('user_id', auth()->id())
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        return response()->json(['success' => true, 'data' => $sesiones]);
    }

    public function abrir(Request $request)
    {
        $sesion = $this->service->abrirSiHaceFalta(
            auth()->id(),
            $request->input('proveedor')
        );
        return response()->json(['success' => true, 'data' => $sesion]);
    }

    public function cerrar(Request $request, $id)
    {
        $sesion = IASesionTrabajo::where('user_id', auth()->id())->findOrFail($id);
        $sesion = $this->service->cerrar($sesion, $request->input('resumen'));
        return response()->json(['success' => true, 'data' => $sesion]);
    }
}
