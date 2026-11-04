<?php

namespace App\Services\Metricas;

use App\Models\ClienteMetrica;
use App\Models\ServicioMonitoreo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * «Histórico del servicio» de Estadísticas → Internet de un dedicado (item #988, Fase 4): para un
 * servicio y un periodo arma, agregado en el servidor, las series de latencia y pérdida por IP,
 * la de tráfico, los indicadores, los eventos y el origen de los datos.
 *
 * Lee solo las tablas nuevas por servicio_id (cliente_metricas, cliente_metricas_ping), nunca
 * ping_statistics (#999). Los cálculos viven en HistoricoMinutos (pura, con pruebas).
 */
class HistoricoServicioDedicado
{
    public const PERIODOS = ['hoy', '24h', '7d', '30d'];

    /**
     * Cubeta de cada periodo (para quedar en 120 a 200 puntos por serie), separación de las
     * líneas de la cuadrícula del eje X y cada cuánto lleva etiqueta.
     */
    private const EJE = [
        'hoy' => ['paso' => 600, 'cuadricula' => 3600, 'marcas' => '4h'],
        '24h' => ['paso' => 600, 'cuadricula' => 3600, 'marcas' => '4h'],
        '7d'  => ['paso' => 3600, 'cuadricula' => 86400, 'marcas' => 'dia'],
        '30d' => ['paso' => 14400, 'cuadricula' => 86400, 'marcas' => 'semana'],
    ];

    public function historico(int $servicioId, string $periodo, ?int $ahora = null): array
    {
        if (! in_array($periodo, self::PERIODOS, true)) {
            throw new InvalidArgumentException("Periodo no válido: {$periodo}");
        }

        $ahora ??= time();
        [$desde, $ejeDesde, $ejeHasta] = $this->ventana($periodo, $ahora);
        $eje = self::EJE[$periodo];

        $monitoreo = ServicioMonitoreo::with(['ips', 'router:id,title'])->where('servicio_id', $servicioId)->first();
        $primera = DB::table('cliente_metricas')->where('servicio_id', $servicioId)->min('ts');
        $primeraT = $primera !== null ? strtotime($primera) : null;

        // Se evalúa desde la primera lectura del servicio (antes no existía su monitoreo: no es un
        // hueco del colector) y hasta el minuto anterior al actual, que puede no haberse leído aún.
        $desdeEval = $primeraT !== null ? max($desde, $primeraT) : $ahora;
        $hastaEval = max($desdeEval, $ahora - $ahora % 60 - 60);

        [$filas, $simulado] = $this->filas($servicioId, $desde, $ahora);
        [$pings, $ipsVistas, $paquetes] = $this->pings($servicioId, $desde, $ahora);

        $calculo = new HistoricoMinutos($desdeEval, $hastaEval, $filas, $pings, $this->latenciaBase($servicioId, $desde, $ahora), [
            'perdida_alta_pct'        => config('metricas.eventos.perdida_alta_pct', 20),
            'perdida_alta_minutos'    => config('metricas.eventos.perdida_alta_minutos', 5),
            'latencia_alta_factor'    => config('metricas.eventos.latencia_alta_factor', 10),
            'latencia_alta_minimo_ms' => config('metricas.eventos.latencia_alta_minimo_ms', 2),
            'hueco_max_segundos'      => config('metricas.hueco_max_segundos', 300),
        ]);

        $ips = $this->ips($monitoreo, $ipsVistas);
        $series = $calculo->series($ejeDesde, $ejeHasta, $eje['paso'], array_column($ips, 'ip'), $ahora);
        $eventos = $calculo->eventos();
        $plan = DB::table('client_internet_services as s')->leftJoin('internets as i', 'i.id', '=', 's.internet_id')
            ->where('s.id', $servicioId)->first(['i.title', 'i.download_speed', 'i.upload_speed']);
        $latido = DB::table('metricas_colector_latidos')->max('ts');
        $latidoSeg = $latido !== null ? max(0, $ahora - strtotime($latido)) : null;

        return [
            'servicio_id' => $servicioId,
            'periodo'     => $periodo,
            'zona'        => config('app.timezone'),
            'simulado'    => $simulado,
            'rango'       => [
                'desde'         => $desde * 1000,
                'hasta'         => $ahora * 1000,
                'eje_desde'     => $ejeDesde * 1000,
                'eje_hasta'     => $ejeHasta * 1000,
                'paso_seg'      => $eje['paso'],
                'cuadricula_seg' => $eje['cuadricula'],
                'marcas'        => $eje['marcas'],
            ],
            'indicadores' => $calculo->indicadores(),
            'latencia'    => [
                'ips'     => array_map(fn ($ip) => $ip + ['puntos' => $series['latencia'][$ip['ip']]], $ips),
                'perdida' => $series['perdida'],
            ],
            'trafico'     => [
                'bajada' => $series['bajada'],
                'subida' => $series['subida'],
                // Del plan del servicio (internets.download_speed viene en kbps). Si el router tiene
                // otra (#1000), aquí no se adivina: la pantalla lo rotula «según el plan».
                'velocidad_contratada_bps' => $plan?->download_speed ? (int) $plan->download_speed * 1000 : null,
                'plan'                     => $plan?->title,
            ],
            'bandas'      => HistoricoMinutos::bandas($eventos),
            'eventos'     => ['total' => count($eventos), 'lista' => array_map(fn ($e) => [
                'tipo'    => $e['tipo'],
                'inicio'  => $e['inicio'] * 1000,
                'fin'     => $e['fin'] * 1000,
                'minutos' => $e['minutos'],
                'detalle' => $e['detalle'],
            ], array_slice($eventos, 0, 200))],
            'origen'      => [
                'ips'                 => array_values(array_filter($ips, fn ($ip) => $ip['configurada'])),
                'paquetes_por_minuto' => $paquetes ?? (int) config('metricas.ping.paquetes', 3),
                'router'              => $monitoreo?->router?->title,
                'interfaz'            => $monitoreo?->interfaz_vlan,
                'vlan_id'             => $monitoreo?->vlan_id,
                'monitoreo_desde'     => $primeraT !== null ? $primeraT * 1000 : null,
                'colector'            => [
                    'ultimo'   => $latido !== null ? strtotime($latido) * 1000 : null,
                    'segundos' => $latidoSeg,
                    'operando' => $latidoSeg !== null && $latidoSeg <= (int) config('metricas.sin_datos_segundos', 180),
                ],
                'conservacion'        => [
                    'minuto_dias'         => (int) config('metricas.retencion.minuto_dias', 30),
                    'promedio_5min_meses' => (int) config('metricas.retencion.promedio_5min_meses', 13),
                ],
            ],
        ];
    }

    /**
     * Ventana de datos y del eje X. «Hoy»: de las 00:00 a ahora, eje de 00:00 a 24:00. «Últimas
     * 24 h»: eje redondeado a horas para que las marcas caigan en horas cerradas. «7 días» y «30
     * días»: días completos hasta hoy.
     *
     * @return array{0: int, 1: int, 2: int} desde, eje desde, eje hasta (epoch)
     */
    private function ventana(string $periodo, int $ahora): array
    {
        $hoy = strtotime('today', $ahora);
        $manana = strtotime('tomorrow', $ahora);

        return match ($periodo) {
            'hoy' => [$hoy, $hoy, $manana],
            '24h' => [$ahora - $ahora % 60 - 86400, $ahora - $ahora % 3600 - 86400, $ahora % 3600 === 0 ? $ahora : $ahora - $ahora % 3600 + 3600],
            '7d'  => [strtotime('-6 days', $hoy), strtotime('-6 days', $hoy), $manana],
            '30d' => [strtotime('-29 days', $hoy), strtotime('-29 days', $hoy), $manana],
        };
    }

    /** @return array{0: array<int, array>, 1: bool} filas por epoch del minuto y si hay datos simulados */
    private function filas(int $servicioId, int $desde, int $hasta): array
    {
        $filas = [];
        $simulado = false;
        $cursor = DB::table('cliente_metricas')->where('servicio_id', $servicioId)
            ->whereBetween('ts', [date('Y-m-d H:i:s', $desde), date('Y-m-d H:i:s', $hasta)])
            ->orderBy('ts')
            ->select(['ts', 'estado', 'estado_ping', 'lat_avg_ms', 'lat_max_ms', 'loss_pct', 'bajada_bps', 'subida_bps', 'ping_ips', 'ping_responden', 'origen'])
            ->cursor();

        foreach ($cursor as $r) {
            $filas[strtotime($r->ts)] = [
                'estado'         => $r->estado,
                'estado_ping'    => $r->estado_ping,
                'lat_avg_ms'     => $r->lat_avg_ms !== null ? (float) $r->lat_avg_ms : null,
                'lat_max_ms'     => $r->lat_max_ms !== null ? (float) $r->lat_max_ms : null,
                'loss_pct'       => $r->loss_pct !== null ? (float) $r->loss_pct : null,
                'bajada_bps'     => $r->bajada_bps !== null ? (int) $r->bajada_bps : null,
                'subida_bps'     => $r->subida_bps !== null ? (int) $r->subida_bps : null,
                'ping_ips'       => $r->ping_ips !== null ? (int) $r->ping_ips : null,
                'ping_responden' => $r->ping_responden !== null ? (int) $r->ping_responden : null,
            ];
            $simulado = $simulado || $r->origen === ClienteMetrica::ORIGEN_SIMULADO;
        }

        return [$filas, $simulado];
    }

    /** @return array{0: array, 1: string[], 2: ?int} pings por minuto e IP, IPs vistas, paquetes de la última medición */
    private function pings(int $servicioId, int $desde, int $hasta): array
    {
        $pings = [];
        $vistas = [];
        $paquetes = null;
        $cursor = DB::table('cliente_metricas_ping')->where('servicio_id', $servicioId)
            ->whereBetween('ts', [date('Y-m-d H:i:s', $desde), date('Y-m-d H:i:s', $hasta)])
            ->orderBy('ts')
            ->select(['ts', 'ip', 'enviados', 'recibidos', 'lat_avg_ms', 'lat_max_ms', 'estado'])
            ->cursor();

        foreach ($cursor as $p) {
            if ($p->estado === 'omitido' || $p->estado === 'error') {
                continue; // no se midió: no cuenta para nada (igual que en ResumenPing)
            }
            $pings[strtotime($p->ts)][$p->ip] = [
                'enviados'   => (int) $p->enviados,
                'recibidos'  => (int) $p->recibidos,
                'lat_avg_ms' => $p->lat_avg_ms !== null ? (float) $p->lat_avg_ms : null,
                'lat_max_ms' => $p->lat_max_ms !== null ? (float) $p->lat_max_ms : null,
            ];
            $vistas[$p->ip] = true;
            $paquetes = (int) $p->enviados;
        }

        return [$pings, array_keys($vistas), $paquetes];
    }

    /**
     * Latencia «reciente» del cliente para «Latencia alta»: por cada hora, el promedio de las 24 h
     * anteriores (sin contar esa hora).
     *
     * @return array<int, float> por epoch de la hora
     */
    private function latenciaBase(int $servicioId, int $desde, int $hasta): array
    {
        $horas = DB::table('cliente_metricas')->where('servicio_id', $servicioId)
            ->whereBetween('ts', [date('Y-m-d H:i:s', $desde - 86400), date('Y-m-d H:i:s', $hasta)])
            ->whereNotNull('lat_avg_ms')
            ->selectRaw("DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') as hora, SUM(lat_avg_ms) as suma, COUNT(*) as n")
            ->groupBy('hora')->orderBy('hora')
            ->get()
            ->mapWithKeys(fn ($h) => [strtotime($h->hora) => [(float) $h->suma, (int) $h->n]])
            ->all();

        $base = [];
        for ($hora = $desde - $desde % 3600; $hora <= $hasta; $hora += 3600) {
            $suma = 0.0;
            $n = 0;
            for ($h = $hora - 86400; $h < $hora; $h += 3600) {
                if (isset($horas[$h])) {
                    $suma += $horas[$h][0];
                    $n += $horas[$h][1];
                }
            }
            if ($n > 0) {
                $base[$hora] = $suma / $n;
            }
        }

        return $base;
    }

    /**
     * IPs de la leyenda: las configuradas para monitorear (sin la del router), en su orden, y al
     * final las que tienen mediciones en el periodo pero ya no están en la lista.
     *
     * @return array<int, array{ip: string, etiqueta: ?string, configurada: bool}>
     */
    private function ips(?ServicioMonitoreo $monitoreo, array $vistas): array
    {
        $lista = [];
        foreach ($monitoreo?->ips ?? [] as $ip) {
            if ($ip->monitorear && $ip->ip !== $monitoreo->ip_router) {
                $lista[$ip->ip] = ['ip' => $ip->ip, 'etiqueta' => $ip->etiqueta, 'configurada' => true];
            }
        }
        foreach ($vistas as $ip) {
            $lista[$ip] ??= ['ip' => $ip, 'etiqueta' => null, 'configurada' => false];
        }

        return array_values($lista);
    }
}
