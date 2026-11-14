<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Controllers\Concerns\RespondeRetirada;
use App\Modules\Addons\Marketing\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Campañas «legado». Fase 1 (F1-3, decisión de Henry 2026-11-13): todo menos `index` quedó RETIRADO
 * porque el modelo legado escribe columnas que `marketing_campaigns` no tiene y usa tablas que no
 * existen (`campaign_contents`, `campaign_schedules`): respondía 500. Las rutas siguen registradas; cada
 * método conserva su validación y su findOrFail con scope de empresa (id de otra marca → 404) y después
 * responde 410. Las campañas se rehacen en la Fase 2. Ver docs/marketing/fase-1-paquetes.md §2.5.
 */
class CampaignController extends Controller
{
    use RespondeRetirada;

    public function index(): View
    {
        return view('addon-marketing::index');
    }

    /** Navegación: la ficha legado ya no existe → regresa al panel (404 si la campaña es de otra marca). */
    public function show(int $id): RedirectResponse
    {
        Campaign::findOrFail($id);

        return redirect('/marketing');
    }

    public function table(Request $request): JsonResponse
    {
        return $this->retirada();
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'target_zone'    => 'nullable|string|max:255',
            'target_plan_id' => 'nullable|integer',
            'channel'        => 'required|array|min:1',
            'channel.*'      => 'in:whatsapp,facebook,instagram',
            'daily_limit'    => 'integer|min:1|max:500',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
        ]);

        return $this->retirada();
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Campaign::findOrFail($id);

        return $this->retirada();
    }

    public function destroy(int $id): JsonResponse
    {
        Campaign::findOrFail($id);

        return $this->retirada();
    }

    public function approve(int $id): JsonResponse
    {
        Campaign::findOrFail($id);

        return $this->retirada();
    }

    public function pause(int $id): JsonResponse
    {
        Campaign::findOrFail($id);

        return $this->retirada();
    }

    public function activate(int $id): JsonResponse
    {
        Campaign::findOrFail($id);

        return $this->retirada();
    }
}
