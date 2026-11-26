<?php

namespace App\Modules\Addons\CobranzaBlaster\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaCampanaService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaTtsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class CampanaController extends Controller
{
    public function __construct(private CobranzaCampanaService $service) {}

    public function index()
    {
        if (! auth()->user()->can('cobranza.view')) {
            abort(403);
        }

        return view('addon-cobranza-blaster::campanas.index', [
            'puedeGestionar' => auth()->user()->can('cobranza.manage'),
            'puedeCortar'    => auth()->user()->can('cobranza.corte.lanzar'),
        ]);
    }

    /**
     * Lista de campañas con búsqueda, filtros, orden y paginación (25-nov: antes solo
     * paginaba). Con `todo=1` regresa todas las que cumplen el filtro (exportar a Excel).
     */
    public function data(Request $request): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'No tienes permiso para ver las campañas.'], 403);
        }

        $buscar = trim((string) $request->get('buscar'));
        $tipo   = in_array($request->get('tipo'), ['cobranza', 'aviso', 'anuncio', 'corte'], true) ? $request->get('tipo') : null;
        $estado = in_array($request->get('estado'), self::ESTADOS_CAMPANA, true) ? $request->get('estado') : null;

        $filtrar = function ($q) use ($buscar, $tipo) {
            if ($buscar !== '') {
                $q->where(fn ($w) => $w->where('c.nombre', 'like', "%{$buscar}%")->orWhere('c.notas', 'like', "%{$buscar}%"));
            }
            if ($tipo) {
                $q->where('c.tipo', $tipo);
            }
        };

        // Cuántas hay en cada estado con la búsqueda y el tipo de ahora (los segmentos).
        $porEstado = DB::table('cobranza_campanas as c')->tap($filtrar)
            ->selectRaw('c.estado, COUNT(*) as n')->groupBy('c.estado')->pluck('n', 'estado');

        $consulta = DB::table('cobranza_campanas as c')
            ->leftJoin('cobranza_llamadas as l', 'l.campana_id', '=', 'c.id')
            ->tap($filtrar)
            ->when($estado, fn ($q) => $q->where('c.estado', $estado))
            ->select(
                'c.*',
                DB::raw('COUNT(l.id) as total_llamadas'),
                DB::raw("SUM(CASE WHEN l.estado = 'contestada'  THEN 1 ELSE 0 END) as contestadas"),
                DB::raw("SUM(CASE WHEN l.estado = 'pagada'      THEN 1 ELSE 0 END) as pagadas"),
                DB::raw("SUM(CASE WHEN l.estado = 'pendiente'   THEN 1 ELSE 0 END) as pendientes"),
                DB::raw("SUM(CASE WHEN l.estado = 'marcando'    THEN 1 ELSE 0 END) as marcando"),
                DB::raw("SUM(CASE WHEN l.estado = 'no_contesto' THEN 1 ELSE 0 END) as no_contesto"),
                DB::raw("SUM(CASE WHEN l.estado = 'ocupado'     THEN 1 ELSE 0 END) as ocupado"),
                DB::raw("SUM(CASE WHEN l.estado = 'fallida'     THEN 1 ELSE 0 END) as fallidas"),
                DB::raw("SUM(CASE WHEN l.estado = 'excluida'    THEN 1 ELSE 0 END) as excluidas")
            )
            ->groupBy('c.id');

        $orden = [
            'nombre'      => 'c.nombre',
            'estado'      => DB::raw("FIELD(c.estado, 'activa', 'pausada', 'borrador', 'completada')"),
            'llamadas'    => 'total_llamadas',
            'contestadas' => 'contestadas',
            'creada'      => 'c.created_at',
        ][$request->get('orden')] ?? 'c.created_at';
        $consulta->orderBy($orden, $request->get('dir') === 'asc' ? 'asc' : 'desc')->orderByDesc('c.id');

        $extra = [
            'por_estado' => ['todas' => (int) $porEstado->sum()] + collect(self::ESTADOS_CAMPANA)
                ->mapWithKeys(fn ($e) => [$e => (int) ($porEstado[$e] ?? 0)])->all(),
        ];

        if ($request->boolean('todo')) {
            return response()->json(['data' => $consulta->limit(5000)->get()] + $extra);
        }

        $porPagina = in_array((int) $request->get('por_pagina'), [10, 25, 50, 100], true) ? (int) $request->get('por_pagina') : 25;

        return response()->json(array_merge($consulta->paginate($porPagina)->toArray(), $extra));
    }

    /**
     * Resumen de arriba: lo que está pasando ahora y lo de hoy. «Por marcar» son las
     * llamadas que esperan turno en campañas activas (antes se contaban las creadas hoy
     * aunque su campaña estuviera en pausa).
     */
    public function kpis(): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'No tienes permiso para ver las campañas.'], 403);
        }

        $hoy = now()->toDateString();
        $marcadasHoy = CobranzaLlamada::whereDate('ultimo_intento_at', $hoy)->count();
        $contestadasHoy = CobranzaLlamada::whereDate('ultimo_intento_at', $hoy)->where('estado', 'contestada')->count();

        return response()->json([
            'campanas_activas'     => CobranzaCampana::where('estado', 'activa')->count(),
            'campanas_pausadas'    => CobranzaCampana::where('estado', 'pausada')->count(),
            'en_curso'             => CobranzaLlamada::where('estado', 'marcando')->count(),
            'por_marcar'           => CobranzaLlamada::where('estado', 'pendiente')
                                        ->whereIn('campana_id', CobranzaCampana::where('estado', 'activa')->select('id'))->count(),
            'marcadas_hoy'         => $marcadasHoy,
            'llamadas_contestadas' => $contestadasHoy,
            'contestadas_pct'      => $marcadasHoy ? (int) round($contestadasHoy * 100 / $marcadasHoy) : null,
            'pagadas_hoy'          => CobranzaLlamada::whereDate('updated_at', $hoy)->where('estado', 'pagada')->count(),
            'canales'              => (int) config('cobranza.canales_por_defecto', 10),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'No tienes permiso para gestionar campañas.'], 403);
        }

        $data = $request->validate([
            'nombre'                  => 'required|string|max:255',
            'tipo'                    => 'nullable|in:cobranza,aviso,anuncio,corte',
            'max_canales_simultaneos' => 'nullable|integer|min:1|max:100',
            'fecha_inicio'            => 'nullable|date',
            'fecha_fin'               => 'nullable|date|after_or_equal:fecha_inicio',
            'hora_inicio'             => 'nullable|date_format:H:i',
            'hora_fin'                => 'nullable|date_format:H:i',
            'max_intentos'            => 'nullable|integer|min:1|max:10',
            'minutos_entre_intentos'  => 'nullable|integer|min:30',
            'dias_vencimiento'        => 'nullable|integer|min:0',
            'audio_mensaje'           => 'nullable|string|max:2000',
            'notas'                   => 'nullable|string|max:2000',
        ] + $this->reglasVoz());

        // La nota es el mensaje que escucha el cliente (24-nov). En cobranza, vacía = el
        // mensaje de siempre; en aviso/anuncio/corte no hay nada que leer sin ella.
        if (($data['tipo'] ?? 'cobranza') !== 'cobranza' && trim((string) ($data['notas'] ?? '')) === '') {
            throw ValidationException::withMessages(['notas' => 'Escribe la nota: es el mensaje que escucha el cliente.']);
        }

        // Un campo numérico o de hora que se dejó vacío toma el valor de siempre de la
        // columna (antes llegaba NULL a una columna NOT NULL y el alta fallaba con 500).
        foreach (['hora_inicio', 'hora_fin', 'max_intentos', 'minutos_entre_intentos', 'dias_vencimiento'] as $campo) {
            if (array_key_exists($campo, $data) && $data[$campo] === null) {
                unset($data[$campo]);
            }
        }

        $data['voz'] ??= config('cobranza.blaster_tts_voice', 'nova');
        $data['voz_velocidad'] ??= CobranzaTtsService::VELOCIDAD_DEFECTO;

        $campana = CobranzaCampana::create($data);

        // fresh(): con los valores de siempre de la base (horario, intentos…) para que la
        // pantalla siga al paso «a quién llamar» con la campaña completa.
        return response()->json($campana->fresh(), 201);
    }

    /** Voces, tonos y velocidad para el alta, y si hay voz asignada en Integraciones → Módulos IA. */
    public function voz(CobranzaTtsService $tts): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'No tienes permiso para gestionar campañas.'], 403);
        }

        return response()->json($tts->opciones());
    }

    /**
     * Escuchar la nota con la voz elegida antes de crear la campaña. En cobranza las variables
     * se llenan con datos de ejemplo. Cada muestra cuesta (se registra en Módulos IA).
     */
    public function escuchar(Request $request, CobranzaTtsService $tts)
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'No tienes permiso para gestionar campañas.'], 403);
        }

        $data = $request->validate([
            'notas'     => 'required|string|max:2000',
            'tipo'      => 'nullable|in:cobranza,aviso,anuncio,corte',
            'fecha_fin' => 'nullable|date',
        ] + $this->reglasVoz());

        $fecha = ! empty($data['fecha_fin']) ? date('d/m/Y', strtotime($data['fecha_fin'])) : 'la brevedad posible';
        $texto = $tts->textoDeEjemplo($data['notas'], $data['tipo'] ?? 'cobranza', $fecha);

        $r = $tts->muestra($texto, [
            'voz'       => $data['voz'] ?? null,
            'velocidad' => $data['voz_velocidad'] ?? null,
            'tono'      => $data['voz_tono'] ?? null,
        ]);

        if (! $r['ok']) {
            return response()->json(['error' => $r['error']], 422);
        }

        return new Response($r['mp3'], 200, [
            'Content-Type'  => 'audio/mpeg',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function reglasVoz(): array
    {
        return [
            'voz'           => ['nullable', Rule::in(array_keys(CobranzaTtsService::VOCES))],
            'voz_velocidad' => 'nullable|numeric|between:' . CobranzaTtsService::VELOCIDAD_MIN . ',' . CobranzaTtsService::VELOCIDAD_MAX,
            'voz_tono'      => ['nullable', Rule::in(array_keys(CobranzaTtsService::TONOS))],
        ];
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
            return response()->json(['error' => $this->sinPermiso($campana)], 403);
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

        $r = $this->service->activarCampanaPorZona(
            $campana,
            $data['district_id'],
            $data['zone_id'] ?? null,
            $data['box_id'] ?? null
        );

        return response()->json(['ok' => true] + $r);
    }

    public function activar(int $id): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'No tienes permiso para gestionar campañas.'], 403);
        }

        $campana = CobranzaCampana::findOrFail($id);

        if (!in_array($campana->estado, ['borrador', 'pausada'])) {
            return response()->json(['error' => 'Solo se puede activar desde borrador o pausada.'], 422);
        }

        $r = $this->service->activarCampana($campana);

        return response()->json(['ok' => true] + $r);
    }

    public function pausar(int $id): JsonResponse
    {
        if (! auth()->user()->can('cobranza.manage')) {
            return response()->json(['error' => 'No tienes permiso para gestionar campañas.'], 403);
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
            return response()->json(['error' => 'No tienes permiso para gestionar campañas.'], 403);
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
            return response()->json(['error' => 'No tienes permiso para ver las campañas.'], 403);
        }

        return response()->json(\App\Models\District::orderBy('name')->get(['id', 'name']));
    }

    public function zonasPorDistrito(int $districtId): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'No tienes permiso para ver las campañas.'], 403);
        }

        return response()->json(
            \App\Models\Zone::where('district_id', $districtId)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function cajasPorZona(int $zoneId): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'No tienes permiso para ver las campañas.'], 403);
        }

        return response()->json(
            DB::table('box_zones')->where('zone_id', $zoneId)->orderBy('name')->get(['id', 'name'])
        );
    }

    /** Llamadas de una campaña, paginadas, con cuántas hay en cada estado. */
    public function llamadas(int $id, Request $request): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'No tienes permiso para ver las campañas.'], 403);
        }

        $campana = CobranzaCampana::findOrFail($id);

        $porEstado = $campana->llamadas()->selectRaw('estado, COUNT(*) as n')->groupBy('estado')->pluck('n', 'estado');

        $llamadas = $campana->llamadas()
            ->leftJoin('client_main_information as cmi', 'cmi.client_id', '=', 'cobranza_llamadas.client_id')
            ->select(
                'cobranza_llamadas.*',
                DB::raw("TRIM(CONCAT(COALESCE(cmi.name,''), ' ', COALESCE(cmi.father_last_name,''))) as cliente_nombre")
            )
            ->when($request->estado, fn ($q) => $q->where('cobranza_llamadas.estado', $request->estado))
            ->orderByRaw('cobranza_llamadas.ultimo_intento_at IS NULL')
            ->orderByDesc('cobranza_llamadas.ultimo_intento_at')
            ->orderBy('cobranza_llamadas.id')
            ->paginate(25);

        return response()->json(array_merge($llamadas->toArray(), [
            'por_estado' => ['todas' => (int) $porEstado->sum()] + $porEstado->map(fn ($n) => (int) $n)->all(),
        ]));
    }

    /**
     * A cuántos clientes llamaría si se activa ahora: cobranza por morosos, el resto por
     * distrito/zona/caja. Se muestra antes de activar (25-nov).
     */
    public function alcance(int $id, Request $request): JsonResponse
    {
        $campana = CobranzaCampana::findOrFail($id);
        if (! auth()->user()->can($this->permisoPara($campana))) {
            return response()->json(['error' => $this->sinPermiso($campana)], 403);
        }

        if ($campana->tipo !== 'cobranza') {
            $data = $request->validate([
                'district_id' => 'required|integer|exists:districts,id',
                'zone_id'     => 'nullable|integer|exists:zones,id',
                'box_id'      => 'nullable|integer|exists:box_zones,id',
            ]);

            return response()->json($this->service->alcance($campana, $data['district_id'], $data['zone_id'] ?? null, $data['box_id'] ?? null));
        }

        return response()->json($this->service->alcance($campana));
    }

    /** Pausada → activa, con lo que ya tenía cargado. */
    public function reanudar(int $id): JsonResponse
    {
        $campana = CobranzaCampana::findOrFail($id);
        if (! auth()->user()->can($this->permisoPara($campana))) {
            return response()->json(['error' => $this->sinPermiso($campana)], 403);
        }
        if ($campana->estado !== 'pausada') {
            return response()->json(['error' => 'Solo se puede reanudar una campaña en pausa.'], 422);
        }

        $this->service->reanudarCampana($campana);

        return response()->json(['ok' => true]);
    }

    private const ESTADOS_CAMPANA = ['activa', 'pausada', 'borrador', 'completada'];

    /** Lanzar un corte pide su propio permiso; lo demás, el de gestionar campañas. */
    private function permisoPara(CobranzaCampana $campana): string
    {
        return $campana->tipo === 'corte' ? 'cobranza.corte.lanzar' : 'cobranza.manage';
    }

    private function sinPermiso(CobranzaCampana $campana): string
    {
        return $campana->tipo === 'corte'
            ? 'No tienes permiso para lanzar campañas de corte de servicio.'
            : 'No tienes permiso para gestionar campañas.';
    }
}
