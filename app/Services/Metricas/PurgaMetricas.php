<?php

namespace App\Services\Metricas;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Conservación de las métricas de dedicados (item #988): primero promedia cada bloque de 5 minutos ya
 * cerrado y después borra lo vencido. Detalle por minuto (y ping por IP) 30 días, promedios de 5 min
 * 13 meses, latidos con su propia retención. Si promediar falla no se borra nada.
 */
final class PurgaMetricas
{
    private const LOTE = 5000;

    /** @return array{promedios: int, cliente_metricas: int, cliente_metricas_ping: int, cliente_metricas_5min: int, metricas_colector_latidos: int, servicio_monitoreo_pausas: int} */
    public function correr(bool $simular = false): array
    {
        $ahora = Carbon::now();
        $cortes = [
            'cliente_metricas'          => $ahora->copy()->subDays((int) config('metricas.retencion.minuto_dias', 30)),
            'cliente_metricas_ping'     => $ahora->copy()->subDays((int) config('metricas.retencion.minuto_dias', 30)),
            'cliente_metricas_5min'     => $ahora->copy()->subMonths((int) config('metricas.retencion.promedio_5min_meses', 13)),
            'metricas_colector_latidos' => $ahora->copy()->subDays((int) config('metricas.retencion.latidos_dias', 30)),
        ];

        $resultado = ['promedios' => $simular ? 0 : $this->promediar($ahora)];
        foreach ($cortes as $tabla => $corte) {
            $resultado[$tabla] = $simular
                ? DB::table($tabla)->where('ts', '<', $corte)->count()
                : $this->borrar($tabla, 'ts', $corte);
        }
        // Las pausas explican los huecos de los promedios: duran lo mismo que ellos. Las abiertas se quedan.
        $resultado['servicio_monitoreo_pausas'] = $simular
            ? DB::table('servicio_monitoreo_pausas')->where('hasta', '<', $cortes['cliente_metricas_5min'])->count()
            : $this->borrar('servicio_monitoreo_pausas', 'hasta', $cortes['cliente_metricas_5min']);

        return $resultado;
    }

    /** Bloques cerrados desde el último ya promediado; el último se recalcula, por si quedó a medias. */
    private function promediar(Carbon $ahora): int
    {
        $hasta = $ahora->copy()->second(0)->minute($ahora->minute - $ahora->minute % 5)->format('Y-m-d H:i:s');
        $desde = DB::table('cliente_metricas_5min')->max('ts') ?? DB::table('cliente_metricas')->min('ts');
        if ($desde === null) {
            return 0;
        }

        return DB::affectingStatement(<<<'SQL'
            INSERT INTO cliente_metricas_5min
                (servicio_id, cliente_id, ts, muestras, bajada_bps, bajada_bps_max, subida_bps, subida_bps_max,
                 lat_avg_ms, lat_max_ms, loss_pct, loss_max_pct, minutos_en_linea, minutos_degradado, minutos_sin_respuesta, origen)
            SELECT servicio_id, MAX(cliente_id), ts - INTERVAL MOD(MINUTE(ts), 5) MINUTE - INTERVAL SECOND(ts) SECOND AS bloque, COUNT(*),
                   ROUND(AVG(bajada_bps)), MAX(bajada_bps), ROUND(AVG(subida_bps)), MAX(subida_bps),
                   AVG(lat_avg_ms), MAX(lat_max_ms), AVG(loss_pct), MAX(loss_pct),
                   COALESCE(SUM(estado_ping = 'en_linea'), 0), COALESCE(SUM(estado_ping = 'degradado'), 0), COALESCE(SUM(estado_ping = 'sin_respuesta'), 0),
                   MAX(origen)
            FROM cliente_metricas
            WHERE ts >= ? AND ts < ?
            GROUP BY servicio_id, bloque
            ON DUPLICATE KEY UPDATE muestras = VALUES(muestras), bajada_bps = VALUES(bajada_bps), bajada_bps_max = VALUES(bajada_bps_max),
                subida_bps = VALUES(subida_bps), subida_bps_max = VALUES(subida_bps_max), lat_avg_ms = VALUES(lat_avg_ms),
                lat_max_ms = VALUES(lat_max_ms), loss_pct = VALUES(loss_pct), loss_max_pct = VALUES(loss_max_pct),
                minutos_en_linea = VALUES(minutos_en_linea), minutos_degradado = VALUES(minutos_degradado),
                minutos_sin_respuesta = VALUES(minutos_sin_respuesta), origen = VALUES(origen)
            SQL, [$desde, $hasta]);
    }

    /** Por lotes, para no bloquear al colector que escribe cada minuto. */
    private function borrar(string $tabla, string $columna, Carbon $corte): int
    {
        $total = 0;
        do {
            $n = DB::table($tabla)->where($columna, '<', $corte)->limit(self::LOTE)->delete();
            $total += $n;
        } while ($n === self::LOTE);

        return $total;
    }
}
