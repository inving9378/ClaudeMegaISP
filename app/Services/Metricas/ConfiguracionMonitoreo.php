<?php

namespace App\Services\Metricas;

use Illuminate\Support\Facades\DB;

/**
 * Qué medir de cada servicio dedicado y desde qué router (item #988). Es la ÚNICA puerta por la
 * que el colector lee esa configuración: sale de los «Datos de red del dedicado» de cada servicio
 * (servicio_monitoreo + servicio_monitoreo_ips), solo de clientes con el interruptor de dedicado.
 */
class ConfiguracionMonitoreo
{
    /**
     * Servicios activos con router y algo que medir (interfaz/VLAN o al menos una IP marcada),
     * agrupados por router. El nombre de la interfaz va tal cual (con espacios). La IP del
     * router nunca entra a la lista de ping, aunque estuviera marcada.
     *
     * `incompletos` = servicios de internet de clientes dedicados que todavía no tienen datos de
     * red guardados, o los tienen sin router ni nada que medir.
     *
     * @param  int[]  $soloClientes
     * @return array{porRouter: array<int, array<int, array{servicio_id:int, cliente_id:int, interfaz:?string, vlan_id:?int, ips:array<int, array{id:int, ip:string}>}>>, incompletos: int}
     */
    public function dedicados(array $soloClientes = []): array
    {
        $dedicados = DB::table('client_additional_information as cai')
            ->join('clients as c', 'c.id', '=', 'cai.client_id')
            ->where('cai.is_dedicated', true)
            ->whereNull('cai.deleted_at')
            ->whereNull('c.deleted_at')
            ->when($soloClientes !== [], fn ($q) => $q->whereIn('cai.client_id', $soloClientes))
            ->pluck('cai.client_id');

        $servicios = DB::table('client_internet_services')->whereIn('client_id', $dedicados)->pluck('id');

        $configs = DB::table('servicio_monitoreo')
            ->whereIn('servicio_id', $servicios)
            ->where('activo', true)
            ->get(['id', 'servicio_id', 'cliente_id', 'router_id', 'interfaz_vlan', 'vlan_id', 'ip_router']);

        $ips = DB::table('servicio_monitoreo_ips')->where('monitorear', true)
            ->whereIn('servicio_monitoreo_id', $configs->pluck('id'))
            ->orderBy('orden')->orderBy('id')
            ->get(['id', 'servicio_monitoreo_id', 'ip'])
            ->groupBy('servicio_monitoreo_id');

        $conDatos = DB::table('servicio_monitoreo')->whereIn('servicio_id', $servicios)->count();
        $porRouter = [];
        $incompletos = $servicios->count() - $conDatos;
        foreach ($configs as $m) {
            $interfaz = trim((string) $m->interfaz_vlan) !== '' ? (string) $m->interfaz_vlan : null;
            $susIps = ($ips[$m->id] ?? collect())
                ->reject(fn ($i) => $i->ip === $m->ip_router)
                ->map(fn ($i) => ['id' => (int) $i->id, 'ip' => (string) $i->ip])->values()->all();
            if (! $m->router_id || ($interfaz === null && ! $m->vlan_id && $susIps === [])) {
                $incompletos++;
                continue;
            }
            $porRouter[(int) $m->router_id][] = [
                'servicio_id' => (int) $m->servicio_id,
                'cliente_id'  => (int) $m->cliente_id,
                'interfaz'    => $interfaz,
                'vlan_id'     => $m->vlan_id ? (int) $m->vlan_id : null,
                'ips'         => $susIps,
            ];
        }

        return ['porRouter' => $porRouter, 'incompletos' => $incompletos];
    }
}
