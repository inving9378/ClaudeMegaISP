<?php

namespace App\Console\Commands\Active;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPingExterno extends Command
{
    protected $signature = 'mikrotik:sync-ping-externo';
    protected $description = 'Mide ping a clientes dedicados desde una sonda externa (fuera de la red de Meganet)';

    public function handle(): int
    {
        if (!config('ping_externo.enabled') || !config('ping_externo.probe_url')) {
            $this->warn('Ping externo apagado o sin sonda configurada (PING_EXTERNO_ENABLED / PING_EXTERNO_PROBE_URL).');
            return self::SUCCESS;
        }

        $clients = Client::isDedicated()
            ->with(['internet_service' => fn ($q) => $q->whereHas('network_ip_used_by')->with('network_ip')])
            ->get();

        $ipToClient = [];
        foreach ($clients as $client) {
            foreach ($client->internet_service as $service) {
                if ($service->network_ip?->ip) {
                    $ipToClient[$service->network_ip->ip] = $client->id;
                }
            }
        }

        if (!$ipToClient) {
            $this->warn('No hay clientes dedicados con IP.');
            return self::SUCCESS;
        }

        try {
            $request = Http::timeout(config('ping_externo.timeout'));
            if ($token = config('ping_externo.probe_token')) {
                $request = $request->withToken($token);
            }
            $response = $request->post(config('ping_externo.probe_url'), [
                'ips'   => array_keys($ipToClient),
                'count' => config('ping_externo.count'),
            ]);
            $response->throw();
            $results = $response->json('results') ?? [];
        } catch (\Throwable $e) {
            Log::warning('SyncPingExterno: sonda no disponible: ' . $e->getMessage());
            $this->error('Sonda no disponible: ' . $e->getMessage());
            return self::FAILURE;
        }

        $now = now();
        $rows = [];
        foreach ($results as $r) {
            $ip = $r['ip'] ?? null;
            if (!$ip || !isset($ipToClient[$ip])) {
                continue;
            }
            $loss = (int) ($r['packet_loss'] ?? 100);
            $min = $r['min_ms'] ?? null;
            $max = $r['max_ms'] ?? null;
            $rows[] = [
                'client_id'   => $ipToClient[$ip],
                'ip_address'  => $ip,
                'avg_ms'      => $r['avg_ms'] ?? null,
                'min_ms'      => $min,
                'max_ms'      => $max,
                'jitter_ms'   => ($min !== null && $max !== null) ? round($max - $min, 2) : null,
                'packet_loss' => max(0, min(100, $loss)),
                'status'      => $loss >= 100 ? 'down' : 'up',
                'recorded_at' => $now,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if ($rows) {
            DB::table('ping_externo_statistics')->insert($rows);
        }
        $this->info(count($rows) . ' mediciones externas guardadas.');
        return self::SUCCESS;
    }
}
