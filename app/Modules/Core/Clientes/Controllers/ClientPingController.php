<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Clientes\Models\Client;
use App\Models\DailyPingStatistic;
use App\Models\PingStatistic;
use Carbon\Carbon;

class ClientPingController extends Controller
{
    /**
     * Último ping + resumen del día actual.
     * GET /cliente/statistics/get-ping-status/{id}
     */
    public function getPingStatus($id)
    {
        $client = Client::with('client_additional_information')->findOrFail($id);

        if (!$client->client_additional_information?->is_dedicated) {
            return response()->json(['is_dedicated' => false]);
        }

        $latest = PingStatistic::where('client_id', $id)
            ->orderByDesc('recorded_at')
            ->first();

        $today = DailyPingStatistic::where('client_id', $id)
            ->whereDate('date', today())
            ->first();

        return response()->json([
            'is_dedicated' => true,
            'latest'       => $latest ? [
                'avg_ms'      => $latest->avg_ms,
                'min_ms'      => $latest->min_ms,
                'max_ms'      => $latest->max_ms,
                'jitter_ms'   => $latest->jitter_ms,
                'packet_loss' => $latest->packet_loss,
                'status'      => $latest->status,
                'status_color'=> $latest->status_color,
                'ip_address'  => $latest->ip_address,
                'recorded_at' => Carbon::parse($latest->recorded_at)->format('d/m/Y H:i:s'),
            ] : null,
            'today' => $today ? [
                'avg_ms'         => $today->avg_ms,
                'min_ms'         => $today->min_ms,
                'max_ms'         => $today->max_ms,
                'uptime_percent' => $today->uptime_percent,
                'total_checks'   => $today->total_checks,
                'down_checks'    => $today->down_checks,
            ] : null,
        ]);
    }

    /**
     * Historial paginado de las últimas 48h (para tabla).
     * GET /cliente/statistics/get-ping-history/{id}
     */
    public function getPingHistory($id)
    {
        $client = Client::with('client_additional_information')->findOrFail($id);

        if (!$client->client_additional_information?->is_dedicated) {
            return response()->json(['is_dedicated' => false]);
        }

        $history = PingStatistic::where('client_id', $id)
            ->where('recorded_at', '>=', now()->subHours(48))
            ->orderByDesc('recorded_at')
            ->paginate(50);

        $items = $history->getCollection()->map(fn ($r) => [
            'recorded_at' => Carbon::parse($r->recorded_at)->format('d/m/Y H:i:s'),
            'ip_address'  => $r->ip_address,
            'avg_ms'      => $r->avg_ms,
            'min_ms'      => $r->min_ms,
            'max_ms'      => $r->max_ms,
            'jitter_ms'   => $r->jitter_ms,
            'packet_loss' => $r->packet_loss,
            'status'      => $r->status,
            'status_color'=> $r->status_color,
        ]);

        return response()->json([
            'is_dedicated' => true,
            'data'         => $items,
            'total'        => $history->total(),
            'current_page' => $history->currentPage(),
            'last_page'    => $history->lastPage(),
        ]);
    }

    /**
     * Resumen diario del mes en curso (para gráfica de barras/línea).
     * GET /cliente/statistics/get-ping-daily/{id}
     */
    public function getPingDailySummary($id)
    {
        $client = Client::with('client_additional_information')->findOrFail($id);

        if (!$client->client_additional_information?->is_dedicated) {
            return response()->json(['is_dedicated' => false]);
        }

        $daily = DailyPingStatistic::where('client_id', $id)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->orderBy('date')
            ->get();

        $data = $daily->map(fn ($d) => [
            'date'           => Carbon::parse($d->date)->format('d/m'),
            'avg_ms'         => $d->avg_ms,
            'min_ms'         => $d->min_ms,
            'max_ms'         => $d->max_ms,
            'uptime_percent' => $d->uptime_percent,
            'total_checks'   => $d->total_checks,
            'down_checks'    => $d->down_checks,
        ]);

        return response()->json([
            'is_dedicated' => true,
            'data'         => $data,
        ]);
    }

