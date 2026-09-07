<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedEmpalme;
use App\Modules\Addons\MapaRed\Models\MapaRedHilo;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use Barryvdh\DomPDF\Facade\Pdf;
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

    /**
     * MR-19 Fase 1 (item roadmap #9990565) — carta de empalme: mismo query base de
     * existentes(), pero agrupado por bandeja para presentación (tabla web / PDF).
     * Decisiones ya tomadas por Irving: nivel MVP (bandeja→hilo_a→tipo→extremo_b con color,
     * sin loose tube/buffer separado ni reservas/pérdidas medidas) y colores del catálogo
     * tal cual están en BD (mapared_hilos.color/buffer_color), sin catálogo nuevo.
     */
    public function carta(Request $request)
    {
        $data = $request->validate([
            'elemento_contenedor_type' => 'required|string',
            'elemento_contenedor_id' => 'required|integer',
        ]);

        return response()->json(
            $this->construirCarta($data['elemento_contenedor_type'], (int) $data['elemento_contenedor_id'])
        );
    }

    /**
     * Export PDF de la carta de empalme (DomPDF, ya en composer.json — decisión q1 de Irving).
     * Orientación landscape (decisión q4). Ruta por query string, no por segmento de URL:
     * `elemento_contenedor_type` es un FQCN con backslashes, mismo patrón que existentes().
     */
    public function cartaPdf(Request $request)
    {
        $data = $request->validate([
            'elemento_contenedor_type' => 'required|string',
            'elemento_contenedor_id' => 'required|integer',
        ]);

        $tipo = $data['elemento_contenedor_type'];
        $id = (int) $data['elemento_contenedor_id'];

        $contenedor = $tipo::find($id);
        if (!$contenedor) {
            return response()->json(['message' => "El elemento contenedor ({$tipo} #{$id}) no existe."], 404);
        }

        $grupos = $this->construirCarta($tipo, $id);

        $pdf = Pdf::loadView('addon-mapa-red::empalmes.carta_pdf', [
            'grupos' => $grupos,
            'contenedorNombre' => class_basename($tipo) . " #{$id}",
            'generado' => now(),
        ])->setPaper('letter', 'landscape');

        return $pdf->download("carta-empalme-{$id}.pdf");
    }

    /**
     * Agrupa los empalmes de un elemento contenedor por bandeja, ordenados por posición dentro
     * de cada bandeja. Bandeja null/vacía → grupo "Sin bandeja asignada" al final.
     */
    private function construirCarta(string $tipo, int $id): array
    {
        $empalmes = MapaRedEmpalme::where('elemento_contenedor_type', $tipo)
            ->where('elemento_contenedor_id', $id)
            ->with(['hiloA', 'extremoB'])
            ->orderBy('posicion')
            ->get();

        $conBandeja = [];
        $sinBandeja = [];

        foreach ($empalmes as $empalme) {
            $fila = $this->filaDeEmpalme($empalme);

            if ($empalme->bandeja === null || $empalme->bandeja === '') {
                $sinBandeja[] = $fila;
            } else {
                $conBandeja[$empalme->bandeja][] = $fila;
            }
        }

        uksort($conBandeja, 'strnatcmp');

        $resultado = [];
        foreach ($conBandeja as $bandeja => $filas) {
            $resultado[] = ['bandeja' => (string) $bandeja, 'filas' => $filas];
        }
        if (!empty($sinBandeja)) {
            $resultado[] = ['bandeja' => 'Sin bandeja asignada', 'filas' => $sinBandeja];
        }

        return $resultado;
    }

    private function filaDeEmpalme(MapaRedEmpalme $empalme): array
    {
        $hiloA = $empalme->hiloA;

        return [
            'hilo_a' => $hiloA ? [
                'numero' => $hiloA->numero,
                'numero_global' => $hiloA->numero_global,
                'color' => $hiloA->color,
                'buffer' => $hiloA->buffer,
                'buffer_color' => $hiloA->buffer_color,
                'cable_id' => $hiloA->cable_id,
            ] : null,
            'tipo' => $empalme->tipo,
            'perdida_db' => $empalme->perdida_db,
            'extremo_b' => $this->extremoBData($empalme->extremoB),
        ];
    }

    private function extremoBData($extremoB): ?array
    {
        if (!$extremoB) {
            return null;
        }

        if ($extremoB instanceof MapaRedHilo) {
            return [
                'tipo' => 'hilo',
                'numero' => $extremoB->numero,
                'numero_global' => $extremoB->numero_global,
                'color' => $extremoB->color,
                'buffer' => $extremoB->buffer,
                'buffer_color' => $extremoB->buffer_color,
                'cable_id' => $extremoB->cable_id,
            ];
        }

        if ($extremoB instanceof MapaRedPuerto) {
            return [
                'tipo' => 'puerto',
                'numero' => $extremoB->numero,
                'etiqueta' => $extremoB->etiqueta,
                'rol' => $extremoB->rol,
            ];
        }

        return ['tipo' => class_basename($extremoB), 'id' => $extremoB->getKey()];
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
