<?php

namespace App\Services\Metricas;

use App\Models\ClienteMetrica;
use App\Models\MetricasColectorLatido;
use App\Models\ServicioMonitoreo;
use App\Services\Clientes\TipoCliente;
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
    public const PAUSADO = 'pausado';
    public const REVISAR = 'revisar';

    /**
     * Servicios de internet del cliente y si tienen «Datos de red». Sirve para decidir qué se pinta:
     * selector (2 o más servicios), aviso de configurar, y si el bloque PPPoE se reemplaza.
     */
    public function resumen(int $clienteId): array
    {
        $dedicado = DB::table('client_additional_information')->where('client_id', $clienteId)
            ->whereNull('deleted_at')->value('tipo_cliente') === TipoCliente::DEDICADO;
        // Si dejó de ser dedicado se mide por PPPoE como cualquiera, pero lo ya medido se sigue consultando.
        $conHistorial = $dedicado ? collect() : DB::table('cliente_metricas')->where('cliente_id', $clienteId)->distinct()->pluck('servicio_id');

        $servicios = $dedicado || $conHistorial->isNotEmpty()
            ? DB::table('client_internet_services as s')
                ->leftJoin('internets as i', 'i.id', '=', 's.internet_id')
                ->leftJoin('servicio_monitoreo as m', 'm.servicio_id', '=', 's.id')
                ->where('s.client_id', $clienteId)
                ->when(! $dedicado, fn ($q) => $q->whereIn('s.id', $conHistorial))
                ->orderBy('s.id')
                ->get(['s.id', 's.description', 'i.title as plan', 'i.download_speed', 'i.upload_speed', 'm.id as monitoreo_id', 'm.vlan_id', 'm.activo'])
            : collect();

        $lista = $servicios->map(fn ($s) => [
            'id'            => (int) $s->id,
            'etiqueta'      => ($s->vlan_id ? "VLAN {$s->vlan_id}" : "#{$s->id}") . ' · ' . ($s->plan ?: $s->description ?: 'Servicio'),
            'configurado'   => $s->monitoreo_id !== null,
            'activo'        => $s->monitoreo_id !== null && (bool) $s->activo,
            'plan_bajada_bps' => $s->download_speed ? (int) $s->download_speed * 1000 : null,
            'plan_subida_bps' => $s->upload_speed ? (int) $s->upload_speed * 1000 : null,
        ])->values()->all();

        return [
            'is_dedicated'    => $dedicado,
            'solo_consulta'   => ! $dedicado && $lista !== [],
            'servicios'       => $lista,
            'reemplaza_pppoe' => $dedicado && collect($lista)->contains('configurado', true),
            'monitoreo_apagado' => $dedicado && ! AjusteMonitoreo::activo(),
        ];
    }

    /** Tarjeta «Estado del servicio en tiempo real» de un servicio. */
    public function estado(int $servicioId): array
    {
        $config = ServicioMonitoreo::with('ips')->where('servicio_id', $servicioId)->first();
        $ultima = DB::table('cliente_metricas')->where('servicio_id', $servicioId)->orderByDesc('ts')->first();
        $umbral = (int) config('metricas.sin_datos_segundos', 180);

        $hechos = fn (?object $fila, array $ips = []) => $this->hechos($config, $fila, $ips, $umbral);
        $pausado = $config !== null && ! $config->activo;

        $base = [
            'servicio_id'    => $servicioId,
            'configurado'    => $config !== null,
            'automatica'     => (bool) $config?->automatica,
            'intervalo_seg'  => 60,
            'ont'            => $this->ont($servicioId),
            'ips_configuradas' => $config ? $config->ips->where('monitorear', true)->where('ip', '!=', $config->ip_router)->pluck('ip')->values()->all() : [],
        ];

        if ($ultima === null) {
            return $base + ['estado' => $pausado ? self::PAUSADO : self::SIN_DATOS,
                'motivo' => $pausado ? $this->textoPausa($config) : 'Todavía no hay lecturas de este servicio.',
                'causa' => $pausado ? null : CausaSinDatos::de($hechos(null)),
                'ultima_lectura' => null, 'segundos' => null, 'simulado' => false, 'ips' => [], 'latencia_ms' => null,
                'perdida_pct' => null, 'bajada_bps' => null, 'subida_bps' => null, 'trafico_motivo' => null];
        }

        $ts = Carbon::parse($ultima->ts);
        $segundos = max(0, now()->diffInSeconds($ts));
        $reciente = $segundos <= $umbral && ! $pausado;

        $pings = DB::table('cliente_metricas_ping as p')
            ->leftJoin('servicio_monitoreo_ips as i', 'i.id', '=', 'p.monitoreo_ip_id')
            ->where('p.servicio_id', $servicioId)->where('p.ts', $ultima->ts)
            ->orderBy('i.orden')->orderBy('p.id')
            ->get(['p.ip', 'i.etiqueta', 'p.enviados', 'p.recibidos', 'p.lat_avg_ms', 'p.estado']);

        [$estado, $motivo] = match (true) {
            $pausado    => [self::PAUSADO, $this->textoPausa($config)],
            ! $reciente => [self::SIN_DATOS, 'El colector no tiene una lectura reciente (no es una caída).'],
            $ultima->estado_ping === ClienteMetrica::PING_EN_LINEA => [self::EN_LINEA, null],
            $ultima->estado_ping === ClienteMetrica::PING_DEGRADADO => [self::DEGRADADO, 'Responde al menos un equipo, pero no todos.'],
            $ultima->estado_ping === ClienteMetrica::PING_SIN_RESPUESTA => [self::SIN_RESPUESTA, 'Ningún equipo del cliente respondió.'],
            default => [self::SIN_DATOS, $this->motivoSinPing($ultima->estado)],
        };

        // #999: una configuración automática que mide la IP del router no puede decir «En línea».
        if (in_array($estado, [self::EN_LINEA, self::DEGRADADO], true) && $this->mideAlRouter($config)) {
            [$estado, $motivo] = [self::REVISAR, 'Revisa los datos de red: la IP medida parece ser del router.'];
        }

        return $base + [
            'estado'         => $estado,
            'motivo'         => $motivo,
            'causa'          => in_array($estado, [self::SIN_DATOS, self::SIN_RESPUESTA], true)
                ? CausaSinDatos::de($hechos($reciente ? $ultima : null, $pings->all()))
                : null,
            'ultima_lectura' => $ts->toIso8601String(),
            // En estos estados el colector lo intentó pero no leyó nada: no es una lectura.
            'ultima_es_intento' => in_array($ultima->estado, ['router_no_verificado', 'router_sin_conexion', 'omitido'], true),
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

    /** Potencia óptica de la ONT del servicio, tal como la dejó el último smartolt:sync (no consulta la OLT). */
    private function ont(int $servicioId): ?array
    {
        $onu = DB::table('olt_onus')->where('service_id', $servicioId)->orderByDesc('last_synced_at')->first(['signal', 'signal_1310', 'signal_1490', 'status', 'last_synced_at']);
        if ($onu === null) {
            return null;
        }
        // signal_1490 = potencia que recibe la ONT (Rx), la que se usa para juzgar el enlace.
        $dbm = $onu->signal_1490 ?? $onu->signal_1310;

        return [
            'dbm'         => is_numeric($dbm) ? round((float) $dbm, 2) : null,
            'calidad'     => $onu->signal && $onu->signal !== '-' ? $onu->signal : null,
            'estado'      => $onu->status,
            'sincronizada' => $onu->last_synced_at ? Carbon::parse($onu->last_synced_at)->toIso8601String() : null,
        ];
    }

    private function mideAlRouter(?ServicioMonitoreo $config): bool
    {
        if ($config === null || ! $config->automatica) {
            return false;
        }
        $router = DB::table('routers')->where('id', $config->router_id)->first();
        $delRouter = [$router->ip_host ?? null, $router->nas_ip ?? null, $config->ip_router];

        return $config->ips->where('monitorear', true)
            ->contains(fn ($ip) => PoliticaMonitoreo::ipPareceDelRouter($ip->ip, $delRouter, $config->bloque_cidr));
    }

    private function textoPausa(ServicioMonitoreo $config): string
    {
        return 'Monitoreo pausado: ' . PoliticaMonitoreo::texto($config->motivo_pausa);
    }

    /** Lo que necesita CausaSinDatos: el freno general, la configuración, la última corrida y la última lectura. */
    private function hechos(?ServicioMonitoreo $config, ?object $fila, array $pings, int $umbral): array
    {
        $latido = MetricasColectorLatido::orderByDesc('ts')->orderByDesc('id')->first();
        $ips = $config ? $config->ips->where('monitorear', true)->where('ip', '!=', $config->ip_router)->count() : 0;
        $router = $config && $latido
            ? collect((array) $latido->detalle)->firstWhere('router_id', $config->router_id)
            : null;

        return [
            'monitoreo'         => AjusteMonitoreo::estado(),
            'configurado'       => $config !== null,
            'con_router'        => (bool) $config?->router_id,
            'con_que_medir'     => $config !== null && ($ips > 0 || $config->vlan_id || trim((string) $config->interfaz_vlan) !== ''),
            'latido_segundos'   => $latido ? max(0, now()->diffInSeconds($latido->ts)) : null,
            'umbral_segundos'   => $umbral,
            'router'            => $router,
            'estado_fila'       => $fila?->estado,
            'sin_ips'           => $fila !== null && $ips === 0,
            'ips_sin_respuesta' => collect($pings)->where('estado', 'sin_respuesta')->pluck('ip')->values()->all(),
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
            'vlan_repetida'                  => 'Ese VLAN ID está en más de una interfaz del router.',
            'sin_interfaz'                   => 'Falta la interfaz o el VLAN ID en los datos de red.',
            'router_sin_conexion'            => 'El router no contestó.',
            'router_no_verificado'           => 'No se leyó: falta verificar el router.',
            default                          => 'Sin lectura de tráfico.',
        };
    }

    private function entero($v): ?int
    {
        return $v === null ? null : (int) $v;
    }
}
