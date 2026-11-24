<?php

namespace App\Modules\Addons\CobranzaBlaster\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Services\HistorialLlamadasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Campañas/Blaster → Dashboard (24-nov, pedido de Irving): todas las llamadas de la
 * central (entrantes, salientes, internas y de campañas) con su grabación.
 *
 * Lee el mismo historial que VoIP (HistorialLlamadasService, CDR de Asterisk); las
 * grabaciones se sirven por /voip/grabaciones/{id}, que exige voip.grabaciones.escuchar.
 */
class DashboardController extends Controller
{
    public const TIPOS = ['entrante', 'saliente', 'campana', 'interna'];

    /**
     * Filtro de estado del tablero → estados del historial. «Perdida» es la entrante que
     * nadie atendió (falla del equipo); «sin respuesta», la que hicimos y no contestaron.
     */
    private const ESTADOS = [
        'contestada'    => ['contestada', 'asistente'],
        'perdida'       => ['no_atendida'],
        'sin_respuesta' => ['sin_respuesta'],
    ];

    public function __construct(private HistorialLlamadasService $historial)
    {
    }

    public function index()
    {
        if (! auth()->user()->can('cobranza.view')) {
            abort(403);
        }

        return view('addon-cobranza-blaster::dashboard.index', [
            'puedeEscuchar' => $this->puedeEscuchar(),
        ]);
    }

