<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedEmpalme;
use App\Modules\Addons\MapaRed\Models\MapaRedHilo;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use Illuminate\Http\Request;

/**
 * MR-12 Fase A (item roadmap #9990501) — panel de unión de hilos. Las 3 reglas duras del DoD
 * #948 (hilo no duplicado en empalme activo, no auto-empalme, contenedor existe) y la pérdida
 * dB por catálogo ya las aplica `MapaRedEmpalme::crear()` — este controller solo valida entrada
 * básica y traduce sus excepciones a 422.
 *
 * LIMITACIÓN CONOCIDA (documentada, no repetir la exploración): `mapared_hilos` no tiene columna
 * de elemento contenedor, solo `cable_id`; hoy no existe tabla/relación que diga qué cables
 * llegan físicamente a un rack (MapaRedDevice) / mufa / NAP (MapaRedLayer) en el sistema nuevo
 * MR-08+. Por eso `disponibles()` recibe el/los cable_id explícitos como parámetro (el frontend
 * los conoce porque el operador los eligió o porque Fase B ya los resuelve) en vez de
 * auto-derivarlos del contenedor. Resolverlo es un item aparte, fuera de alcance de este MVP.
 */
class EmpalmesController extends Controller
{
    /**
     * Hilos libres del/los cable(s) explícitos, y puertos libres del splitter si el extremo B
     * es un puerto (polimórfico, mismo patrón `puertable_type`/`puertable_id` que
     * NapSaludController/NapOcupacionController/EnlacesServicioController).
     */
    public function disponibles(Request $request)
    {
        $data = $request->validate([
            'cable_a_id' => 'required|integer',
            'cable_b_id' => 'nullable|integer',
            'puertable_type' => 'nullable|string',
            'puertable_id' => 'nullable|integer|required_with:puertable_type',
        ]);

        $respuesta = [
            'hilos_a' => $this->hilosLibresDeCable((int) $data['cable_a_id']),
        ];

        if (!empty($data['cable_b_id'])) {
            $respuesta['hilos_b'] = $this->hilosLibresDeCable((int) $data['cable_b_id']);
        }

        if (!empty($data['puertable_type']) && !empty($data['puertable_id'])) {
            $respuesta['puertos_b'] = MapaRedPuerto::query()
                ->delDueno($data['puertable_type'], (int) $data['puertable_id'])
                ->libre()
                ->get();
        }

        return response()->json($respuesta);
    }

    private function hilosLibresDeCable(int $cableId)
    {
        return MapaRedHilo::where('cable_id', $cableId)
            ->where('estado', 'libre')
            ->orderBy('buffer')
            ->orderBy('numero')
            ->get();
    }

    /**
     * Tabla de uniones ya hechas en un elemento contenedor (decisión q3 ya tomada: tabla, no
     * diagrama SVG).
     */
    public function existentes(Request $request)
    {
        $data = $request->validate([
            'elemento_contenedor_type' => 'required|string',
            'elemento_contenedor_id' => 'required|integer',
        ]);

        $empalmes = MapaRedEmpalme::where('elemento_contenedor_type', $data['elemento_contenedor_type'])
            ->where('elemento_contenedor_id', $data['elemento_contenedor_id'])
            ->with(['hiloA', 'extremoB'])
            ->get();

        return response()->json($empalmes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hilo_a_id' => 'required|integer',
            'extremo_b_type' => 'required|string',
            'extremo_b_id' => 'required|integer',
            'elemento_contenedor_type' => 'required|string',
            'elemento_contenedor_id' => 'required|integer',
            'bandeja' => 'nullable|string',
            'posicion' => 'nullable|string',
            'tipo' => 'required|in:' . implode(',', [
                MapaRedEmpalme::TIPO_FUSION,
                MapaRedEmpalme::TIPO_MECANICO,
                MapaRedEmpalme::TIPO_CONECTORIZADO,
            ]),
            'perdida_db' => 'nullable|numeric',
            'fecha' => 'required|date',
            'tecnico_id' => 'nullable|integer',
        ]);

        try {
            $empalme = MapaRedEmpalme::crear($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($empalme, 201);
    }

    /**
     * Borrado lógico (decisión q4 ya tomada: soft-delete + confirm en el frontend, no físico).
     */
    public function destroy($id)
    {
        $empalme = MapaRedEmpalme::findOrFail($id);
        $empalme->delete();

        return response()->json(['deleted' => true]);
    }
}
