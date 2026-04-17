<?php

namespace App\Console\Commands\Active;

use App\Http\Traits\RouterConnection;
use App\Models\Client;
use App\Models\DailyPingStatistic;
use App\Models\PingStatistic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PEAR2\Net\RouterOS\Request as RouterOSRequest;
use PEAR2\Net\RouterOS\Response;

class SyncPingMonitoring extends Command
{
    use RouterConnection;

    protected $signature = 'mikrotik:sync-ping';
    protected $description = 'Mide latencia ICMP desde Mikrotik a clientes dedicados y almacena estadísticas';

    private const PING_COUNT = 5;

    public function handle(): void
    {
        $startTotal = microtime(true);
        $this->info("=== Iniciando monitoreo de ping - " . now()->format('H:i:s') . " ===");

        $clients = Client::isDedicated()
            ->with([
                'internet_service' => fn ($q) => $q
                    ->whereHas('network_ip_used_by')
                    ->with(['router.mikrotik', 'network_ip']),
            ])
            ->get();

        // Filtrar clientes que tengan servicio con IP y router válidos
        $clients = $clients->filter(function ($client) {
            return $client->internet_service->contains(function ($service) {
                return $service->router
                    && $service->router->mikrotik
                    && $service->network_ip
                    && $service->network_ip->ip;
            });
        });

        if ($clients->isEmpty()) {
            $this->warn("No hay clientes dedicados con servicios desplegados.");
            return;
        }

        // Agrupar servicios por router_id
        $servicesByRouter = [];
        foreach ($clients as $client) {
            foreach ($client->internet_service as $service) {
                if (!$service->router || !$service->router->mikrotik || !$service->network_ip?->ip) {
                    continue;
                }
                $routerId = $service->router_id;
                $servicesByRouter[$routerId]['router'] = $service->router;
                $servicesByRouter[$routerId]['entries'][] = [
                    'client_id' => $client->id,
                    'ip'        => $service->network_ip->ip,
                ];
            }
        }

        $totalClients = 0;
        $totalRouters = 0;

        foreach ($servicesByRouter as $routerId => $data) {
            $router = $data['router'];
            $this->info("Conectando a router: {$router->name} ({$router->ip_host})...");

            try {
                $connection = $this->getConnectionByRouter($router);
                if (!$connection) {
                    $this->error("No se pudo conectar al router {$router->name}");
                    Log::warning("SyncPingMonitoring: sin conexión con router {$router->id}");
                    continue;
                }

                foreach ($data['entries'] as $entry) {
                    $this->pingAndRecord($connection, $entry['client_id'], $entry['ip']);
                    $totalClients++;
                }

                $totalRouters++;
                $this->info("✔ {$router->name}: " . count($data['entries']) . " clientes procesados.");

            } catch (\Exception $e) {
                $this->error("✘ Error en router {$router->name}: " . $e->getMessage());
                Log::error("SyncPingMonitoring error router {$router->id}: " . $e->getMessage());
            }
        }

        $elapsed = round(microtime(true) - $startTotal, 2);
        $this->newLine();
        $this->info("====================================================");
        $this->info(" PROCESO COMPLETADO");
        $this->info(" Routers procesados : $totalRouters");
        $this->info(" Clientes pingeados  : $totalClients");
        $this->info(" Tiempo total        : {$elapsed}s");
        $this->info("====================================================");

        if ($elapsed > 240) {
            Log::warning("mikrotik:sync-ping tardó {$elapsed}s — revisar cantidad de clientes o timeouts");
        }
    }