    /** Conteo por tipo, resumen y llamadas por día del rango (del tipo elegido, si hay). */
    public function data(Request $request): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        [$desde, $hasta] = $this->rango($request);
        $base = ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()];

        if (! $this->historial->centralDisponible()) {
            return response()->json(['central' => false] + $base);
        }

        $todas = $this->historial->llamadas($desde, $hasta);
        $tipo  = $this->tipo($request);
        $delTipo = $tipo ? $todas->where('direccion', $tipo) : $todas;

        return response()->json(['central' => true] + $base + [
            'por_tipo' => ['todas' => $todas->count()]
                + collect(self::TIPOS)->mapWithKeys(fn ($t) => [$t => $todas->where('direccion', $t)->count()])->all(),
            'resumen'  => self::resumen($delTipo),
            'por_dia'  => self::porDia($delTipo, $desde, $hasta),
        ]);
    }

    public function llamadas(Request $request): JsonResponse
    {
        if (! auth()->user()->can('cobranza.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        [$desde, $hasta] = $this->rango($request);

        if (! $this->historial->centralDisponible()) {
            return response()->json(['central' => false, 'total' => 0, 'llamadas' => []]);
        }

        $llamadas = $this->historial->llamadas($desde, $hasta);

        if ($tipo = $this->tipo($request)) {
            $llamadas = $llamadas->where('direccion', $tipo);
        }
        if ($estados = self::ESTADOS[$request->get('estado')] ?? null) {
            $llamadas = $llamadas->whereIn('estado', $estados);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->get('dia'))) {
            $dia = $request->get('dia');
            $llamadas = $llamadas->filter(fn ($l) => str_starts_with($l['inicio'], $dia));
        }
        if ($request->boolean('con_grabacion')) {
            $llamadas = $llamadas->whereNotNull('grabacion');
        }
        if ($request->filled('buscar')) {
            $llamadas = self::buscar($llamadas, (string) $request->get('buscar'));
        }

        $porPagina = min(100, max(10, (int) $request->get('por_pagina', 25)));
        $pagina    = max(1, (int) $request->get('pagina', 1));
        $total     = $llamadas->count();
        $pagina    = min($pagina, max(1, (int) ceil($total / $porPagina)));
        $puedeEscuchar = $this->puedeEscuchar();

        return response()->json([
            'central'    => true,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'llamadas'   => $llamadas->slice(($pagina - 1) * $porPagina, $porPagina)->values()
                ->map(function ($l) use ($puedeEscuchar) {
                    unset($l['timbro_a'], $l['timbro_a_ids'], $l['campana_llamada_id']);
                    $l['tiene_grabacion'] = $l['grabacion'] !== null;
                    if (! $puedeEscuchar) {
                        $l['grabacion'] = null;   // sin permiso no viaja el id para pedirla
                    }

                    return $l;
                }),
        ]);
    }

    // ── Cálculos (puros: probados en tests/Unit) ──────────────────────────────

    public static function resumen(Collection $llamadas): array
    {
        $contestadas = $llamadas->whereIn('estado', self::ESTADOS['contestada']);

        return [
            'total'           => $llamadas->count(),
            'contestadas'     => $contestadas->count(),
            'perdidas'        => $llamadas->whereIn('estado', self::ESTADOS['perdida'])->count(),
            'sin_respuesta'   => $llamadas->whereIn('estado', self::ESTADOS['sin_respuesta'])->count(),
            'contestadas_pct' => $llamadas->count() ? (int) round($contestadas->count() * 100 / $llamadas->count()) : null,
            'segundos_total'  => (int) $contestadas->sum('segundos'),
            'duracion_prom'   => $contestadas->isEmpty() ? null : (int) round($contestadas->avg('segundos')),
            'con_grabacion'   => $llamadas->whereNotNull('grabacion')->count(),
            // Contestadas sin grabación: antes del 24-nov solo grababa la cola.
            'sin_grabar'      => $contestadas->whereNull('grabacion')->count(),
        ];
    }

    /** Contestadas, perdidas y sin respuesta por día (también los días sin llamadas, en cero). */
    public static function porDia(Collection $llamadas, Carbon $desde, Carbon $hasta): array
    {
        $porFecha = $llamadas->groupBy(fn ($l) => substr($l['inicio'], 0, 10));
        $dias = [];
        for ($d = $desde->copy()->startOfDay(); $d->lte($hasta); $d->addDay()) {
            $grupo = $porFecha[$d->toDateString()] ?? collect();
            $dias[] = [
                'fecha'         => $d->toDateString(),
                'contestadas'   => $grupo->whereIn('estado', self::ESTADOS['contestada'])->count(),
                'perdidas'      => $grupo->whereIn('estado', self::ESTADOS['perdida'])->count(),
                'sin_respuesta' => $grupo->whereIn('estado', self::ESTADOS['sin_respuesta'])->count(),
            ];
        }

        return $dias;
    }

    /** Número (por dígitos), cliente, trabajador o campaña. */
    public static function buscar(Collection $llamadas, string $texto): Collection
    {
        $q = mb_strtolower(trim($texto));
        if ($q === '') {
            return $llamadas;
        }
        $digitos = preg_replace('/\D/', '', $q);

        return $llamadas->filter(fn ($l) => ($digitos !== '' && (str_contains((string) $l['numero'], $digitos)
                || str_contains((string) $l['origen'], $digitos)))
            || str_contains(mb_strtolower((string) $l['cliente']), $q)
            || str_contains(mb_strtolower((string) $l['trabajador']), $q)
            || str_contains(mb_strtolower((string) ($l['campana'] ?? '')), $q))->values();
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    private function puedeEscuchar(): bool
    {
        return auth()->user()->can('voip.grabaciones.escuchar') && Route::has('voip.grabaciones.show');
    }

    private function tipo(Request $request): ?string
    {
        $tipo = $request->get('tipo');

        return in_array($tipo, self::TIPOS, true) ? $tipo : null;
    }

    /** @return array{0: Carbon, 1: Carbon} rango pedido; por omisión los últimos 7 días, máximo MAX_DIAS */
    private function rango(Request $request): array
    {
        try {
            $hasta = $request->filled('hasta') ? Carbon::parse($request->get('hasta'))->endOfDay() : now()->endOfDay();
            $desde = $request->filled('desde') ? Carbon::parse($request->get('desde'))->startOfDay() : $hasta->copy()->subDays(6)->startOfDay();
        } catch (\Throwable $e) {
            $hasta = now()->endOfDay();
            $desde = $hasta->copy()->subDays(6)->startOfDay();
        }

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }
        if ($desde->diffInDays($hasta) >= HistorialLlamadasService::MAX_DIAS) {
            $desde = $hasta->copy()->subDays(HistorialLlamadasService::MAX_DIAS - 1)->startOfDay();
        }

        return [$desde, $hasta];
    }
}
