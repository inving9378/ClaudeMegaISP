<?php

namespace App\Modules\Addons\Planes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Planes\Models\ContractableService;
use App\Modules\Addons\Planes\Services\ServiceCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Item #006 — Motor de servicios contratables.
 * Expone el catálogo unificado (manifiesto + overlay) y su administración.
 */
class CatalogoController extends Controller
{
    public function __construct(private ServiceCatalogService $catalog)
    {
    }

    // GET /planes/catalogo  → página
    public function index(): View
    {
        return view('meganet.module.planes.catalogo');
    }

    // GET /planes/catalogo/data  → JSON consumido por el componente Vue
    public function data(): JsonResponse
    {
        return response()->json([
            'services'   => $this->catalog->catalog(),
            'bundleable' => $this->catalog->bundleable(),
        ]);
    }

    // POST /planes/catalogo/sync
    public function sync(): JsonResponse
    {
        $result = $this->catalog->syncFromManifests();

        return response()->json([
            'success'  => true,
            'result'   => $result,
            'services' => $this->catalog->catalog(),
        ]);
    }

    // POST /planes/catalogo/{key}
    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'price'  => 'nullable|numeric|min:0',
            'label'  => 'nullable|string|max:120',
            'active' => 'nullable|boolean',
        ]);

        $row = ContractableService::where('service_type_key', $key)->firstOrFail();

        if (array_key_exists('price', $data)) {
            if (! $row->price_configurable && $data['price'] !== null) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Este service_type no permite precio configurable.',
                ], 422);
            }
            $row->price = $data['price'];
        }
        if (! empty($data['label'])) {
            $row->label = $data['label'];
        }
        if (array_key_exists('active', $data)) {
            $row->active = (bool) $data['active'];
        }

        $row->save();

        return response()->json(['success' => true, 'service' => $row]);
    }
}
