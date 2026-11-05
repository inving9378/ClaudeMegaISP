<?php

namespace App\Modules\Addons\Roadmap\Services;

use Illuminate\Support\Carbon;

/**
 * #1068 — Cuánto se ha usado de la suscripción de Claude (ventana de 5 horas y semanal) que mueve a
 * las terminales del circuito. No hay API pública para leerlo. Dos fuentes escriben el mismo archivo:
 *  - cada vuelta del circuito (`claude -p --output-format stream-json`, #1117) trae eventos
 *    `rate_limit_event` con el uso de las dos ventanas; el scheduler los lee de su `.jsonl`
 *    ({@see actualizarDesdeVueltas()}), así que mientras haya trabajo la medición es de minutos;
 *  - la línea de estado de Claude Code (`deploy/circuito/cuota-plan-statusline.sh`), si está activa.
 * Cada lectura lleva su edad: un dato viejo se dice viejo.
 */
class CuotaPlanService
{
    /** Más de esto sin medición nueva y la Torre lo marca como dato viejo. */
    public const MINUTOS_VIGENTE = 60;

    public function leer(): ?array
    {
        $ruta = (string) config('circuito.cuota_plan.archivo');
        $j = is_file($ruta) ? json_decode((string) @file_get_contents($ruta), true) : null;
        if (! is_array($j) || empty($j['rate_limits']) || empty($j['medido_at'])) {
            return null;
        }

        $medido = Carbon::createFromTimestamp((int) $j['medido_at']);

        return [
            'cinco_horas' => $this->ventana($j['rate_limits']['five_hour'] ?? null),
            'semana'      => $this->ventana($j['rate_limits']['seven_day'] ?? null),
            'medido_at'   => $medido->toIso8601String(),
            'medido_hace' => $medido->locale('es')->diffForHumans(),
            'vieja'       => $medido->diffInMinutes(now()) > self::MINUTOS_VIGENTE,
        ];
    }

    /**
     * Toma el `rate_limit_event` más reciente de los registros de vuelta y lo deja en el archivo de
     * la cuota, con la misma forma que la línea de estado. Solo escribe si la medición es más nueva
     * que la guardada. Nunca lanza: sin registros o sin evento, no cambia nada.
     */
    public function actualizarDesdeVueltas(): bool
    {
        $dir = (string) config('circuito.cuota_plan.logs_vueltas');
        $archivos = $dir !== '' ? (glob(rtrim($dir, '/') . '/*.jsonl') ?: []) : [];
        usort($archivos, fn ($a, $b) => (int) @filemtime($b) <=> (int) @filemtime($a));

        foreach (array_slice($archivos, 0, 6) as $f) {
            $info = $this->ultimoEvento($f);
            if ($info === null) {
                continue;
            }
            $medido = (int) @filemtime($f);
            $ruta   = (string) config('circuito.cuota_plan.archivo');
            $previo = is_file($ruta) ? json_decode((string) @file_get_contents($ruta), true) : null;
            if (is_array($previo) && (int) ($previo['medido_at'] ?? 0) >= $medido) {
                return false;
            }
            $limites = [];
            foreach (['five_hour', 'seven_day'] as $k) {
                $w = $info['unifiedWindows'][$k] ?? null;
                if (is_array($w) && isset($w['utilization'])) {
                    $limites[$k] = ['used_percentage' => round((float) $w['utilization'] * 100, 1), 'resets_at' => (int) ($w['resetsAt'] ?? 0)];
                }
            }
            if ($limites === []) {
                continue;
            }
            $tmp = $ruta . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, json_encode(['medido_at' => $medido, 'fuente' => 'vuelta', 'rate_limits' => $limites])) === false) {
                return false;
            }
            @chmod($tmp, 0664);

            return @rename($tmp, $ruta);
        }

        return false;
    }

    /** El último `rate_limit_info` de un registro de vuelta (lee solo el final del archivo). */
    private function ultimoEvento(string $archivo): ?array
    {
        $tam   = (int) @filesize($archivo);
        $texto = (string) @file_get_contents($archivo, false, null, max(0, $tam - 400000));
        $info  = null;
        foreach (explode("\n", $texto) as $linea) {
            if (! str_contains($linea, '"rate_limit_event"')) {
                continue;
            }
            $j = json_decode($linea, true);
            if (is_array($j) && is_array($j['rate_limit_info'] ?? null)) {
                $info = $j['rate_limit_info'];
            }
        }

        return $info;
    }

    private function ventana(?array $v): ?array
    {
        if (! is_array($v) || ! isset($v['used_percentage'])) {
            return null;
        }
        $reinicia = isset($v['resets_at']) ? Carbon::createFromTimestamp((int) $v['resets_at']) : null;

        // La ventana ya se reinició después de la medición: el porcentaje guardado ya no vale.
        if ($reinicia && $reinicia->isPast()) {
            return ['pct' => null, 'reiniciada' => true, 'reinicia' => null, 'reinicia_texto' => null];
        }

        return [
            'pct'            => (int) floor((float) $v['used_percentage']),
            'reiniciada'     => false,
            'reinicia'       => $reinicia?->toIso8601String(),
            'reinicia_texto' => $reinicia ? $this->cuando($reinicia) : null,
        ];
    }

    private function cuando(Carbon $c): string
    {
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

        return ($c->isToday() ? 'hoy' : ($c->isTomorrow() ? 'mañana' : 'el ' . $dias[$c->dayOfWeek])) . ' a las ' . $c->format('H:i');
    }
}
