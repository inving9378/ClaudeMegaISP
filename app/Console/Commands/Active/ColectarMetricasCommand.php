<?php

namespace App\Console\Commands\Active;

use App\Services\Metricas\ColectorMetricas;
use Illuminate\Console\Command;
use Throwable;

/**
 * Item #988: métricas por minuto de los clientes dedicados desde su MikroTik — tráfico de su
 * VLAN (bajada/subida del cliente) y ping a las IPs de sus equipos. El schedule solo se
 * registra con METRICAS_ACTIVO; a mano, sin el interruptor, únicamente --dry-run o --forzar.
 *
 * --dry-run es el primer paso del runbook: por router imprime el host que de verdad se usó, el
 * usuario (y si es el de respaldo) y la identidad que contestó; si no es la esperada se detiene
 * ahí, sin leer nada más. Nunca guarda.
 */
class ColectarMetricasCommand extends Command
{
    protected $signature = 'metricas:colectar
        {--driver= : mikrotik | simulado (por default config metricas.driver)}
        {--cliente=* : limitar a estos clients.id}
        {--dry-run : lee e imprime todo, sin guardar nada; se detiene si un router no es el esperado}
        {--forzar : guarda aunque METRICAS_ACTIVO esté apagado}
        {--tasas-vlan= : SOLO dry-run, informativo: VLAN ID separados por coma (600,601,602) cuyas tasas en vivo se imprimen para comparar con Winbox}
        {--muestra= : SOLO dry-run: segundos entre dos lecturas de contadores para calcular bajada/subida sin guardar}
        {--paquetes= : SOLO dry-run: paquetes de ping por IP (para medir cuánto tarda con 3 o con 5)}
        {--ips-extra= : SOLO dry-run, informativo: IPs separadas por coma a las que además se hace ping desde el router (p. ej. de un dedicado sin «Datos de red»)}';

    protected $description = 'Lee tráfico de la VLAN y ping de cada cliente dedicado desde su MikroTik y lo guarda por minuto';

