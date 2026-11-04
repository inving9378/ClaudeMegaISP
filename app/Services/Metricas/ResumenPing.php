<?php

namespace App\Services\Metricas;

use App\Models\ClienteMetrica;

/**
 * Estado del cliente en un minuto a partir del ping a cada una de sus IPs (item #988):
 * En línea si responden todas, Degradado si responde al menos una pero no todas, Sin respuesta
 * si ninguna. Una IP que no se llegó a medir (sin tiempo en el ciclo, o error de la API) no
 * cuenta para nada. Pura, para probarla sin base de datos.
 */
final class ResumenPing
{
    /**
     * @param  array<int, LecturaPing|null>  $lecturas  null = no se midió
     * @return array{ping_ips: ?int, ping_responden: ?int, lat_avg_ms: ?float, lat_max_ms: ?float, loss_pct: ?float, estado_ping: ?string}
     */
    public static function de(array $lecturas): array
    {
        $medidas = array_values(array_filter($lecturas, fn ($l) => $l instanceof LecturaPing && $l->error === null));
        if ($medidas === []) {
            return ['ping_ips' => null, 'ping_responden' => null, 'lat_avg_ms' => null, 'lat_max_ms' => null, 'loss_pct' => null, 'estado_ping' => null];
        }

        $responden = array_values(array_filter($medidas, fn (LecturaPing $l) => $l->responde()));
        $n = count($medidas);
        $r = count($responden);

        return [
            'ping_ips'       => $n,
            'ping_responden' => $r,
            'lat_avg_ms'     => $r ? round(array_sum(array_map(fn ($l) => $l->avgMs(), $responden)) / $r, 3) : null,
            'lat_max_ms'     => $r ? max(array_map(fn ($l) => $l->maxMs(), $responden)) : null,
            'loss_pct'       => round(array_sum(array_map(fn ($l) => $l->perdidaPct(), $medidas)) / $n, 2),
            'estado_ping'    => match (true) {
                $r === $n => ClienteMetrica::PING_EN_LINEA,
                $r > 0    => ClienteMetrica::PING_DEGRADADO,
                default   => ClienteMetrica::PING_SIN_RESPUESTA,
            },
        ];
    }
}
