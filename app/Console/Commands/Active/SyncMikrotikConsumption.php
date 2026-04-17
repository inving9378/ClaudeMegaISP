<?php

namespace App\Console\Commands\Active;

use App\Http\Traits\RouterConnection;
use App\Models\Router;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PEAR2\Net\RouterOS\Request as MikroTikRequest;
use PEAR2\Net\RouterOS\Response;

class SyncMikrotikConsumption extends Command
{
    use RouterConnection;

    protected $signature = 'mikrotik:sync-consumption';
    protected $description = 'Captura el consumo actual y calcula deltas diarios para estadísticas precisas';

    public function handle()
    {
        // 1. INICIAR CRONÓMETRO
        $startTotal = microtime(true);
        $today = now()->toDateString();

        $this->info("=== Iniciando recolección de datos - " . now()->format('H:i:s') . " ===");

        $routers = Router::all();
        $totalSincronizados = 0;

        foreach ($routers as $router) {
            $startRouter = microtime(true);
            $this->info("Conectando a: {$router->name}...");

            try {
                $connection = $this->getConnectionByRouter($router);
                if (!$connection) {
                    $this->error("No se pudo conectar al router {$router->id}");
                    continue;
                }

                $activeReq = new MikroTikRequest('/ppp/active/print');
                $activeReq->setArgument('.proplist', 'name,uptime,session-id,address,caller-id');
                $activeUsers = $connection->sendSync($activeReq);

                $sessionsMetadata = [];
                foreach ($activeUsers as $u) {
                    if ($u->getType() === Response::TYPE_DATA) {
                        $sessionsMetadata[$u->getProperty('name')] = [
                            'session_id' => $u->getProperty('session-id'),
                            'uptime'     => $u->getProperty('uptime'),
                            'ip'         => $u->getProperty('address'),
                            'mac'        => $u->getProperty('caller-id'),
                        ];
                    }
                }

                // B. Obtener Tráfico real de las Interfaces
                $interfaceReq = new MikroTikRequest('/interface/print');
                $interfaceReq->setArgument('.proplist', 'name,rx-byte,tx-byte');
                $interfaceReq->setQuery(\PEAR2\Net\RouterOS\Query::where('type', 'pppoe-in'));
                $interfaces = $connection->sendSync($interfaceReq);

                $count = 0;
                foreach ($interfaces as $i) {
                    if ($i->getType() === Response::TYPE_DATA) {
                        $clientName = str_replace(['<pppoe-', '>'], '', $i->getProperty('name'));

                        if (isset($sessionsMetadata[$clientName])) {
                            $meta = $sessionsMetadata[$clientName];
                            $sessionId = (string)$meta['session_id'];
                            $currentIn = (int)$i->getProperty('rx-byte');
                            $currentOut = (int)$i->getProperty('tx-byte');
                            $prev = DB::table('internet_consumptions')
                                ->where('session_id', $sessionId)
                                ->first(['bytes_in', 'bytes_out']);
                            $deltaIn = 0;
                            $deltaOut = 0;

                            if ($prev) {
                                // Si el contador subió, restamos para saber el consumo del intervalo
                                if ($currentIn >= $prev->bytes_in) {
                                    $deltaIn = $currentIn - $prev->bytes_in;
                                    $deltaOut = $currentOut - $prev->bytes_out;
                                } else {
                                    // Si el contador bajó, el router reinició contadores: el delta es el total actual
                                    $deltaIn = $currentIn;
                                    $deltaOut = $currentOut;
                                }
                            } else {
                                // Es la primera vez que vemos esta sesión: el delta es el total actual
                                $deltaIn = $currentIn;
                                $deltaOut = $currentOut;
                            }

                            // 2. Si hubo consumo nuevo, lo sumamos a la tabla diaria (Acumulador)
                            if ($deltaIn > 0 || $deltaOut > 0) {
                                DB::table('daily_internet_consumptions')->updateOrInsert(
                                    ['client_name' => $clientName, 'date' => $today],
                                    [
                                        'bytes_in'   => DB::raw("bytes_in + $deltaIn"),
                                        'bytes_out'  => DB::raw("bytes_out + $deltaOut"),
                                        'updated_at' => now()
                                    ]
                                );
                            }

                            // --- FIN LÓGICA DE DELTAS ---

                            // 3. Actualizamos la tabla de sesiones (Estado actual "Live")
                            DB::table('internet_consumptions')->updateOrInsert(
                                ['session_id' => $sessionId],
                                [
                                    'client_name'   => $clientName,
                                    'bytes_in'      => $currentIn,
                                    'bytes_out'     => $currentOut,
                                    'uptime'        => $this->parseMikrotikUptimeToSeconds($meta['uptime']),
                                    'ip_address'    => $meta['ip'],
                                    'mac_address'   => $meta['mac'],
                                    'nas_ip'        => $router->ip_host,
                                    'date_recorded' => $today,
                                    'updated_at'    => now()
                                ]
                            );
                            $count++;
                        }
                    }
                }

                $totalSincronizados += $count;
                $endRouter = microtime(true);
                $timeRouter = round($endRouter - $startRouter, 2);

                $this->info("✔ {$router->name}: $count clientes sincronizados en {$timeRouter} segundos.");

            } catch (\Exception $e) {
                $this->error("✘ Error en router {$router->id}: " . $e->getMessage());
                Log::error("Error sincronizando consumo: " . $e->getMessage());
            }
        }

        // 2. FINALIZAR CRONÓMETRO TOTAL
        $endTotal = microtime(true);
        $executionTime = round($endTotal - $startTotal, 2);

        $this->newLine();
        $this->info("====================================================");
        $this->info(" PROCESO COMPLETADO");
        $this->info(" Total Clientes: $totalSincronizados");
        $this->info(" Tiempo Total  : {$executionTime} segundos");
        $this->info("====================================================");

        if ($executionTime > 60) {
            Log::warning("El comando mikrotik:sync-consumption está tardando mucho: {$executionTime}s");
        }
    }
}
