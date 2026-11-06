<?php

namespace App\Services\Metricas;

use App\Models\ClienteMetrica;
use App\Models\ClienteMetricaContador;
use App\Models\ClienteMetricaPing;
use App\Models\MetricasColectorLatido;
use App\Models\Router;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Colector por minuto de los servicios dedicados (item #988): tráfico de su VLAN y ping a las
 * IPs de su bloque, todo desde su MikroTik.
 *
 * Por router: se abre UNA conexión, se verifica su identidad (IdentidadRouter: si es igual al título
 * se registra sola; sin confirmar o distinta, no se lee nada de él), se leen de una vez los contadores de
 * todas las interfaces y se hace ping a cada IP con pocos paquetes. Por servicio queda una fila
 * en cliente_metricas (tráfico + resumen del ping) y una en cliente_metricas_ping por IP; por
 * corrida, un latido. Un router o un servicio que falla no detiene a los demás.
 * La configuración la lee solo ConfiguracionMonitoreo. El ping viejo (mikrotik:sync-ping /
 * ping_statistics) no se toca.
 */
final class ColectorMetricas
{
    public function __construct(private readonly ConfiguracionMonitoreo $configuracion)
    {
    }

    /** Sin nombre, el que corresponde a este entorno (EntornoMetricas::driverPorDefecto). */
    public static function driver(?string $nombre = null): MetricasDriver
    {
        $nombre = $nombre ?: EntornoMetricas::driverPorDefecto();

        return match ($nombre) {
            'mikrotik' => new MikrotikMetricasDriver(),
            'simulado' => new SimuladoMetricasDriver(),
            default    => throw new InvalidArgumentException("Driver de métricas desconocido: «{$nombre}» (mikrotik | simulado)."),
        };
    }

    /**
     * @param  int[]  $soloClientes
     * @param  bool  $dryRun  lee todo y no guarda nada; además se detiene en el primer router
     *                        cuya identidad no coincide (red de seguridad del runbook)
     * @param  array{tasas_vlan?: int[], muestra_segundos?: int, paquetes?: int, ips_extra?: string[]}  $opciones
     *         SOLO en dry-run (se ignoran si no): tasas en vivo de esas VLAN para comparar con
     *         Winbox; dos lecturas separadas N segundos para calcular bajada/subida sin guardar; otro
     *         número de paquetes de ping para medir cuánto tarda; ping a IPs fuera de los servicios.
     * @return array{latido: array<string, mixed>, routers: array<int, array<string, mixed>>, filas: array<int, array<string, mixed>>, pings: array<int, array<string, mixed>>, abortado: bool}
     */
    public function correr(MetricasDriver $driver, array $soloClientes = [], bool $dryRun = false, array $opciones = []): array
    {
        $opciones = $dryRun ? $opciones : [];
        $inicio = microtime(true);
        $ts = Carbon::now()->startOfMinute()->format('Y-m-d H:i:s');
        ['porRouter' => $porRouter, 'incompletos' => $incompletos] = $this->configuracion->dedicados($soloClientes);

        $param = [
            'hueco'      => (int) config('metricas.hueco_max_segundos', 300),
            'bps_max'    => (int) config('metricas.bps_maximo', 40_000_000_000),
            'paquetes'   => (int) ($opciones['paquetes'] ?? config('metricas.ping.paquetes', 3)),
            'muestra'    => (int) ($opciones['muestra_segundos'] ?? 0),
            'intervalo'  => (string) config('metricas.ping.intervalo', '00:00:00.200'),
            'origen'     => $driver->nombre() === 'simulado' ? ClienteMetrica::ORIGEN_SIMULADO : ClienteMetrica::ORIGEN_MIKROTIK,
            'ts'         => $ts,
        ];
        $presupuestoRouter = (float) config('metricas.presupuesto_router_segundos', 40);
        $presupuestoCorrida = (float) config('metricas.presupuesto_corrida_segundos', 50);

        $routers = Router::with('mikrotik')->whereIn('id', array_keys($porRouter))->get()->keyBy('id');
        $servicioIds = array_merge([], ...array_map(fn ($ss) => array_column($ss, 'servicio_id'), array_values($porRouter)));
        $previos = ClienteMetricaContador::whereIn('servicio_id', $servicioIds)->get()->keyBy('servicio_id');

        $latido = ['routers_ok' => 0, 'routers_error' => 0, 'clientes_ok' => 0, 'clientes_error' => 0, 'clientes_incompletos' => $incompletos];
        $infoRouters = [];
        $filas = [];
        $pings = [];
        $contadores = [];
        $abortado = false;

        foreach ($porRouter as $routerId => $servicios) {
            $router = $routers->get($routerId);
            $info = ['router_id' => $routerId, 'titulo' => $router?->title, 'host' => null, 'usuario' => null,
                'solo_lectura' => null, 'redirigido' => null, 'identidad' => null,
                'esperada' => $router?->mikrotik?->identidad_esperada, 'estado' => 'ok', 'error' => null, 'duracion_ms' => null];
            $inicioRouter = microtime(true);

            if ($inicioRouter - $inicio > $presupuestoCorrida) {
                $info['estado'] = 'omitido';
                $info['error'] = 'Sin tiempo en el ciclo de 60 s.';
                $this->sinLectura($servicios, 'omitido', $param, $filas, $pings, $latido);
                $infoRouters[] = $info;
                continue;
            }

            $sesion = null;
            try {
                $sesion = $router ? $driver->abrir($router) : throw new RuntimeException("El router #{$routerId} no existe.");
                $info['host'] = $sesion->host();
                $info['usuario'] = $sesion->usuario();
                $info['solo_lectura'] = $sesion->soloLectura();
                $info['redirigido'] = $sesion->redirigido();
                $info['identidad'] = $sesion->identidad();
                $this->seguirDisponibilidad($router, true, $dryRun);

                $evaluacion = IdentidadRouter::evaluar($info['identidad'], $info['esperada'], $router->title);
                if ($evaluacion === IdentidadRouter::POR_TITULO && ! $sesion->redirigido()) {
                    if (! $dryRun) {
                        IdentidadRouter::guardar((int) $routerId, $info['identidad']);
                    }
                    $info['esperada'] = $info['identidad'];
                    $evaluacion = IdentidadRouter::COINCIDE;
                }
                if ($evaluacion !== IdentidadRouter::COINCIDE) {
                    $esperada = trim((string) $info['esperada']);
                    $info['estado'] = $esperada === '' ? 'sin_identidad_esperada' : 'identidad_distinta';
                    $info['error'] = $esperada === ''
                        ? "Contestó «{$info['identidad']}», que no es el título del router: falta confirmarlo en «Probar lectura»."
                        : "Contestó «{$info['identidad']}», se esperaba «{$esperada}»: no se lee.";
                    $latido['routers_error']++;
                    $this->sinLectura($servicios, 'router_no_verificado', $param, $filas, $pings, $latido);
                    $infoRouters[] = $info;
                    if ($dryRun) {
                        $abortado = true;
                        break;
                    }
                    continue;
                }

                if (! empty($opciones['tasas_vlan'])) {
                    try {
                        $info['tasas'] = $sesion->tasasEnVivo($opciones['tasas_vlan']);
                    } catch (Throwable $e) {
                        $info['tasas_error'] = mb_substr($e->getMessage(), 0, 300);
                    }
                }

                $info['ping_ms'] = [];
                $this->leerRouter($sesion, $routerId, $servicios, $previos, $param, $inicioRouter + $presupuestoRouter,
                    $filas, $pings, $contadores, $latido, $info['ping_ms']);

                // Solo dry-run, informativo: IPs que no están en ningún servicio (p. ej. de un
                // dedicado sin «Datos de red» todavía), desde esta misma conexión. No van a $pings.
                foreach ($opciones['ips_extra'] ?? [] as $ipExtra) {
                    $antes = microtime(true);
                    if ($antes > $inicioRouter + $presupuestoRouter) {
                        $info['ping_extra'][] = ['ip' => $ipExtra, 'ms' => null, 'lectura' => null];
                        continue;
                    }
                    $lp = $sesion->ping($ipExtra, $param['paquetes'], $param['intervalo']);
                    $info['ping_extra'][] = ['ip' => $ipExtra, 'ms' => (int) round((microtime(true) - $antes) * 1000), 'lectura' => $lp];
                }
                $latido['routers_ok']++;
            } catch (Throwable $e) {
                $info['host'] ??= $e instanceof SinConexionRouter ? $e->host : null;
                $info['estado'] = 'sin_conexion';
                if ($e instanceof SinConexionRouter && $router) {
                    $this->seguirDisponibilidad($router, false, $dryRun);
                }
                $info['error'] = mb_substr($e->getMessage(), 0, 300);
                $latido['routers_error']++;
                Log::warning("metricas:colectar: sin lectura del router {$routerId}: " . $e->getMessage());
                $this->sinLectura($servicios, 'router_sin_conexion', $param, $filas, $pings, $latido);
            } finally {
                try {
                    $sesion?->cerrar();
                } catch (Throwable) {
                }
            }
            $info['duracion_ms'] = (int) round((microtime(true) - $inicioRouter) * 1000);
            $infoRouters[] = $info;
        }

        $latido += [
            'ts'          => $ts,
            'driver'      => $driver->nombre(),
            'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'detalle'     => $infoRouters ?: null,
        ];

        if (! $dryRun) {
            DB::transaction(function () use ($filas, $pings, $contadores, $latido) {
                if ($filas !== []) {
                    ClienteMetrica::upsert($filas, ['servicio_id', 'ts'], [
                        'cliente_id', 'bajada_bps', 'subida_bps', 'bajada_pps', 'subida_pps', 'estado',
                        'ping_ips', 'ping_responden', 'lat_avg_ms', 'lat_max_ms', 'loss_pct', 'estado_ping', 'origen',
                    ]);
                }
                if ($pings !== []) {
                    ClienteMetricaPing::upsert($pings, ['servicio_id', 'ip', 'ts'], [
                        'cliente_id', 'monitoreo_ip_id', 'enviados', 'recibidos', 'lat_min_ms', 'lat_avg_ms', 'lat_max_ms', 'estado', 'origen',
                    ]);
                }
                if ($contadores !== []) {
                    ClienteMetricaContador::upsert($contadores, ['servicio_id'], ['cliente_id', 'router_id', 'interfaz', 'rx_byte', 'tx_byte', 'rx_packet', 'tx_packet', 'leido_at', 'origen']);
                }
                MetricasColectorLatido::create($latido);
            });
        }

        return ['latido' => $latido, 'routers' => $infoRouters, 'filas' => $filas, 'pings' => $pings, 'abortado' => $abortado];
    }

    /** #1171: up/down del router (antes en mikrotik:sync-ping); solo con el interruptor y sin dry-run. */
    private function seguirDisponibilidad(Router $router, bool $arriba, bool $dryRun): void
    {
        if ($dryRun || ! SeguimientoDisponibilidadRouter::colectorLoLleva()) {
            return;
        }
        try {
            SeguimientoDisponibilidadRouter::registrar($router, $arriba, 'ColectorMetricas');
        } catch (Throwable $e) {
            Log::warning("metricas:colectar: seguimiento de disponibilidad del router {$router->id}: " . $e->getMessage());
        }
    }

    /** Lectura de un router ya verificado: contadores de todas sus interfaces y ping a cada IP. */
    private function leerRouter(SesionRouter $sesion, int $routerId, array $servicios, $previos, array $param, float $limite,
        array &$filas, array &$pings, array &$contadores, array &$latido, array &$tiemposPing): void
    {
        $pedidas = [];
        foreach ($servicios as $s) {
            if ($s['interfaz'] !== null || $s['vlan_id'] !== null) {
                $pedidas[(string) $s['servicio_id']] = ['interfaz' => $s['interfaz'], 'vlan_id' => $s['vlan_id']];
            }
        }
        // Dry-run con muestra: una primera lectura en memoria como base, en vez de la guardada.
        $muestra = null;
        if ($param['muestra'] > 0 && $pedidas !== []) {
            $muestra = $sesion->interfaces($pedidas);
            usleep($param['muestra'] * 1_000_000);
        }
        $lecturas = $pedidas === [] ? [] : $sesion->interfaces($pedidas);

        foreach ($servicios as $s) {
            try {
                $trafico = ['estado' => 'sin_interfaz', 'bajada_bps' => null, 'subida_bps' => null, 'bajada_pps' => null, 'subida_pps' => null];
                if (isset($pedidas[(string) $s['servicio_id']])) {
                    $lectura = $lecturas[(string) $s['servicio_id']] ?? null;
                    if ($lectura === null) {
                        $nombres = $sesion->resolucion()[(string) $s['servicio_id']]['nombres'] ?? [];
                        $trafico['estado'] = count($nombres) > 1 ? 'vlan_repetida' : 'interfaz_no_encontrada';
                    } else {
                        $previo = $previos->get($s['servicio_id']);
                        $nombre = $s['interfaz'] ?? ('vlan:' . $s['vlan_id']);
                        $primera = $muestra[(string) $s['servicio_id']] ?? null;
                        // La base solo vale si es de la misma interfaz del mismo router.
                        $base = $primera ? [
                            'rx_byte' => $primera->rxByte, 'tx_byte' => $primera->txByte,
                            'rx_packet' => $primera->rxPacket, 'tx_packet' => $primera->txPacket, 'leido_en' => $primera->leidoEn,
                        ] : (($previo && (int) $previo->router_id === $routerId && $previo->interfaz === $nombre)
                            ? [
                                'rx_byte' => (int) $previo->rx_byte, 'tx_byte' => (int) $previo->tx_byte,
                                'rx_packet' => (int) $previo->rx_packet, 'tx_packet' => (int) $previo->tx_packet,
                                'leido_en' => Carbon::parse($previo->leido_at)->getPreciseTimestamp(3) / 1000,
                            ]
                            : null);
                        $trafico = DeltaContadores::calcular($lectura, $base, $param['hueco'], $param['bps_max']);
                        $contadores[] = [
                            'servicio_id' => $s['servicio_id'], 'cliente_id' => $s['cliente_id'], 'router_id' => $routerId, 'interfaz' => $nombre,
                            'rx_byte' => $lectura->rxByte, 'tx_byte' => $lectura->txByte,
                            'rx_packet' => $lectura->rxPacket, 'tx_packet' => $lectura->txPacket,
                            'leido_at' => Carbon::createFromTimestampMs((int) round($lectura->leidoEn * 1000))->format('Y-m-d H:i:s.v'),
                            'origen' => $param['origen'],
                        ];
                    }
                }

                $lecturasPing = [];
                foreach ($s['ips'] as $ip) {
                    $antes = microtime(true);
                    $lp = $antes > $limite ? null : $sesion->ping($ip['ip'], $param['paquetes'], $param['intervalo']);
                    $tiemposPing[] = ['ip' => $ip['ip'], 'ms' => (int) round((microtime(true) - $antes) * 1000), 'detalle' => $lp?->detalle ?? []];
                    $lecturasPing[] = $lp;
                    $pings[] = $this->filaPing($s, $ip, $param, $lp);
                }

                $filas[] = $this->base($s, $param) + $trafico + ResumenPing::de($lecturasPing);
                $latido['clientes_ok']++;
            } catch (Throwable $e) {
                $filas[] = $this->fila($s, 'error', $param);
                $latido['clientes_error']++;
                Log::warning("metricas:colectar: servicio {$s['servicio_id']}: " . $e->getMessage());
            }
        }
    }

    /** Todo un router sin lectura: cada servicio con el motivo, y sus IPs como no medidas. */
    private function sinLectura(array $servicios, string $estado, array $param, array &$filas, array &$pings, array &$latido): void
    {
        foreach ($servicios as $s) {
            $filas[] = $this->fila($s, $estado, $param);
            foreach ($s['ips'] as $ip) {
                $pings[] = $this->filaPing($s, $ip, $param, null);
            }
            $latido['clientes_error']++;
        }
    }

    private function base(array $s, array $param): array
    {
        return ['servicio_id' => $s['servicio_id'], 'cliente_id' => $s['cliente_id'], 'ts' => $param['ts'], 'origen' => $param['origen']];
    }

    /** Fila sin velocidad ni ping: el estado dice por qué no hay dato en ese minuto. */
    private function fila(array $s, string $estado, array $param): array
    {
        return $this->base($s, $param)
            + ['estado' => $estado, 'bajada_bps' => null, 'subida_bps' => null, 'bajada_pps' => null, 'subida_pps' => null]
            + ResumenPing::de([]);
    }

    /** @param  array{id:int, ip:string}  $ip */
    private function filaPing(array $s, array $ip, array $param, ?LecturaPing $l): array
    {
        return $this->base($s, $param) + [
            'monitoreo_ip_id' => $ip['id'], 'ip' => $ip['ip'],
            'enviados' => $l?->enviados ?? $param['paquetes'], 'recibidos' => $l?->recibidos() ?? 0,
            'lat_min_ms' => $l?->minMs(), 'lat_avg_ms' => $l?->avgMs(), 'lat_max_ms' => $l?->maxMs(),
            'estado' => match (true) {
                $l === null            => ClienteMetricaPing::OMITIDO,
                $l->error !== null     => ClienteMetricaPing::ERROR,
                $l->responde()         => ClienteMetricaPing::RESPONDE,
                default                => ClienteMetricaPing::SIN_RESPUESTA,
            },
        ];
    }
}
