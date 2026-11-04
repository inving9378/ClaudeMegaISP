<?php

namespace App\Services\Metricas;

use App\Models\ClienteMetrica;

/**
 * Histórico de un servicio dedicado a partir de sus minutos (item #988, Fase 4): indicadores del
 * periodo, eventos y series agregadas para las gráficas. Pura (recibe los minutos ya leídos) para
 * probarla sin base de datos; la alimenta HistoricoServicioDedicado.
 *
 * Un minuto «medido» tiene fila y estado de ping. Un minuto sin fila, o con fila pero sin ping
 * (router sin conexión, ciclo sin tiempo...), es un hueco del colector: no cuenta como caída del
 * cliente y en las gráficas es un corte de línea, nunca un cero.
 */
final class HistoricoMinutos
{
    public const SIN_RESPUESTA = 'sin_respuesta';
    public const DEGRADADO = 'degradado';
    public const PERDIDA_ALTA = 'perdida_alta';
    public const LATENCIA_ALTA = 'latencia_alta';
    public const SIN_DATOS = 'sin_datos';

    /** Estados de tráfico en los que el colector no leyó los contadores (no son base de la siguiente diferencia). */
    private const SIN_CONTADORES = ['router_sin_conexion', 'router_no_verificado', 'omitido', 'error', 'sin_interfaz', 'interfaz_no_encontrada'];

    private const REGLAS = [
        'perdida_alta_pct'        => 20,
        'perdida_alta_minutos'    => 5,
        'latencia_alta_factor'    => 10,
        'latencia_alta_minimo_ms' => 2,
        'hueco_max_segundos'      => 300,
    ];

    private readonly array $reglas;

    private ?float $latenciaPeriodo = null;

    /**
     * @param  int  $desde  epoch del primer minuto que se evalúa (segundos en 0)
     * @param  int  $hasta  epoch del fin, exclusivo: no se evalúan minutos >= $hasta
     * @param  array<int, array{estado: string, estado_ping: ?string, lat_avg_ms: ?float, lat_max_ms: ?float, loss_pct: ?float, bajada_bps: ?int, subida_bps: ?int, ping_ips: ?int}>  $filas  por epoch del minuto
     * @param  array<int, array<string, array{enviados: int, recibidos: int, lat_avg_ms: ?float, lat_max_ms: ?float}>>  $pings  por epoch del minuto y por IP
     * @param  array<int, float>  $latenciaBase  por epoch de la hora: latencia promedio del servicio en las 24 h anteriores
     */
    public function __construct(
        private readonly int $desde,
        private readonly int $hasta,
        private readonly array $filas,
        private readonly array $pings = [],
        private readonly array $latenciaBase = [],
        array $reglas = [],
    ) {
        $this->reglas = $reglas + self::REGLAS;
    }

    /**
     * Disponibilidad = minutos con al menos una IP respondiendo ÷ minutos medidos (los huecos del
     * colector no cuentan). Latencia y pérdida, promedio de los minutos medidos y su máximo. Tráfico:
     * picos y totales (cada fila es el promedio desde la lectura anterior de contadores).
     */
    public function indicadores(): array
    {
        $medidos = $responden = $sinRespuesta = $sinDatos = 0;
        $latSuma = $perdSuma = 0.0;
        $latN = $perdN = 0;
        $latMax = $perdMax = null;
        $picoBajada = $picoSubida = null;
        $bytesBajada = $bytesSubida = 0.0;
        $lecturaAnterior = null;

        for ($t = $this->desde; $t < $this->hasta; $t += 60) {
            $f = $this->filas[$t] ?? null;

            if ($f !== null && ! in_array($f['estado'], self::SIN_CONTADORES, true)) {
                $dur = $lecturaAnterior !== null && $t - $lecturaAnterior <= $this->reglas['hueco_max_segundos'] ? $t - $lecturaAnterior : 60;
                $lecturaAnterior = $t;
                if ($f['bajada_bps'] !== null) {
                    $picoBajada = max($picoBajada ?? 0, (int) $f['bajada_bps']);
                    $bytesBajada += $f['bajada_bps'] * $dur / 8;
                }
                if ($f['subida_bps'] !== null) {
                    $picoSubida = max($picoSubida ?? 0, (int) $f['subida_bps']);
                    $bytesSubida += $f['subida_bps'] * $dur / 8;
                }
            }

            if (! self::medido($f)) {
                $sinDatos++;

                continue;
            }
            $medidos++;
            if ($f['estado_ping'] === ClienteMetrica::PING_SIN_RESPUESTA) {
                $sinRespuesta++;
            } else {
                $responden++;
            }
            if ($f['lat_avg_ms'] !== null) {
                $latSuma += $f['lat_avg_ms'];
                $latN++;
            }
            if ($f['lat_max_ms'] !== null) {
                $latMax = max($latMax ?? 0.0, (float) $f['lat_max_ms']);
            }
            if ($f['loss_pct'] !== null) {
                $perdSuma += $f['loss_pct'];
                $perdN++;
                $perdMax = max($perdMax ?? 0.0, (float) $f['loss_pct']);
            }
        }

        return [
            'minutos_medidos'       => $medidos,
            'minutos_sin_respuesta' => $sinRespuesta,
            'minutos_sin_datos'     => $sinDatos,
            'disponibilidad_pct'    => $medidos > 0 ? round($responden / $medidos * 100, 2) : null,
            'latencia_prom_ms'      => $latN > 0 ? round($latSuma / $latN, 3) : null,
            'latencia_max_ms'       => $latMax,
            'perdida_prom_pct'      => $perdN > 0 ? round($perdSuma / $perdN, 2) : null,
            'perdida_max_pct'       => $perdMax,
            'pico_bajada_bps'       => $picoBajada,
            'pico_subida_bps'       => $picoSubida,
            'total_bajada_bytes'    => (int) round($bytesBajada),
            'total_subida_bytes'    => (int) round($bytesSubida),
        ];
    }