    /**
     * ¿El ping guardado de este dedicado sirve para su reporte de disponibilidad?
     *
     * Hoy no (#999): mikrotik:sync-ping le hace ping a la IP del SERVICIO, y en los dedicados
     * por VLAN esa IP puede ser la del propio router (la de su VLAN), así que
     * ping_statistics mide al router y no al equipo del cliente. El reporte queda sin cifras
     * hasta que se calcule con el ping por IP del equipo del cliente (épica #988, se unifica
     * en #995); entonces esta comprobación pasa a mirar esa medición.
     */
    private function pingConfiableParaReporte(Client $client): bool
    {
        return false;
    }

    /**
     * Reporte mensual de disponibilidad: % uptime, latencia, cortes (inicio/fin/duración).
     * GET /cliente/statistics/get-ping-monthly-report/{id}?mes=YYYY-MM
     * Cortes = rachas consecutivas de lecturas 'down' en ping_statistics.
     * Tráfico pico vs contratado queda fuera: depende de cliente_metricas (épica #988).
     * Para dedicados responde «sin datos confiables» mientras pingConfiableParaReporte() diga no.
     */
    public function getPingMonthlyReport($id)
    {
        $client = Client::with('client_additional_information')->findOrFail($id);

        if (!$client->client_additional_information?->is_dedicated) {
            return response()->json(['is_dedicated' => false]);
        }

        $mes = request('mes');
        try {
            $inicio = $mes ? Carbon::createFromFormat('Y-m', $mes)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable $e) {
            abort(422, 'Mes inválido (usar YYYY-MM).');
        }
        $fin = $inicio->copy()->endOfMonth();

        if (! $this->pingConfiableParaReporte($client)) {
            return response()->json([
                'is_dedicated' => true,
                'confiable'    => false,
                'mes'          => $inicio->format('Y-m'),
                'mensaje'      => 'Sin datos confiables: el ping de este cliente mide la IP de su servicio, que puede ser la del propio router y no la de su equipo. El reporte se habilita cuando se mida el equipo del cliente.',
            ]);
        }

        $daily = DailyPingStatistic::where('client_id', $id)
            ->whereBetween('date', [$inicio->toDateString(), $fin->toDateString()])
            ->get();

        $totalChecks = (int) $daily->sum('total_checks');
        $downChecks  = (int) $daily->sum('down_checks');
        $uptime = $totalChecks > 0 ? round((1 - $downChecks / $totalChecks) * 100, 3) : null;

        $lecturas = PingStatistic::where('client_id', $id)
            ->whereBetween('recorded_at', [$inicio, $fin])
            ->orderBy('recorded_at')
            ->get(['status', 'recorded_at']);

        $cortes = [];
        $actual = null;
        foreach ($lecturas as $l) {
            $t = Carbon::parse($l->recorded_at);
            if ($l->status === 'down') {
                $actual ??= ['inicio' => $t, 'fin' => $t];
                $actual['fin'] = $t;
            } elseif ($actual) {
                $cortes[] = ['inicio' => $actual['inicio'], 'fin' => $t];
                $actual = null;
            }
        }
        if ($actual) {
            $cortes[] = ['inicio' => $actual['inicio'], 'fin' => $actual['fin']];
        }

        $cortes = array_map(fn ($c) => [
            'inicio'           => $c['inicio']->format('d/m/Y H:i'),
            'fin'              => $c['fin']->format('d/m/Y H:i'),
            'duracion_minutos' => $c['inicio']->diffInMinutes($c['fin']),
        ], $cortes);

        return response()->json([
            'is_dedicated'     => true,
            'mes'              => $inicio->format('Y-m'),
            'uptime_percent'   => $uptime,
            'total_checks'     => $totalChecks,
            'down_checks'      => $downChecks,
            'latencia_prom_ms' => $daily->count() ? round((float) $daily->avg('avg_ms'), 2) : null,
            'latencia_max_ms'  => $daily->count() ? (float) $daily->max('max_ms') : null,
            'total_cortes'     => count($cortes),
            'minutos_caido'    => array_sum(array_column($cortes, 'duracion_minutos')),
            'cortes'           => $cortes,
        ]);
    }
}
