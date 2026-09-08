<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedCoverageArea;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * MR-22 Fase 2c-2 (item roadmap #9990540) — CRUD de cobertura DECLARADA/manual (zonas
 * editoriales tipo "aquí sí damos servicio"). NO confundir con MR-26/CoberturaController
 * (cobertura VENDIBLE calculada automáticamente) — conceptos y tablas distintas.
 *
 * Lectura gateada por el permiso de ruta `mapa_red_view` (patrón `/mapa-red/**`, igual que
 * el resto del módulo). Escritura exige ADEMÁS el permiso granular
 * `mapared.cobertura_declarada.manage`, verificado inline (mismo patrón de defensa en
 * profundidad que `OLTsOnuController`, item #287). Sin UI de edición aquí (Fase 2d).
 */
class CoberturaDeclaradaController extends Controller
{
    public function index()
    {
        $areas = MapaRedCoverageArea::query()->activas()->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $areas->map(fn ($a) => $a->toGeoJsonFeature())->values(),
        ]);
    }

    public function show(int $id)
    {
        return response()->json(MapaRedCoverageArea::findOrFail($id));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $this->validated($request);
        $area = MapaRedCoverageArea::create($data);

        return response()->json($area, 201);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeManage();

        $area = MapaRedCoverageArea::findOrFail($id);
        $data = $this->validated($request, sometimes: true);
        $area->update($data);

        return response()->json($area);
    }

    public function destroy(int $id)
    {
        $this->authorizeManage();

        MapaRedCoverageArea::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('mapared.cobertura_declarada.manage'), 403);
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $req = fn (string $rule) => $sometimes ? "sometimes|{$rule}" : $rule;

        return $request->validate([
            'nombre' => $req('required|string|max:255'),
            'tipo_tecnologia' => [$sometimes ? 'sometimes' : 'required', Rule::in(['ftth', 'inalambrico'])],
            'polygon' => $req('required|array|min:3'),
            'polygon.*' => 'array|size:2',
            'polygon.*.*' => 'numeric',
            'activo' => 'boolean',
        ], [
            'polygon.min' => 'El polígono debe tener al menos 3 puntos.',
        ]);
    }
}