    /**
     * Eventos del periodo, el más reciente primero:
     * - Sin respuesta: ningún equipo responde (1 min o más).
     * - Degradado: responde solo una parte de los equipos.
     * - Pérdida alta: pérdida de los equipos QUE RESPONDEN de 20 % o más durante 5 min o más (un
     *   equipo que no responde ya es «Degradado» o «Sin respuesta»; no se repite aquí).
     * - Latencia alta: más de 10× el promedio de las 24 h anteriores y más de 2 ms.
     * - Sin datos del colector: no hubo lectura (no cuenta como caída).
     *
     * @return array<int, array{tipo: string, inicio: int, fin: int, minutos: int, detalle: string}>
     */
    public function eventos(): array
    {
        $minimos = [self::PERDIDA_ALTA => (int) $this->reglas['perdida_alta_minutos']];
        $abiertas = [];
        $eventos = [];

        for ($t = $this->desde; $t <= $this->hasta; $t += 60) {
            $activos = $t < $this->hasta ? $this->tiposDelMinuto($t) : [];
            foreach ([self::SIN_DATOS, self::SIN_RESPUESTA, self::DEGRADADO, self::PERDIDA_ALTA, self::LATENCIA_ALTA] as $tipo) {
                if (isset($activos[$tipo])) {
                    $abiertas[$tipo][] = $t;
                } elseif (isset($abiertas[$tipo])) {
                    if (count($abiertas[$tipo]) >= ($minimos[$tipo] ?? 1)) {
                        $eventos[] = $this->evento($tipo, $abiertas[$tipo]);
                    }
                    unset($abiertas[$tipo]);
                }
            }
        }

        usort($eventos, fn ($a, $b) => [$b['inicio'], $a['tipo']] <=> [$a['inicio'], $b['tipo']]);

        return $eventos;
    }