    public function handle(ColectorMetricas $colector): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! config('metricas.activo') && ! $dryRun && ! $this->option('forzar')) {
            $this->warn('METRICAS_ACTIVO está apagado: no se guarda nada. Usa --dry-run para probar la lectura o --forzar para guardar de todos modos.');

            return self::SUCCESS;
        }

        try {
            $driver = ColectorMetricas::driver((string) ($this->option('driver') ?: config('metricas.driver')));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $opciones = array_filter([
            'tasas_vlan'       => $this->option('tasas-vlan') ? array_map('intval', array_filter(explode(',', (string) $this->option('tasas-vlan')))) : null,
            'muestra_segundos' => $this->option('muestra') ? max(1, min(30, (int) $this->option('muestra'))) : null,
            'paquetes'         => $this->option('paquetes') ? max(1, min(10, (int) $this->option('paquetes'))) : null,
            'ips_extra'        => $this->option('ips-extra') ? array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('ips-extra')))))) : null,
        ]);
        if ($opciones !== [] && ! $dryRun) {
            $this->error('--tasas-vlan, --muestra, --paquetes e --ips-extra solo se usan con --dry-run.');

            return self::FAILURE;
        }
        $invalidas = array_filter($opciones['ips_extra'] ?? [], fn ($ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
        if ($invalidas !== []) {
            $this->error('--ips-extra: no son IPv4 válidas: ' . implode(', ', $invalidas));

            return self::FAILURE;
        }

        $r = $colector->correr($driver, array_map('intval', (array) $this->option('cliente')), $dryRun, $opciones);

        foreach ($r['routers'] as $router) {
            $usuario = match (true) {
                $router['usuario'] === null => '—',
                (bool) $router['redirigido'] => $router['usuario'] . ' (redirección de DEV: credenciales MIKROTIK_DEV_*)',
                (bool) $router['solo_lectura'] => $router['usuario'] . ' (solo lectura)',
                default => $router['usuario'] . ' (RESPALDO: no es de solo lectura)',
            };
            $linea = sprintf('Router %d «%s» · host usado %s · usuario %s · identidad «%s» (se espera «%s»)%s',
                $router['router_id'], $router['titulo'] ?? '?', $router['host'] ?? '—', $usuario,
                $router['identidad'] ?? '—', $router['esperada'] ?? '—',
                $router['duracion_ms'] !== null ? " · {$router['duracion_ms']} ms" : '');
            $router['estado'] === 'ok' ? $this->info("✔ {$linea}") : $this->error("✘ {$linea} — {$router['error']}");
        }

        if ($r['abortado']) {
            $this->error('Dry-run detenido: un router no contestó la identidad esperada. No se leyó nada más y no se guardó nada.');

            return self::FAILURE;
        }

        foreach ($r['routers'] as $router) {
            if (! empty($router['tasas_error'])) {
                $this->warn("Router {$router['router_id']}: no se pudieron leer las tasas en vivo: {$router['tasas_error']}");
            }
            if (! isset($router['tasas'])) {
                continue;
            }
            $this->line("Tasas en vivo del router {$router['router_id']} (informativo, como en Winbox: rx = entra al router = subida del cliente; tx = sale = bajada del cliente):");
            $this->table(['VLAN', 'Interfaz', 'Sobre', 'Rx (subida)', 'Tx (bajada)', 'Rx pps', 'Tx pps'],
                array_map(fn ($t) => [$t['vlan_id'], $t['nombre'], $t['padre'] ?? '—', self::mbps($t['rx_bps']), self::mbps($t['tx_bps']),
                    $t['rx_pps'] ?? '—', $t['tx_pps'] ?? '—'], $router['tasas']));
            $faltan = array_diff($opciones['tasas_vlan'] ?? [], array_column($router['tasas'], 'vlan_id'));
            if ($faltan !== []) {
                $this->line('  VLAN que no existen en ese router: ' . implode(', ', $faltan));
            }
        }

        if ($r['filas'] !== []) {
            $this->table(
                ['Cliente', 'Servicio', 'Tráfico', 'Bajada del cliente', 'Subida del cliente', 'Ping', 'IPs que responden', 'Latencia prom.', 'Pérdida'],
                array_map(fn ($f) => [
                    $f['cliente_id'], $f['servicio_id'], $f['estado'], self::mbps($f['bajada_bps']), self::mbps($f['subida_bps']),
                    $f['estado_ping'] ?? '—',
                    $f['ping_ips'] === null ? '—' : "{$f['ping_responden']} de {$f['ping_ips']}",
                    self::ms($f['lat_avg_ms']), $f['loss_pct'] === null ? '—' : $f['loss_pct'] . ' %',
                ], $r['filas'])
            );
        }

        if ($r['pings'] !== []) {
            $tardo = [];
            foreach ($r['routers'] as $router) {
                foreach ($router['ping_ms'] ?? [] as $t) {
                    $tardo[$t['ip']] = $t;
                }
            }
            $this->table(
                ['Servicio', 'IP', 'Estado', 'Recibidos', 'Mín', 'Prom', 'Máx', 'Tardó', 'Por paquete (ms)'],
                array_map(fn ($p) => [
                    $p['servicio_id'], $p['ip'], $p['estado'], "{$p['recibidos']}/{$p['enviados']}",
                    self::ms($p['lat_min_ms']), self::ms($p['lat_avg_ms']), self::ms($p['lat_max_ms']),
                    isset($tardo[$p['ip']]) ? $tardo[$p['ip']]['ms'] . ' ms' : '—',
                    self::porPaquete($tardo[$p['ip']]['detalle'] ?? []),
                ], $r['pings'])
            );
        }

        foreach ($r['routers'] as $router) {
            if (empty($router['ping_extra'])) {
                continue;
            }
            $this->line("Ping a IPs extra desde el router {$router['router_id']} (informativo, no pertenecen a ningún servicio configurado):");
            $this->table(
                ['IP', 'Recibidos', 'Mín', 'Prom', 'Máx', 'Tardó', 'Por paquete (ms)'],
                array_map(fn ($e) => $e['lectura'] === null ? [$e['ip'], 'omitido: sin tiempo', '—', '—', '—', '—', '—'] : [
                    $e['ip'],
                    $e['lectura']->error !== null ? 'error: ' . mb_substr($e['lectura']->error, 0, 60) : "{$e['lectura']->recibidos()}/{$e['lectura']->enviados}",
                    self::ms($e['lectura']->minMs()), self::ms($e['lectura']->avgMs()), self::ms($e['lectura']->maxMs()),
                    $e['ms'] . ' ms', self::porPaquete($e['lectura']->detalle),
                ], $router['ping_extra'])
            );
        }

        $l = $r['latido'];
        $this->info(sprintf(
            '%s%s · %s · routers ok %d / error %d · clientes ok %d / error %d · sin router o sin nada que medir %d · %d ms',
            $dryRun ? '[dry-run, nada guardado] ' : '',
            $l['ts'], $l['driver'], $l['routers_ok'], $l['routers_error'],
            $l['clientes_ok'], $l['clientes_error'], $l['clientes_incompletos'], $l['duracion_ms']
        ));

        return self::SUCCESS;
    }

    private static function mbps(?int $bps): string
    {
        return $bps === null ? '—' : number_format($bps / 1_000_000, 2) . ' Mbps';
    }

    private static function ms(?float $ms): string
    {
        return $ms === null ? '—' : rtrim(rtrim(number_format($ms, 3), '0'), '.') . ' ms';
    }

    /** «0.356 · 0.412 · timeout»: un valor por paquete, en el orden en que contestó el router. */
    private static function porPaquete(array $detalle): string
    {
        return $detalle === [] ? '—' : implode(' · ', array_map(
            fn ($d) => $d['ms'] !== null ? rtrim(rtrim(number_format($d['ms'], 3), '0'), '.') : $d['estado'],
            $detalle
        ));
    }
}
