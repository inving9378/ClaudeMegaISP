<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Traits\RouterConnection;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PEAR2\Net\RouterOS\Request as MikroTikRequest;
use PEAR2\Net\RouterOS\Response as MikroTikResponse;
use PEAR2\Net\RouterOS\Query;

class ClientStatisticsController extends Controller
{
    use RouterConnection;

    /**
     * 1. CONEXIONES ACTIVAS (Modo Live)
     * Ruta: /get-active-connections/{id}
     */
    public function getActiveConnections($id)
    {
        $client = Client::with(['internet_service.router'])->find($id);
        if (!$client) return response()->json(['success' => false, 'message' => 'No encontrado'], 404);

        $activeConnections = [];
        foreach ($client->internet_service as $service) {
            $router = $service->router;
            if (!$router) continue;

            $connection = $this->getConnectionByRouter($router);
            if ($connection) {
                $request = new MikroTikRequest('/ppp/active/print');
                $request->setQuery(Query::where('name', $service->client_name));
                $responses = $connection->sendSync($request);

                foreach ($responses as $r) {
                    if ($r->getType() === MikroTikResponse::TYPE_DATA) {
                        $interfaceName = "<pppoe-" . $service->client_name . ">";

                        // Velocidad en tiempo real
                        $monRequest = new MikroTikRequest('/interface/monitor-traffic');
                        $monRequest->setArgument('interface', $interfaceName);
                        $monRequest->setArgument('once', '');
                        $monResponse = $connection->sendSync($monRequest);

                        $speedIn = "0 bps"; $speedOut = "0 bps";
                        foreach ($monResponse as $mr) {
                            if ($mr->getType() === MikroTikResponse::TYPE_DATA) {
                                $speedIn = $this->formatSpeed($mr->getProperty('rx-bits-per-second'));
                                $speedOut = $this->formatSpeed($mr->getProperty('tx-bits-per-second'));
                            }
                        }

                        // Tráfico acumulado de la interfaz
                        $intRequest = new MikroTikRequest('/interface/print');
                        $intRequest->setQuery(Query::where('name', $interfaceName));
                        $intRes = $connection->sendSync($intRequest);
                        $bIn = 0; $bOut = 0;
                        foreach($intRes as $ir) {
                            $bIn = $ir->getProperty('rx-byte');
                            $bOut = $ir->getProperty('tx-byte');
                        }

                        $activeConnections[] = [
                            'service_id'   => $service->id,
                            'client_name'  => $service->client_name,
                            'ip_assigned'  => $r->getProperty('address'),
                            'uptime'       => $r->getProperty('uptime'),
                            'start_at'     => Carbon::now()->subSeconds($this->parseMikrotikUptimeToSeconds($r->getProperty('uptime')))->format('d/m/Y H:i:s'),
                            'bytes_in'     => $this->formatBytes($bIn),
                            'bytes_out'    => $this->formatBytes($bOut),
                            'speed_in'     => $speedIn,
                            'speed_out'    => $speedOut,
                            'nas'          => $router->name ?? $router->ip_host,
                            'mac_address'  => $r->getProperty('caller-id'),
                            'radius'       => 'false',
                        ];
                    }
                }
            }
        }
        return response()->json(['success' => true, 'data' => $activeConnections]);
    }