    /**
     * Series para las gráficas, en cubetas de $paso segundos desde $ejeDesde: promedio y máximo de
     * cada cubeta (para no ocultar picos). Una cubeta sin minutos medidos va con null (corte de
     * línea). Las cubetas que empiezan en $ultimo o después (el futuro de «Hoy») no se incluyen.
     *
     * @param  string[]  $ips  en el orden de la leyenda
     * @return array{latencia: array<string, array>, perdida: array, bajada: array, subida: array}
     */
    public function series(int $ejeDesde, int $ejeHasta, int $paso, array $ips, int $ultimo): array
    {
        $n = max(0, (int) ceil((min($ejeHasta, $ultimo) - $ejeDesde) / $paso));
        $vacio = fn () => array_fill(0, $n, [0.0, 0, null]);
        $lat = [];
        foreach ($ips as $ip) {
            $lat[$ip] = $vacio();
        }
        $perdida = $bajada = $subida = $vacio();

        foreach ($this->filas as $t => $f) {
            $k = intdiv($t - $ejeDesde, $paso);
            if ($t < $ejeDesde || $k >= $n) {
                continue;
            }
            if (self::medido($f) && $f['loss_pct'] !== null) {
                self::sumar($perdida[$k], (float) $f['loss_pct'], (float) $f['loss_pct']);
            }
            if ($f['bajada_bps'] !== null) {
                self::sumar($bajada[$k], (float) $f['bajada_bps'], (float) $f['bajada_bps']);
            }
            if ($f['subida_bps'] !== null) {
                self::sumar($subida[$k], (float) $f['subida_bps'], (float) $f['subida_bps']);
            }
        }
        foreach ($this->pings as $t => $porIp) {
            $k = intdiv($t - $ejeDesde, $paso);
            if ($t < $ejeDesde || $k >= $n) {
                continue;
            }
            foreach ($porIp as $ip => $p) {
                if (isset($lat[$ip]) && $p['recibidos'] > 0 && $p['lat_avg_ms'] !== null) {
                    self::sumar($lat[$ip][$k], (float) $p['lat_avg_ms'], (float) ($p['lat_max_ms'] ?? $p['lat_avg_ms']));
                }
            }
        }

        $puntos = function (array $cubetas, int $decimales) use ($ejeDesde, $paso) {
            $salida = [];
            foreach ($cubetas as $k => [$suma, $cuenta, $max]) {
                $salida[] = [
                    ($ejeDesde + $k * $paso + intdiv($paso, 2)) * 1000,
                    $cuenta > 0 ? round($suma / $cuenta, $decimales) : null,
                    $cuenta > 0 ? round($max, $decimales) : null,
                ];
            }

            return $salida;
        };

        return [
            'latencia' => array_map(fn ($c) => $puntos($c, 3), $lat),
            'perdida'  => $puntos($perdida, 2),
            'bajada'   => $puntos($bajada, 0),
            'subida'   => $puntos($subida, 0),
        ];
    }

    /**
     * Franjas para sombrear: «Sin respuesta» y «Sin datos del colector», en epoch ms.
     *
     * @param  array  $eventos  los de eventos()
     */
    public static function bandas(array $eventos): array
    {
        $bandas = [self::SIN_RESPUESTA => [], self::SIN_DATOS => []];
        foreach (array_reverse($eventos) as $e) {
            if (isset($bandas[$e['tipo']])) {
                $bandas[$e['tipo']][] = [$e['inicio'] * 1000, $e['fin'] * 1000];
            }
        }

        return $bandas;
    }

    private static function medido(?array $f): bool
    {
        return $f !== null && $f['estado_ping'] !== null;
    }

    private static function sumar(array &$cubeta, float $valor, float $max): void
    {
        $cubeta[0] += $valor;
        $cubeta[1]++;
        $cubeta[2] = $cubeta[2] === null ? $max : max($cubeta[2], $max);
    }

    /** @return array<string, true> */
    private function tiposDelMinuto(int $t): array
    {
        $f = $this->filas[$t] ?? null;
        if (! self::medido($f)) {
            return [self::SIN_DATOS => true];
        }

        $tipos = [];
        if ($f['estado_ping'] === ClienteMetrica::PING_SIN_RESPUESTA) {
            $tipos[self::SIN_RESPUESTA] = true;
        } elseif ($f['estado_ping'] === ClienteMetrica::PING_DEGRADADO) {
            $tipos[self::DEGRADADO] = true;
        }

        $parcial = $this->perdidaDeLosQueResponden($t);
        if ($parcial !== null && $parcial >= $this->reglas['perdida_alta_pct']) {
            $tipos[self::PERDIDA_ALTA] = true;
        }

        $lat = $f['lat_avg_ms'];
        if ($lat !== null && $lat > $this->reglas['latencia_alta_minimo_ms']) {
            $base = $this->latenciaBase[$t - $t % 3600] ?? $this->latenciaDelPeriodo();
            if ($base !== null && $lat > $this->reglas['latencia_alta_factor'] * $base) {
                $tipos[self::LATENCIA_ALTA] = true;
            }
        }

        return $tipos;
    }

    /** Pérdida promedio de las IPs que respondieron en ese minuto (null si no respondió ninguna). */
    private function perdidaDeLosQueResponden(int $t): ?float
    {
        $suma = 0.0;
        $n = 0;
        foreach ($this->pings[$t] ?? [] as $p) {
            if ($p['recibidos'] > 0 && $p['enviados'] > 0) {
                $suma += (1 - $p['recibidos'] / $p['enviados']) * 100;
                $n++;
            }
        }

        return $n > 0 ? $suma / $n : null;
    }

