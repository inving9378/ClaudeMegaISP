<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Controllers\Concerns\RespondeRetirada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel de Marketing. `index` sirve la vista del Dashboard.
 *
 * Fase 1 (F1-3): `stats` y las Plantillas legado quedaron RETIRADAS — dependían de `marketing_templates`,
 * tabla que no existe (500). Las rutas siguen registradas y responden 410 (alta de plantilla: primero su
 * validación). El Dashboard nuevo usa `/api/marketing/dashboard/*`. Ver docs/marketing/fase-1-paquetes.md §2.5.
 */
class MarketingController extends Controller
{
    use RespondeRetirada;

    public function index(): View
    {
        return view('addon-marketing::index');
    }

    public function stats(): JsonResponse
    {
        return $this->retirada();
    }

    public function templates(): JsonResponse
    {
        return $this->retirada();
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'channel'       => 'required|string|in:whatsapp,facebook,instagram',
            'template_type' => 'required|string|in:campaign,welcome,followup,closing',
            'system_prompt' => 'nullable|string',
            'base_copy'     => 'required|string',
            'variables'     => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        return $this->retirada();
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        return $this->retirada();
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        return $this->retirada();
    }
}
