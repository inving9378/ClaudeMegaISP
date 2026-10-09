<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedCable;
use App\Modules\Addons\MapaRed\Models\MapaRedSplitter;
use App\Modules\Addons\MapaRed\Models\MapaRedTipoCable;
use App\Modules\Addons\MapaRed\Models\MapaRedTipoCaja;
use App\Modules\Addons\MapaRed\Models\MapaRedTipoConector;
use App\Modules\Addons\MapaRed\Models\MapaRedTipoSplitter;
use Illuminate\Http\Request;

/**
 * MR-24e Fase 1b (item roadmap #9990558) — catálogos de solo lectura para selects del mapa.
 * GAP detectado en la Fase 1a/investigación previa: MapaRedTipoSplitter (MR-13, #949) no tenía
 * controller/ruta propia (ver comentario de clase). tipoSplitter()/tipoCable() siguen siendo
 * deliberadamente mínimos: solo el listado de opciones para un <q-select>, NO el CRUD.
 *
 * MR-08 Fase 2 (items roadmap #9990536/#9990538) — sumó el CRUD de los 4 catálogos
 * (cables/conectores/cajas/splitters: index/store/update/destroy) sobre el mismo archivo, para
 * la administración de catálogos.
 */
class CatalogosController extends Controller
{
    public function tipoSplitter()
    {
        return response()->json(
            MapaRedTipoSplitter::orderBy('numero_puertos')->get(['id', 'nombre'])
        );
    }

    // MR-24e Fase 3b (item roadmap #9990582) — catálogo mínimo de tipos de cable para el
    // <q-select> del formulario de vista previa (SIN persistencia del cable en sí, ver Fase 4).
    public function tipoCable()
    {
        return response()->json(
            MapaRedTipoCable::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function cables()
    {
        $this->authorize('mapa_red_catalogo_view');

        return response()->json(
            MapaRedTipoCable::orderBy('nombre')->get()
        );
    }

    public function storeCable(Request $request)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'numero_hilos' => 'required|integer|min:1',
            'hilos_por_buffer' => 'nullable|integer|min:1',
            'esquema_color' => 'required|in:eia_tia_598,abnt_nbr_14771',
            'atenuacion_db_km' => 'nullable|array',
            'atenuacion_db_km.*' => 'numeric',
            'precio' => 'nullable|numeric|min:0',
            'unidad_medida' => 'nullable|string|max:50',
        ]);

        $cable = MapaRedTipoCable::create($data);

        return response()->json($cable, 201);
    }

    public function updateCable(Request $request, $id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $cable = MapaRedTipoCable::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'numero_hilos' => 'required|integer|min:1',
            'hilos_por_buffer' => 'nullable|integer|min:1',
            'esquema_color' => 'required|in:eia_tia_598,abnt_nbr_14771',
            'atenuacion_db_km' => 'nullable|array',
            'atenuacion_db_km.*' => 'numeric',
            'precio' => 'nullable|numeric|min:0',
            'unidad_medida' => 'nullable|string|max:50',
        ]);

        $cable->update($data);

        return response()->json($cable);
    }

    public function destroyCable($id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $cable = MapaRedTipoCable::findOrFail($id);

        if (MapaRedCable::where('tipo_cable_id', $id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay cables usando este tipo.',
            ], 422);
        }

        $cable->delete();

        return response()->json(['deleted' => true]);
    }

    public function conectores()
    {
        $this->authorize('mapa_red_catalogo_view');

        return response()->json(
            MapaRedTipoConector::orderBy('nombre')->get()
        );
    }

    public function storeConector(Request $request)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'perdida_db' => 'nullable|numeric|min:0',
        ]);

        $conector = MapaRedTipoConector::create($data);

        return response()->json($conector, 201);
    }

    public function updateConector(Request $request, $id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $conector = MapaRedTipoConector::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'perdida_db' => 'nullable|numeric|min:0',
        ]);

        $conector->update($data);

        return response()->json($conector);
    }

    public function destroyConector($id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $conector = MapaRedTipoConector::findOrFail($id);

        if (MapaRedTipoSplitter::where('tipo_conector_id', $id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay splitters usando este tipo de conector.',
            ], 422);
        }

        $conector->delete();

        return response()->json(['deleted' => true]);
    }

    public function cajas()
    {
        $this->authorize('mapa_red_catalogo_view');

        return response()->json(
            MapaRedTipoCaja::orderBy('nombre')->get()
        );
    }

    public function storeCaja(Request $request)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'capacidad_puertos' => 'nullable|integer|min:1',
            'capacidad_fusiones' => 'nullable|integer|min:1',
            'ip_rating' => 'nullable|string|max:20',
            'ik_rating' => 'nullable|string|max:20',
        ]);

        $caja = MapaRedTipoCaja::create($data);

        return response()->json($caja, 201);
    }

    public function updateCaja(Request $request, $id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $caja = MapaRedTipoCaja::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'capacidad_puertos' => 'nullable|integer|min:1',
            'capacidad_fusiones' => 'nullable|integer|min:1',
            'ip_rating' => 'nullable|string|max:20',
            'ik_rating' => 'nullable|string|max:20',
        ]);

        $caja->update($data);

        return response()->json($caja);
    }

    public function destroyCaja($id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $caja = MapaRedTipoCaja::findOrFail($id);

        // Sin consumidores hoy (grep confirmado) — destroy directo, sin guard de referencias.
        $caja->delete();

        return response()->json(['deleted' => true]);
    }

    public function splitters()
    {
        $this->authorize('mapa_red_catalogo_view');

        return response()->json(
            MapaRedTipoSplitter::orderBy('nombre')->get()
        );
    }

    public function storeSplitter(Request $request)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'balanceado' => 'required|boolean',
            'ratio' => 'nullable|string|max:20',
            'numero_puertos' => 'required|integer|min:1',
            'perdida_db' => 'nullable|numeric|min:0',
            'precio' => 'nullable|numeric|min:0',
            'tipo_conector_id' => 'nullable|integer|exists:mapared_tipo_conector,id',
            'perdida_paso_db' => 'nullable|numeric|min:0',
            'perdida_derivacion_db' => 'nullable|numeric|min:0',
        ]);

        $splitter = MapaRedTipoSplitter::create($data);

        return response()->json($splitter, 201);
    }

    public function updateSplitter(Request $request, $id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $splitter = MapaRedTipoSplitter::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'balanceado' => 'required|boolean',
            'ratio' => 'nullable|string|max:20',
            'numero_puertos' => 'required|integer|min:1',
            'perdida_db' => 'nullable|numeric|min:0',
            'precio' => 'nullable|numeric|min:0',
            'tipo_conector_id' => 'nullable|integer|exists:mapared_tipo_conector,id',
            'perdida_paso_db' => 'nullable|numeric|min:0',
            'perdida_derivacion_db' => 'nullable|numeric|min:0',
        ]);

        $splitter->update($data);

        return response()->json($splitter);
    }

    public function destroySplitter($id)
    {
        $this->authorize('mapa_red_catalogo_manage');

        $splitter = MapaRedTipoSplitter::findOrFail($id);

        if (MapaRedSplitter::where('tipo_splitter_id', $id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay splitters físicos usando este tipo.',
            ], 422);
        }

        $splitter->delete();

        return response()->json(['deleted' => true]);
    }
}
