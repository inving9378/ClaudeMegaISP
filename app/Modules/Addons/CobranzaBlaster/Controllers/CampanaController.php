<?php

namespace App\Modules\Addons\CobranzaBlaster\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaCampanaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampanaController extends Controller
{
    public function __construct(private CobranzaCampanaService $service) {}

    public function index()
    {
        if (! auth()->user()->can('cobranza.view')) {
            abort(403);
        }

        return view('addon-cobranza-blaster::campanas.index');
    }

    public function data(): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $campanas = DB::table('cobranza_campanas as c')
            ->leftJoin('cobranza_llamadas as l', 'l.campana_id', '=', 'c.id')
            ->select(
                'c.*',
                DB::raw('COUNT(l.id) as total_llamadas'),
                DB::raw("SUM(CASE WHEN l.estado = 'contestada' THEN 1 ELSE 0 END) as contestadas"),
                DB::raw("SUM(CASE WHEN l.estado = 'pagada'     THEN 1 ELSE 0 END) as pagadas"),
                DB::raw("SUM(CASE WHEN l.estado = 'pendiente'  THEN 1 ELSE 0 END) as pendientes"),
                DB::raw("SUM(CASE WHEN l.estado = 'fallida'    THEN 1 ELSE 0 END) as fallidas"),
                DB::raw("SUM(CASE WHEN l.estado = 'marcando'   THEN 1 ELSE 0 END) as marcando")
            )
            ->groupBy('c.id')
            ->orderByDesc('c.created_at')
            ->paginate(25);

        return response()->json($campanas);
    }

    public function kpis(): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $hoy = now()->toDateString();

        return response()->json([
            'campanas_activas'      => CobranzaCampana::where('estado', 'activa')->count(),
            'llamadas_pendientes'   => CobranzaLlamada::whereDate('created_at', $hoy)
                                        ->where('estado', 'pendiente')->count(),
            'llamadas_contestadas'  => CobranzaLlamada::whereDate('ultimo_intento_at', $hoy)
                                        ->where('estado', 'contestada')->count(),
            'pagadas_hoy'           => CobranzaLlamada::whereDate('updated_at', $hoy)
                                        ->where('estado', 'pagada')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'nombre'                  => 'required|string|max:255',
            'tipo'                    => 'nullable|in:cobranza,aviso,anuncio,corte',
            'troncal_id'              => 'nullable|integer|exists:voip_troncales,id',
            'max_canales_simultaneos' => 'nullable|integer|min:1|max:100',
            'fecha_inicio'            => 'nullable|date',
            'fecha_fin'               => 'nullable|date|after_or_equal:fecha_inicio',
            'hora_inicio'             => 'nullable|date_format:H:i',
            'hora_fin'                => 'nullable|date_format:H:i',
            'max_intentos'            => 'nullable|integer|min:1|max:10',
            'minutos_entre_intentos'  => 'nullable|integer|min:30',
            'dias_vencimiento'        => 'nullable|integer|min:0',
            'audio_mensaje'           => 'nullable|string|max:2000',
            'notas'                   => 'nullable|string',
        ]);

        $campana = CobranzaCampana::create($data);

        return response()->json($campana, 201);
    }

    /**
     * MegaVoz Fase 7 — "botón de corte por distrito/zona/caja". Activa una
     * campaña tipo aviso/anuncio/corte cargando clientes por geografía
     * (nomenclatura DxZyCz) en vez de por morosidad. `corte` exige el
     * permiso dedicado del plan (cobranza.corte.lanzar); aviso/anuncio
     * comparten el permiso general de gestión (mismo criterio que el resto
     * de acciones sobre campañas).
     */
    public function activarPorZona(int $id, Request $request): JsonResponse
    {
        $campana = CobranzaCampana::findOrFail($id);

        $permisoRequerido = $campana->tipo === 'corte' ? 'cobranza.corte.lanzar' : 'cobranza.manage';
        if (! auth()->user()->can($permisoRequerido)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (! in_array($campana->tipo, ['aviso', 'anuncio', 'corte'])) {
            return response()->json(['error' => 'Esta acción es solo para campañas tipo aviso/anuncio/corte.'], 422);
        }

        if (! in_array($campana->estado, ['borrador', 'pausada'])) {
            return response()->json(['error' => 'Solo se puede activar desde borrador o pausada.'], 422);
        }

        $data = $request->validate([
            'district_id' => 'required|integer|exists:districts,id',
            'zone_id'     => 'nullable|integer|exists:zones,id',
            'box_id'      => 'nullable|integer|exists:box_zones,id',
        ]);

        $insertados = $this->service->activarCampanaPorZona(
            $campana,
            $data['district_id'],
            $data['zone_id'] ?? null,
            $data['box_id'] ?? null
        );

        return response()->json(['ok' => true, 'insertados' => $insertados]);
    }

    public function activar(int $id): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $campana = CobranzaCampana::findOrFail($id);

        if (!in_array($campana->estado, ['borrador', 'pausada'])) {
            return response()->json(['error' => 'Solo se puede activar desde borrador o pausada.'], 422);
        }

        $this->service->activarCampana($campana);

        return response()->json(['ok' => true, 'insertados' => $campana->llamadas()->count()]);
    }

    public function pausar(int $id): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $campana = CobranzaCampana::findOrFail($id);

        if ($campana->estado !== 'activa') {
            return response()->json(['error' => 'La campaña no está activa.'], 422);
        }

        $this->service->pausarCampana($campana);

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $campana = CobranzaCampana::findOrFail($id);

        if ($campana->estado !== 'borrador') {
            return response()->json(['error' => 'Solo se pueden eliminar campañas en borrador.'], 422);
        }

        $campana->delete();

        return response()->json(['ok' => true]);
    }

    // MegaVoz Fase 7 — lookups para el selector de distrito/zona/caja del
    // "botón de corte". Solo lectura, gate con cobranza.view (lo mismo que
    // ya gatea ver la pantalla de campañas).

    public function distritos(): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json(\App\Models\District::orderBy('name')->get(['id', 'name']));
    }

    public function zonasPorDistrito(int $districtId): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json(
            \App\Models\Zone::where('district_id', $districtId)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function cajasPorZona(int $zoneId): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json(
            DB::table('box_zones')->where('zone_id', $zoneId)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function llamadas(int $id, Request $request): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $campana  = CobranzaCampana::findOrFail($id);

        $llamadas = $campana->llamadas()
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'cobranza_llamadas.client_id')
            ->select(
                'cobranza_llamadas.*',
                DB::raw("CONCAT(cmi.name, ' ', COALESCE(cmi.father_last_name,'')) as cliente_nombre")
            )
            ->when($request->estado, fn ($q) => $q->where('cobranza_llamadas.estado', $request->estado))
            ->orderByDesc('cobranza_llamadas.ultimo_intento_at')
            ->paginate(50);

        return response()->json($llamadas);
    }
}
