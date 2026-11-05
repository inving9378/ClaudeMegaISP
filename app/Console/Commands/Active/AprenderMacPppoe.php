<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\RouterConnection;
use App\Models\Router;

/**
 * Item #576 Fase 1 — modo APRENDER (no bloquea nada): registra con qué MAC
 * (Calling-Station-Id) se conecta cada usuario PPPoE. Solo LEE radacct.
 */
class AprenderMacPppoe extends Command
{
    use RouterConnection;

    protected $signature = 'pppoe:aprender-mac {--dias=2 : Ventana de radacct a leer} {--mikrotik : Además lee caller-id de /ppp/active de cada MikroTik (solo lectura)}';

    protected $description = 'Aprende la MAC (Calling-Station-Id) de cada usuario PPPoE desde radacct (solo lectura)';

    public function handle()
    {
        $desde = now()->subDays((int) $this->option('dias'));
        try {
            $filas = DB::connection('radius')->table('radacct')
                ->where('acctstarttime', '>=', $desde)
                ->whereNotNull('callingstationid')->where('callingstationid', '!=', '')
                ->selectRaw('username, callingstationid, MAX(nasipaddress) as nas, MAX(callingstationid) as crudo, MIN(acctstarttime) as primera, MAX(acctstarttime) as ultima, COUNT(*) as n')
                ->groupBy('username', 'callingstationid')
                ->get();
        } catch (\Throwable $e) {
            $this->error('No se pudo leer radacct: ' . $e->getMessage());
            return self::FAILURE;
        }

        $nuevas = 0;
        foreach ($filas as $f) {
            $mac = strtoupper(str_replace('-', ':', trim($f->callingstationid)));
            $fila = DB::table('pppoe_mac_aprendidas')->where(['username' => $f->username, 'mac' => $mac])->first();
            if ($fila) {
                DB::table('pppoe_mac_aprendidas')->where('id', $fila->id)->update([
                    'ultima_vez' => $f->ultima,
                    'veces' => $fila->veces + $f->n,
                    'nas_ip' => $f->nas, 'mac_crudo' => $f->crudo,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('pppoe_mac_aprendidas')->insert([
                    'username' => $f->username, 'mac' => $mac, 'origen' => 'radius',
                    'primera_vez' => $f->primera, 'ultima_vez' => $f->ultima, 'veces' => $f->n,
                    'nas_ip' => $f->nas, 'mac_crudo' => $f->crudo,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $nuevas++;
            }
        }

        if ($this->option('mikrotik')) {
            $nuevas += $this->aprenderDeMikrotik();
        }

        $multi = DB::table('pppoe_mac_aprendidas')->select('username')->groupBy('username')->havingRaw('COUNT(*) > 1')->count();
        $this->info("Leídas {$filas->count()} combinaciones; nuevas: {$nuevas}; usuarios con más de una MAC: {$multi}.");
        return self::SUCCESS;
    }

    /** Fase 1b: lee caller-id de /ppp/active por router. Un router caído no frena a los demás. */
    private function aprenderDeMikrotik(): int
    {
        $nuevas = 0;
        foreach (Router::where('type_of_nas', 'Mikrotik')->get() as $router) {
            try {
                $conn = $this->getConnectionByRouter($router);
                if (!$conn) {
                    $this->warn("Router {$router->ip_host}: sin conexión, se omite.");
                    continue;
                }
                $activos = $this->getAllClientPppWithActiveConnection($conn);
            } catch (\Throwable $e) {
                $this->warn("Router {$router->ip_host}: " . $e->getMessage());
                continue;
            }
            foreach ($activos as $a) {
                $user = $a['name'] ?? null;
                $raw = trim($a['caller-id'] ?? '');
                if (!$user || !preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', $raw)) {
                    continue;
                }
                $mac = strtoupper(str_replace('-', ':', $raw));
                $fila = DB::table('pppoe_mac_aprendidas')->where(['username' => $user, 'mac' => $mac])->first();
                if ($fila) {
                    DB::table('pppoe_mac_aprendidas')->where('id', $fila->id)
                        ->update(['ultima_vez' => now(), 'veces' => $fila->veces + 1, 'updated_at' => now()]);
                } else {
                    DB::table('pppoe_mac_aprendidas')->insert([
                        'username' => $user, 'mac' => $mac, 'origen' => 'mikrotik',
                        'primera_vez' => now(), 'ultima_vez' => now(), 'veces' => 1,
                        'nas_ip' => $router->ip_host, 'mac_crudo' => $raw,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $nuevas++;
                }
            }
        }
        return $nuevas;
    }
}
