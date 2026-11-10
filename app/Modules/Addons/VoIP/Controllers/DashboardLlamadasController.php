<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Models\Llamada;
use App\Modules\Addons\VoIP\Services\HistorialLlamadasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * /voip: tablero de llamadas, historial con grabaciones y rendimiento por trabajador.
 *
 * Como el resto del módulo, las rutas de VoIP no pasan por check_route_permission:
 * el permiso se revisa aquí, en cada método.
 *   voip.dashboard.view        → tablero e historial
 *   voip.grabaciones.escuchar  → escuchar / descargar grabaciones
 */
class DashboardLlamadasController extends Controller
{
    public function __construct(private HistorialLlamadasService $historial)
    {
    }

    /** /voip — el tablero para quien puede verlo; para los demás, Troncales como antes. */
    public function inicio()
    {
        if (! auth()->user()->can('voip.dashboard.view')) {
            return app(TroncalController::class)->index();
        }

        return view('addon-voip::dashboard.index', [
            'puedeEscuchar' => auth()->user()->can('voip.grabaciones.escuchar'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (! auth()->user()->can('voip.dashboard.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        [$desde, $hasta] = $this->rango($request);

        if (! $this->historial->centralDisponible()) {
            return response()->json(['central' => false, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]);
        }

        $llamadas = $this->historial->llamadas($desde, $hasta);

        return response()->json([
            'central'        => true,
            'desde'          => $desde->toDateString(),
            'hasta'          => $hasta->toDateString(),
            'minutos_ticket' => HistorialLlamadasService::MINUTOS_TICKET,
            'resumen'        => $this->historial->resumen($llamadas),
            'por_dia'        => $this->historial->porDia($llamadas, $desde, $hasta),
            'trabajadores'   => $this->historial->porTrabajador($llamadas),
        ]);
    }

    public function llamadas(Request $request): JsonResponse
    {
        if (! auth()->user()->can('voip.dashboard.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        [$desde, $hasta] = $this->rango($request);

        if (! $this->historial->centralDisponible()) {
            return response()->json(['central' => false, 'total' => 0, 'llamadas' => []]);
        }

        $llamadas = $this->historial->llamadas($desde, $hasta);

        if ($request->filled('direccion')) {
            $llamadas = $llamadas->where('direccion', $request->get('direccion'));
        }
        if ($request->filled('estado')) {
            $llamadas = $llamadas->where('estado', $request->get('estado'));
        }
        if ($request->filled('trabajador')) {
            $llamadas = $llamadas->where('trabajador_id', (int) $request->get('trabajador'));
        }
        if ($request->boolean('con_grabacion')) {
            $llamadas = $llamadas->whereNotNull('grabacion');
        }
        if ($request->filled('buscar')) {
            $q = mb_strtolower(trim((string) $request->get('buscar')));
            $digitos = preg_replace('/\D/', '', $q);
            $llamadas = $llamadas->filter(fn ($l) => ($digitos !== '' && str_contains((string) $l['numero'], $digitos))
                || str_contains(mb_strtolower((string) $l['cliente']), $q)
                || str_contains(mb_strtolower((string) $l['trabajador']), $q));
        }

        $porPagina = min(100, max(10, (int) $request->get('por_pagina', 25)));
        $pagina    = max(1, (int) $request->get('pagina', 1));
        $total     = $llamadas->count();
        $puedeEscuchar = auth()->user()->can('voip.grabaciones.escuchar');

        return response()->json([
            'central'    => true,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'llamadas'   => $llamadas->slice(($pagina - 1) * $porPagina, $porPagina)->values()
                ->map(function ($l) use ($puedeEscuchar) {
                    unset($l['timbro_a'], $l['timbro_a_ids']);
                    $l['tiene_grabacion'] = $l['grabacion'] !== null;
                    if (! $puedeEscuchar) {
                        $l['grabacion'] = null;   // sin permiso no viaja el id para pedirla
                    }

                    return $l;
                }),
        ]);
    }

    /** Escuchar (inline, con salto en el audio) o ?descargar=1. */
    public function grabacion(Request $request, string $uniqueid)
    {
        if (! auth()->user()->can('voip.grabaciones.escuchar')) {
            abort(403);
        }
        if (! preg_match('/^\d+\.\d+$/', $uniqueid)) {
            abort(404);
        }

        $llamada = Llamada::where('uniqueid', $uniqueid)->whereNotNull('grabacion')->first();
        $ruta    = $llamada?->grabacionPath();
        $base    = realpath((string) config('voip.grabaciones.dir'));
        $real    = $ruta ? realpath($ruta) : false;

        // Solo archivos que de verdad viven dentro de la carpeta de grabaciones.
        if (! $real || ! $base || ! str_starts_with($real, $base . DIRECTORY_SEPARATOR) || ! is_readable($real)) {
            abort(404, 'La grabación no está disponible en el servidor.');
        }

        $nombre = 'llamada-' . Carbon::parse($llamada->start)->format('Ymd-His') . '.wav';

        return $request->boolean('descargar')
            ? response()->download($real, $nombre, ['Content-Type' => 'audio/wav'])
            : response()->file($real, ['Content-Type' => 'audio/wav']);
    }

    /** @return array{0: Carbon, 1: Carbon} rango pedido; por omisión los últimos 7 días, máximo MAX_DIAS */
    private function rango(Request $request): array
    {
        $hasta = $request->filled('hasta') ? Carbon::parse($request->get('hasta'))->endOfDay() : now()->endOfDay();
        $desde = $request->filled('desde') ? Carbon::parse($request->get('desde'))->startOfDay() : $hasta->copy()->subDays(6)->startOfDay();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }
        if ($desde->diffInDays($hasta) >= HistorialLlamadasService::MAX_DIAS) {
            $desde = $hasta->copy()->subDays(HistorialLlamadasService::MAX_DIAS - 1)->startOfDay();
        }

        return [$desde, $hasta];
    }
}
