<?php

namespace App\Services\Metricas;

use App\Models\ClienteMetrica;
use App\Models\ServicioMonitoreo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que muestra Estadísticas → Internet de un cliente dedicado (item #988, Fase 4).
 * Lee solo las tablas nuevas (cliente_metricas, cliente_metricas_ping, servicio_monitoreo): el
 * ping viejo (ping_statistics) no se usa, porque mide la IP del servicio, que puede ser la del
 * router (#999).
 */
class EstadoServicioDedicado
{
    /** Estado general que ve el usuario (siempre con texto, nunca solo color). */
    public const EN_LINEA = 'en_linea';
    public const DEGRADADO = 'degradado';
    public const SIN_RESPUESTA = 'sin_respuesta';
    public const SIN_DATOS = 'sin_datos';

    /**
     * Servicios de internet del cliente y si tienen «Datos de red». Sirve para decidir qué se pinta:
     * selector (2 o más servicios), aviso de configurar, y si el bloque PPPoE se reemplaza.
     */
    public function resumen(int $clienteId): array
    {
        $dedicado = (bool) DB::table('client_additional_information')->where('client_id', $clienteId)
            ->whereNull('deleted_at')->value('is_dedicated');

        $servicios = $dedicado
            ? DB::table('client_internet_services as s')
                ->leftJoin('internets as i', 'i.id', '=', 's.internet_id')
                ->leftJoin('servicio_monitoreo as m', 'm.servicio_id', '=', 's.id')
                ->where('s.client_id', $clienteId)
                ->orderBy('s.id')
                ->get(['s.id', 's.description', 'i.title as plan', 'i.download_speed', 'i.upload_speed', 'm.id as monitoreo_id', 'm.vlan_id', 'm.activo'])
            : collect();

        $lista = $servicios->map(fn ($s) => [
            'id'            => (int) $s->id,
            'etiqueta'      => trim(($s->vlan_id ? "VLAN {$s->vlan_id} · " : '') . ($s->plan ?: $s->description ?: "Servicio #{$s->id}")),
            'configurado'   => $s->monitoreo_id !== null,
            'activo'        => $s->monitoreo_id !== null && (bool) $s->activo,
            'plan_bajada_bps' => $s->download_speed ? (int) $s->download_speed * 1000 : null,
            'plan_subida_bps' => $s->upload_speed ? (int) $s->upload_speed * 1000 : null,
        ])->values()->all();

        return [
            'is_dedicated'    => $dedicado,
            'servicios'       => $lista,
            'reemplaza_pppoe' => $dedicado && collect($lista)->contains('configurado', true),
        ];
    }

    /** Tarjeta «Estado del servicio en tiempo real» de un servicio. */
    public function estado(int $servicioId): array
    {
        $config = ServicioMonitoreo::with('ips')->where('servicio_id', $servicioId)->first();
        $ultima = DB::table('cliente_metricas')->where('servicio_id', $servicioId)->orderByDesc('ts')->first();
        $umbral = (int) config('metricas.sin_datos_segundos', 180);

        $base = [
            'servicio_id'    => $servicioId,
            'configurado'    => $config !== null,
            'intervalo_seg'  => 60,
            'ips_configuradas' => $config ? $config->ips->where('monitorear', true)->where('ip', '!=', $config->ip_router)->pluck('ip')->values()->all() : [],
        ];

        if ($ultima === null) {
            return $base + ['estado' => self::SIN_DATOS, 'motivo' => 'Todavía no hay lecturas de este servicio.',
                'ultima_lectura' => null, 'segundos' => null, 'simulado' => false, 'ips' => [], 'latencia_ms' => null,
                'perdida_pct' => null, 'bajada_bps' => null, 'subida_bps' => null, 'trafico_motivo' => null];
        }

        $ts = Carbon::parse($ultima->ts);
        $segundos = max(0, now()->diffInSeconds($ts));
        $reciente = $segundos <= $umbral;

        $pings = DB::table('cliente_metricas_ping as p')
            ->leftJoin('servicio_monitoreo_ips as i', 'i.id', '=', 'p.monitoreo_ip_id')
            ->where('p.servicio_id', $servicioId)->where('p.ts', $ultima->ts)
            ->orderBy('i.orden')->orderBy('p.id')
            ->get(['p.ip', 'i.etiqueta', 'p.enviados', 'p.recibidos', 'p.lat_avg_ms', 'p.estado']);

        [$estado, $motivo] = match (true) {
            ! $reciente => [self::SIN_DATOS, 'El colector no tiene una lectura reciente (no es una caída).'],
            $ultima->estado_ping === ClienteMetrica::PING_EN_LINEA => [self::EN_LINEA, null],
            $ultima->estado_ping === ClienteMetrica::PING_DEGRADADO => [self::DEGRADADO, 'Responde al menos un equipo, pero no todos.'],
            $ultima->estado_ping === ClienteMetrica::PING_SIN_RESPUESTA => [self::SIN_RESPUESTA, 'Ningún equipo del cliente respondió.'],
            default => [self::SIN_DATOS, $this->motivoSinPing($ultima->estado)],
        };

        return $base + [
            'estado'         => $estado,
            'motivo'         => $motivo,
            'ultima_lectura' => $ts->toIso8601String(),
            'segundos'       => $segundos,
            'simulado'       => $ultima->origen === ClienteMetrica::ORIGEN_SIMULADO,
            'latencia_ms'    => $reciente && $ultima->lat_avg_ms !== null ? (float) $ultima->lat_avg_ms : null,
            'perdida_pct'    => $reciente && $ultima->loss_pct !== null ? (float) $ultima->loss_pct : null,
            'bajada_bps'     => $reciente ? $this->entero($ultima->bajada_bps) : null,
            'subida_bps'     => $reciente ? $this->entero($ultima->subida_bps) : null,
            'trafico_motivo' => $reciente && $ultima->bajada_bps === null ? $this->motivoTrafico($ultima->estado) : null,
            'ips'            => $reciente ? $pings->map(fn ($p) => [
                'ip'          => $p->ip,
                'etiqueta'    => $p->etiqueta,
                'estado'      => match ($p->estado) {
                    'responde'      => self::EN_LINEA,
                    'sin_respuesta' => self::SIN_RESPUESTA,
                    default         => self::SIN_DATOS,
                },
                'latencia_ms' => $p->lat_avg_ms !== null ? (float) $p->lat_avg_ms : null,
                'perdida_pct' => $p->enviados > 0 ? round((1 - $p->recibidos / $p->enviados) * 100, 1) : null,
            ])->values()->all() : [],
        ];
    }

    private function motivoSinPing(string $estadoTrafico): string
    {
        return match ($estadoTrafico) {
            'router_sin_conexion'  => 'El router no contestó en la última lectura.',
            'router_no_verificado' => 'El router no contestó la identidad esperada: no se leyó.',
            'omitido'              => 'No alcanzó el tiempo del ciclo para medir este servicio.',
            default                => 'No hay IPs del equipo del cliente marcadas para monitorear.',
        };
    }

    private function motivoTrafico(string $estado): string
    {
        return match ($estado) {
            'primera_lectura', 'hueco_largo' => 'Se necesita una lectura más para calcular el tráfico.',
            'contador_reiniciado'            => 'El contador de la interfaz se reinició.',
            'interfaz_no_encontrada'         => 'No se encontró la interfaz en el router.',
            'sin_interfaz'                   => 'Falta la interfaz o el VLAN ID en los datos de red.',
            'router_sin_conexion', 'router_no_verificado' => 'El router no contestó.',
            default                          => 'Sin lectura de tráfico.',
        };
    }

    private function entero($v): ?int
    {
        return $v === null ? null : (int) $v;
    }
}