    /**
     * 2. RESUMEN DE CONSUMO (Deltas Mensuales + Gráfico Líneas)
     * Ruta: /get-consumption-summary/{id}
     */
    public function getConsumptionSummary($id)
    {
        $client = Client::with('internet_service')->find($id);
        if (!$client) return response()->json(['success' => false], 404);
        $usernames = $client->internet_service->pluck('client_name')->toArray();

        // Suma de deltas del mes
        $summary = DB::table('daily_internet_consumptions')
            ->whereIn('client_name', $usernames)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->select([
                DB::raw('SUM(bytes_out) as total_down'),
                DB::raw('SUM(bytes_in) as total_up')
            ])->first();

        // Stats de sesiones desde la tabla de snapshots/sessions
        $sessionStats = DB::table('internet_consumptions')
            ->whereIn('client_name', $usernames)
            ->whereMonth('updated_at', now()->month)
            ->select([
                DB::raw('COUNT(DISTINCT session_id) as total_sessions'),
                DB::raw('MAX(uptime) as max_uptime')
            ])->first();

        // Datos del gráfico (Día a día del mes)
        $dailyData = DB::table('daily_internet_consumptions')
            ->whereIn('client_name', $usernames)
            ->whereMonth('date', now()->month)
            ->select('date', DB::raw('SUM(bytes_out) / 1073741824 as down_gb'), DB::raw('SUM(bytes_in) / 1073741824 as up_gb'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $labels = $dailyData->pluck('date')->map(fn($d) => Carbon::parse($d)->format('d'))->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $sessionStats->total_sessions ?? 0,
                'errors'   => 0,
                'time'     => $this->formatSeconds($sessionStats->max_uptime ?? 0),
                'download' => $this->formatToGB($summary->total_down ?? 0),
                'upload'   => $this->formatToGB($summary->total_up ?? 0),
                'graph_data' => [
                    'labels'   => !empty($labels) ? $labels : [now()->format('d')],
                    'download' => $dailyData->pluck('down_gb')->toArray(),
                    'upload'   => $dailyData->pluck('up_gb')->toArray()
                ]
            ]
        ]);
    }

    /**
     * 3. USO POR DÍA (Gráfico Barras)
     * Ruta: /get-daily-usage/{id}
     */
    public function getDailyUsage($id)
    {
        $client = Client::with('internet_service')->find($id);
        $usernames = $client->internet_service->pluck('client_name')->toArray();

        $results = DB::table('daily_internet_consumptions')
            ->whereIn('client_name', $usernames)
            ->whereMonth('date', now()->month)
            ->select('date', DB::raw('SUM(bytes_out) / 1048576 as down_mb'), DB::raw('SUM(bytes_in) / 1048576 as up_mb'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'labels' => $results->pluck('date')->map(fn($d) => Carbon::parse($d)->format('d/m/Y')),
            'download' => $results->pluck('down_mb'),
            'upload' => $results->pluck('up_mb')
        ]);
    }

    /**
     * 4. ESTADÍSTICAS FUP
     * Ruta: /get-fup-stats/{id}
     */
    public function getFupStatistics($id)
    {
        $client = Client::with('internet_service')->find($id);
        $usernames = $client->internet_service->pluck('client_name')->toArray();

        $periods = ['Día' => now()->today(), 'Semana' => now()->startOfWeek(), 'Mes' => now()->startOfMonth()];
        $data = [];

        foreach ($periods as $label => $startDate) {
            $s = DB::table('daily_internet_consumptions')
                ->whereIn('client_name', $usernames)
                ->where('date', '>=', $startDate->toDateString())
                ->select(DB::raw('SUM(bytes_out) as down, SUM(bytes_in) as up'))
                ->first();

            $data[] = [
                'periodo' => $label,
                'down'    => number_format(($s->down ?? 0) / 1048576, 2, '.', ''),
                'up'      => number_format(($s->up ?? 0) / 1048576, 2, '.', ''),
                'time'    => '---'
            ];
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 5. HISTORIAL DE CONEXIONES (Tabla de Sesiones)
     * Ruta: /get-history/{id}
     */
    public function getConnectionHistory(Request $request, $id)
    {
        $perPage = $request->get('per_page', 50);
        $client = Client::with('internet_service')->find($id);
        $usernames = $client->internet_service->pluck('client_name')->toArray();

        $sessions = DB::table('internet_consumptions')
            ->whereIn('client_name', $usernames)
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        $sessions->getCollection()->transform(function ($s) {
            return [
                'id' => $s->id,
                'connected' => $s->created_at,
                'disconnected' => (Carbon::parse($s->updated_at)->diffInMinutes(now()) < 10) ? 'En línea' : $s->updated_at,
                'time' => $this->formatSeconds($s->uptime ?? 0),
                'error' => 0,
                'down_mb' => round($s->bytes_out / 1048576, 2),
                'up_mb' => round($s->bytes_in / 1048576, 2),
                'ip' => $s->ip_address,
                'mac' => $s->mac_address,
                'nas' => $s->nas_ip
            ];
        });

        return response()->json(['success' => true, 'data' => $sessions]);
    }
}