    /** Respaldo de la latencia «reciente» cuando no hay datos de las 24 h anteriores: la del periodo. */
    private function latenciaDelPeriodo(): ?float
    {
        if ($this->latenciaPeriodo === null) {
            $valores = [];
            foreach ($this->filas as $f) {
                if (self::medido($f) && $f['lat_avg_ms'] !== null) {
                    $valores[] = (float) $f['lat_avg_ms'];
                }
            }
            $this->latenciaPeriodo = $valores === [] ? -1.0 : array_sum($valores) / count($valores);
        }

        return $this->latenciaPeriodo < 0 ? null : $this->latenciaPeriodo;
    }

    /** @param  int[]  $minutos */
    private function evento(string $tipo, array $minutos): array
    {
        $inicio = $minutos[0];

        return [
            'tipo'    => $tipo,
            'inicio'  => $inicio,
            'fin'     => end($minutos) + 60,
            'minutos' => count($minutos),
            'detalle' => match ($tipo) {
                self::SIN_RESPUESTA => $this->detalleSinRespuesta($minutos),
                self::DEGRADADO     => $this->detalleDegradado($minutos),
                self::PERDIDA_ALTA  => 'Pérdida promedio ' . self::numero($this->promedio($minutos, fn ($t) => $this->perdidaDeLosQueResponden($t)), 0) . ' %',
                self::LATENCIA_ALTA => 'Latencia promedio ' . self::numero($this->promedio($minutos, fn ($t) => $this->filas[$t]['lat_avg_ms']), 1) . ' ms',
                self::SIN_DATOS     => $this->detalleSinDatos($minutos),
            },
        ];
    }

    private function detalleSinRespuesta(array $minutos): string
    {
        $base = (int) ($this->filas[$minutos[0]]['ping_ips'] ?? 0) === 1 ? 'El equipo no respondió' : 'Ningún equipo respondió';
        $caida = array_filter($minutos, fn ($t) => ($this->filas[$t]['estado'] ?? null) === 'interfaz_caida');

        return count($caida) === count($minutos) ? $base . ' · interfaz VLAN caída' : $base;
    }

    private function detalleDegradado(array $minutos): string
    {
        $conjuntos = [];
        $cuentas = [];
        foreach ($minutos as $t) {
            $responden = array_keys(array_filter($this->pings[$t] ?? [], fn ($p) => $p['recibidos'] > 0));
            $conjuntos[implode(',', $responden)] = $responden;
            $cuentas[($this->filas[$t]['ping_responden'] ?? '?') . '/' . ($this->filas[$t]['ping_ips'] ?? '?')] = true;
        }

        if (count($conjuntos) === 1 && reset($conjuntos) !== []) {
            return 'Responde solo ' . self::lista(array_map(fn ($ip) => self::octeto($ip), reset($conjuntos)));
        }
        if (count($cuentas) === 1) {
            [$r, $n] = explode('/', array_key_first($cuentas));

            return "Responden {$r} de {$n} equipos";
        }

        return 'Responde solo una parte de los equipos';
    }

    private function detalleSinDatos(array $minutos): string
    {
        $motivos = [];
        foreach ($minutos as $t) {
            $estado = $this->filas[$t]['estado'] ?? 'sin_fila';
            $motivos[$estado] = ($motivos[$estado] ?? 0) + 1;
        }
        arsort($motivos);

        return match (array_key_first($motivos)) {
            'sin_fila'             => 'No hubo lectura del colector',
            'router_sin_conexion'  => 'El router no contestó',
            'router_no_verificado' => 'El router no contestó la identidad esperada',
            'omitido'              => 'No alcanzó el tiempo del ciclo',
            default                => 'No se midió el ping',
        };
    }

    private function promedio(array $minutos, callable $valor): float
    {
        $valores = array_filter(array_map($valor, $minutos), fn ($v) => $v !== null);

        return $valores === [] ? 0.0 : array_sum($valores) / count($valores);
    }

    private static function numero(float $v, int $decimales): string
    {
        return number_format($v, $decimales, '.', ',');
    }

    /** «192.0.2.10» → «.10» (como lo dice la especificación); lo que no sea IPv4, tal cual. */
    private static function octeto(string $ip): string
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? '.' . substr($ip, strrpos($ip, '.') + 1) : $ip;
    }

    private static function lista(array $partes): string
    {
        return count($partes) <= 1 ? implode('', $partes) : implode(', ', array_slice($partes, 0, -1)) . ' y ' . end($partes);
    }
}
