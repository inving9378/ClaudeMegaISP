<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Requests\CampanasActivasRequest;
use App\Modules\Addons\Marketing\Requests\ResumenDashboardRequest;
use App\Modules\Addons\Marketing\Services\Dashboard\DashboardMarketingService;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard de Marketing (Fase 1). Rutas `$M` (marketing.empresa + rol admin/marketing): la empresa sale
 * SIEMPRE de la empresa activa, nunca del request. Ver docs/marketing/fase-1-paquetes.md §2.4.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMarketingService $servicio,
        private readonly EmpresaActiva $contexto,
    ) {}

    /** GET /api/marketing/dashboard/resumen?mes=AAAA-MM */
    public function resumen(ResumenDashboardRequest $request): JsonResponse
    {
        return response()->json($this->servicio->resumen((int) $this->contexto->id(), $request->mes()));
    }

    /** GET /api/marketing/dashboard/campanas-activas?limite=N */
    public function campanasActivas(CampanasActivasRequest $request): JsonResponse
    {
        return response()->json($this->servicio->campanasActivas((int) $this->contexto->id(), $request->limite()));
    }
}