    private function pingAndRecord($connection, int $clientId, string $ip): void
    {
        $recordedAt = now();
        $status     = 'up';
        $avgMs = $minMs = $maxMs = $jitterMs = null;
        $packetLoss = 100;

        try {
            $request = new RouterOSRequest('/ping');
            $request->setArgument('address', $ip);
            $request->setArgument('count', (string) self::PING_COUNT);
            $request->setArgument('interval', '00:00:00.200');

            $responses = $connection->sendSync($request);

            $times = [];
            foreach ($responses as $response) {
                if ($response->getType() !== Response::TYPE_DATA) {
                    continue;
                }
                $time   = $response->getProperty('time');
                $pStatus = $response->getProperty('status');

                // Respuesta exitosa: tiene 'time' y sin status de error
                if ($time !== null && !in_array($pStatus, ['timeout', 'no route to host', 'net unreachable'])) {
                    $times[] = $this->parseRttMs($time);
                }
            }

            $sent     = self::PING_COUNT;
            $received = count($times);
            $packetLoss = (int) round((($sent - $received) / $sent) * 100);

            if ($received > 0) {
                $avgMs    = round(array_sum($times) / $received, 2);
                $minMs    = round(min($times), 2);
                $maxMs    = round(max($times), 2);
                $jitterMs = round($maxMs - $minMs, 2);
                $status   = $packetLoss === 100 ? 'down' : 'up';
            } else {
                $status = 'down';
            }

        } catch (\Exception $e) {
            $status     = 'timeout';
            $packetLoss = 100;
            Log::warning("SyncPingMonitoring: error al pingar {$ip} (cliente {$clientId}): " . $e->getMessage());
        }

        PingStatistic::create([
            'client_id'   => $clientId,
            'ip_address'  => $ip,
            'avg_ms'      => $avgMs,
            'min_ms'      => $minMs,
            'max_ms'      => $maxMs,
            'jitter_ms'   => $jitterMs,
            'packet_loss' => $packetLoss,
            'status'      => $status,
            'recorded_at' => $recordedAt,
        ]);

        $this->updateDailyStats($clientId, $status, $avgMs, $minMs, $maxMs);
    }

    private function updateDailyStats(int $clientId, string $status, ?float $avgMs, ?float $minMs, ?float $maxMs): void
    {
        $daily = DailyPingStatistic::firstOrCreate(
            ['client_id' => $clientId, 'date' => today()],
            [
                'avg_ms'         => 0,
                'min_ms'         => null,
                'max_ms'         => null,
                'uptime_percent' => 100,
                'total_checks'   => 0,
                'down_checks'    => 0,
            ]
        );

        $isDown      = in_array($status, ['down', 'timeout']);
        $newTotal    = $daily->total_checks + 1;
        $newDown     = $daily->down_checks + ($isDown ? 1 : 0);
        $newUptime   = round((($newTotal - $newDown) / $newTotal) * 100, 2);

        $newAvg = $daily->avg_ms;
        $newMin = $daily->min_ms;
        $newMax = $daily->max_ms;

        if (!$isDown && $avgMs !== null) {
            $newAvg = round(($daily->avg_ms * $daily->total_checks + $avgMs) / $newTotal, 2);
            $newMin = $newMin === null ? $minMs : ($minMs !== null ? min($newMin, $minMs) : $newMin);
            $newMax = $newMax === null ? $maxMs : ($maxMs !== null ? max($newMax, $maxMs) : $newMax);
        }

        $daily->update([
            'total_checks'   => $newTotal,
            'down_checks'    => $newDown,
            'uptime_percent' => $newUptime,
            'avg_ms'         => $newAvg,
            'min_ms'         => $newMin,
            'max_ms'         => $newMax,
        ]);
    }

    /**
     * Parsea el string de RTT de RouterOS ("2ms", "1.5ms", "< 1ms") a float en ms.
     */
    private function parseRttMs(string $time): float
    {
        // "< 1ms" → 0.5 (aproximación)
        if (str_starts_with(trim($time), '<')) {
            return 0.5;
        }
        // "2ms", "1.5ms", "10ms"
        if (preg_match('/^([\d.]+)\s*ms$/i', trim($time), $m)) {
            return (float) $m[1];
        }
        // "2000us" → microsegundos a ms
        if (preg_match('/^([\d.]+)\s*us$/i', trim($time), $m)) {
            return round((float) $m[1] / 1000, 3);
        }
        return 0.0;
    }
}
