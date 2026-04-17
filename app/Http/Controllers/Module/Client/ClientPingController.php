<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
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
}
