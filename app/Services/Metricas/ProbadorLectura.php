<?php

namespace App\Services\Metricas;

use App\Models\Router;
use App\Models\ServicioMonitoreo;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * «Probar lectura» de la ventana Datos de red (item #988): lo mismo que el --dry-run del colector, pero
 * de un solo servicio y sin guardar lecturas. Solo lectura en el router. Lo único que puede registrar es
 * la identidad del router, porque esa es la regla: si es igual a su título se guarda sola.
 */
final class ProbadorLectura
{
    /** Cuánto se recuerda quién contestó, para que confirmar guarde lo que el router dijo y no lo que llegue del navegador. */
    private const RECUERDA_SEGUNDOS = 600;

    public function probar(int $servicioId, ?int $usuarioId = null): array
    {
        $inicio = microtime(true);
        $limite = $inicio + (float) config('metricas.probar.presupuesto_segundos', 15);

        $config = ServicioMonitoreo::with('ips')->where('servicio_id', $servicioId)->first();
        $router = $config?->router_id ? Router::with('mikrotik')->find($config->router_id) : null;
        $r = [
            'ok'        => false,
            'simulado'  => false,
            'router'    => ['id' => $router?->id, 'titulo' => $router?->title, 'host_registrado' => $router?->ip_host, 'host_real' => null, 'redirigido' => false],
            'usuario'   => null,
            'identidad' => null,
            'interfaz'  => null,
            'pings'     => [],
            'trafico'   => null,
            'fallas'    => [],
        ];
        if ($router === null) {
            $r['fallas'][] = ['codigo' => 'sin_router', 'mensaje' => 'Este servicio no tiene router.', 'causa' => 'Captúralo y guarda antes de probar.'];

            return $this->fin($r, $inicio);
        }

        $sesion = null;
        try {
            $driver = ColectorMetricas::driver();
            $sesion = $driver->abrir($router);
            $r['simulado'] = $driver->nombre() === 'simulado';
            $r['router']['host_real'] = $sesion->host();
            $r['router']['redirigido'] = $sesion->redirigido();
            $r['usuario'] = ['nombre' => $sesion->usuario(), 'tipo' => match (true) {
                $r['simulado']         => 'simulado',
                $sesion->redirigido()  => 'laboratorio',
                $sesion->soloLectura() => 'solo_lectura',
                default                => 'principal',
            }];

            $r['identidad'] = $this->identidad($sesion, $router, $usuarioId, $r['fallas']);
            [$r['interfaz'], $primera] = $this->interfaz($sesion, $config, $servicioId, $r['fallas']);
            $r['pings'] = $this->pings($sesion, $config, $limite, $r['fallas']);
            $r['trafico'] = $this->trafico($sesion, $config, $servicioId, $primera, $limite);
            $r['ok'] = $r['fallas'] === [];
        } catch (Throwable $e) {
            $r['router']['host_real'] ??= $e instanceof SinConexionRouter ? $e->host : null;
            $r['fallas'][] = FallaLectura::deConexion($e);
        } finally {
            try {
                $sesion?->cerrar();
            } catch (Throwable) {
            }
        }

        return $this->fin($r, $inicio);
    }

    /** «Sí, es este router»: guarda la identidad que contestó en la última prueba de esta persona. */
    public function confirmarIdentidad(int $servicioId, User $usuario): ?string
    {
        $routerId = ServicioMonitoreo::where('servicio_id', $servicioId)->value('router_id');
        $identidad = $routerId ? Cache::pull($this->claveIdentidad((int) $routerId, $usuario->id)) : null;
        if ($identidad === null) {
            return null;
        }

        IdentidadRouter::guardar((int) $routerId, $identidad);
        activity('metricas')->causedBy($usuario)->withProperties(['router_id' => (int) $routerId, 'identidad' => $identidad])
            ->log('Identidad del router confirmada en «Probar lectura»');

        return $identidad;
    }

    private function identidad(SesionRouter $sesion, Router $router, ?int $usuarioId, array &$fallas): array
    {
        $contesto = $sesion->identidad();
        $esperada = trim((string) $router->mikrotik?->identidad_esperada);
        $evaluacion = IdentidadRouter::evaluar($contesto, $esperada, $router->title);
        $guardada = false;

        if ($evaluacion === IdentidadRouter::POR_TITULO && ! $sesion->redirigido()) {
            IdentidadRouter::guardar((int) $router->id, $contesto);
            $guardada = true;
        } elseif ($evaluacion === IdentidadRouter::SIN_REGISTRAR) {
            $fallas[] = FallaLectura::identidadSinConfirmar($contesto, $router->title);
        } elseif ($evaluacion === IdentidadRouter::DISTINTA) {
            $fallas[] = FallaLectura::identidadDistinta($contesto, $esperada);
        }

        // En el laboratorio contesta otro router: esa identidad no se le puede registrar al de verdad.
        $puedeConfirmar = in_array($evaluacion, [IdentidadRouter::SIN_REGISTRAR, IdentidadRouter::DISTINTA], true) && ! $sesion->redirigido();
        if ($puedeConfirmar && $usuarioId !== null) {
            Cache::put($this->claveIdentidad((int) $router->id, $usuarioId), $contesto, self::RECUERDA_SEGUNDOS);
        }

        return ['contesto' => $contesto, 'esperada' => $esperada ?: null, 'estado' => $evaluacion, 'guardada_sola' => $guardada, 'puede_confirmar' => $puedeConfirmar];
    }

    /** @return array{0: ?array, 1: ?LecturaInterfaz} lo que se resolvió y la primera lectura de contadores */
    private function interfaz(SesionRouter $sesion, ServicioMonitoreo $config, int $servicioId, array &$fallas): array
    {
        $nombre = trim((string) $config->interfaz_vlan) !== '' ? (string) $config->interfaz_vlan : null;
        if ($nombre === null && ! $config->vlan_id) {
            $fallas[] = FallaLectura::sinInterfaz();

            return [null, null];
        }

        $clave = (string) $servicioId;
        $lectura = $sesion->interfaces([$clave => ['interfaz' => $nombre, 'vlan_id' => $config->vlan_id]])[$clave] ?? null;
        $resolucion = $sesion->resolucion()[$clave] ?? ['por' => null, 'nombres' => []];
        if (count($resolucion['nombres']) > 1) {
            $fallas[] = FallaLectura::vlanRepetida($config->vlan_id);
        } elseif ($lectura === null) {
            $fallas[] = FallaLectura::vlanNoEncontrada($config->vlan_id, $nombre);
        }

        return [[
            'por'        => $resolucion['por'],
            'nombres'    => $resolucion['nombres'],
            'encontrada' => $lectura !== null,
            'varias'     => count($resolucion['nombres']) > 1,
            'activa'     => $lectura?->running,
        ], $lectura];
    }

    private function pings(SesionRouter $sesion, ServicioMonitoreo $config, float $limite, array &$fallas): array
    {
        $ips = $config->ips->where('monitorear', true)->where('ip', '!=', $config->ip_router)->values();
        if ($ips->isEmpty()) {
            $fallas[] = FallaLectura::sinIps();

            return [];
        }

        $paquetes = (int) config('metricas.ping.paquetes', 3);

        return $ips->map(function ($ip) use ($sesion, $paquetes, $limite, &$fallas) {
            $base = ['ip' => $ip->ip, 'etiqueta' => $ip->etiqueta];
            if (microtime(true) > $limite - 1) {
                return $base + ['estado' => 'omitido', 'enviados' => 0, 'recibidos' => 0, 'latencia_ms' => null, 'latencia_max_ms' => null, 'perdida_pct' => null];
            }
            $l = $sesion->ping($ip->ip, $paquetes, (string) config('metricas.ping.intervalo', '00:00:00.200'));
            if (! $l->responde()) {
                $fallas[] = FallaLectura::ipSinRespuesta($ip->ip);
            }

            return $base + [
                'estado'          => $l->error !== null ? 'error' : ($l->responde() ? 'responde' : 'sin_respuesta'),
                'enviados'        => $l->enviados,
                'recibidos'       => $l->recibidos(),
                'latencia_ms'     => $l->avgMs(),
                'latencia_max_ms' => $l->maxMs(),
                'perdida_pct'     => round($l->perdidaPct(), 1),
            ];
        })->all();
    }

    /** Bajada y subida instantáneas: dos lecturas de contadores separadas unos segundos. */
    private function trafico(SesionRouter $sesion, ServicioMonitoreo $config, int $servicioId, ?LecturaInterfaz $primera, float $limite): ?array
    {
        if ($primera === null) {
            return null;
        }

        $falta = $primera->leidoEn + (float) config('metricas.probar.muestra_segundos', 5) - microtime(true);
        if ($falta > 0 && microtime(true) + $falta < $limite) {
            usleep((int) ($falta * 1_000_000));
        }

        $clave = (string) $servicioId;
        $nombre = trim((string) $config->interfaz_vlan) !== '' ? (string) $config->interfaz_vlan : null;
        $segunda = $sesion->interfaces([$clave => ['interfaz' => $nombre, 'vlan_id' => $config->vlan_id]])[$clave] ?? null;
        if ($segunda === null) {
            return null;
        }

        $delta = DeltaContadores::calcular($segunda, [
            'rx_byte' => $primera->rxByte, 'tx_byte' => $primera->txByte,
            'rx_packet' => $primera->rxPacket, 'tx_packet' => $primera->txPacket, 'leido_en' => $primera->leidoEn,
        ], (int) config('metricas.hueco_max_segundos', 300), (int) config('metricas.bps_maximo', 40_000_000_000));

        return ['bajada_bps' => $delta['bajada_bps'], 'subida_bps' => $delta['subida_bps'], 'segundos' => round($segunda->leidoEn - $primera->leidoEn, 1)];
    }

    private function claveIdentidad(int $routerId, int $usuarioId): string
    {
        return "metricas:identidad-vista:{$routerId}:{$usuarioId}";
    }

    private function fin(array $r, float $inicio): array
    {
        return $r + ['duracion_ms' => (int) round((microtime(true) - $inicio) * 1000)];
    }
}
