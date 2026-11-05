<?php

namespace App\Services\Metricas;

use App\Services\Clientes\TipoCliente;
use Illuminate\Support\Facades\DB;

/**
 * Deja al día el monitoreo de los dedicados (item #988), en un solo lugar e idempotente: crea la
 * configuración que falte (automática, sin revisar) y pausa o reanuda según PoliticaMonitoreo, guardando
 * cada periodo de pausa con su motivo.
 *
 * Lo llaman los eventos del cliente y del servicio, la tarea diaria y el colector antes de cada
 * corrida: varios bloqueos y activaciones cambian el estado por query builder, sin eventos.
 * Solo escribe en tablas propias; nunca guarda el servicio ni el router (eso manda trabajos al router).
 */
class AsegurarMonitoreoDedicados
{
    /** @return array{creados: int, pausados: int, reanudados: int} */
    public function todos(): array
    {
        return $this->correr(null);
    }

    /** @return array{creados: int, pausados: int, reanudados: int} */
    public function cliente(int $clienteId): array
    {
        return $this->correr($clienteId);
    }

    private function correr(?int $clienteId): array
    {
        $filas = DB::table('client_internet_services as s')
            ->join('clients as c', 'c.id', '=', 's.client_id')
            ->leftJoin('client_main_information as m', fn ($j) => $j->on('m.client_id', '=', 's.client_id')->whereNull('m.deleted_at'))
            ->leftJoin('client_additional_information as cai', fn ($j) => $j->on('cai.client_id', '=', 's.client_id')->whereNull('cai.deleted_at'))
            ->leftJoin('servicio_monitoreo as sm', 'sm.servicio_id', '=', 's.id')
            ->leftJoin('routers as r', 'r.id', '=', 's.router_id')
            ->where(fn ($q) => $q->where('cai.tipo_cliente', TipoCliente::DEDICADO)->orWhereNotNull('sm.id'))
            ->when($clienteId !== null, fn ($q) => $q->where('s.client_id', $clienteId))
            ->get([
                's.id as servicio_id', 's.client_id', 's.estado as estado_servicio', 's.ipv4', 's.ipv4_pool', 'r.id as router_id',
                'm.estado as estado_cliente', 'cai.tipo_cliente', 'c.deleted_at as cliente_eliminado',
                'sm.id as config_id', 'sm.activo', 'sm.motivo_pausa',
            ]);

        $r = ['creados' => 0, 'pausados' => 0, 'reanudados' => 0];
        foreach ($filas as $f) {
            if ($f->config_id === null) {
                if (! $this->crear($f)) {
                    continue;
                }
                $r['creados']++;
                $f->activo = true;
            }

            $motivo = PoliticaMonitoreo::motivoPausa(TipoCliente::esDedicado($f->tipo_cliente), $f->cliente_eliminado !== null, $f->estado_cliente, $f->estado_servicio);
            if ($motivo === $f->motivo_pausa && (bool) $f->activo === ($motivo === null)) {
                continue;
            }
            $this->aplicar((int) $f->servicio_id, (int) $f->client_id, $motivo);
            $r[$motivo === null ? 'reanudados' : 'pausados']++;
        }

        return $r;
    }

    /** Router del servicio y su IP registrada para el ping. El servicio no guarda VLAN: esa se captura en «Datos de red». */
    private function crear(object $f): bool
    {
        [$ip, $bloque] = $this->ipYBloque($f->ipv4, $f->ipv4_pool);
        $ahora = now();

        return DB::transaction(function () use ($f, $ip, $bloque, $ahora) {
            // Si otro proceso la creó al mismo tiempo, no se duplica: servicio_id es único.
            $creada = DB::table('servicio_monitoreo')->insertOrIgnore([
                'servicio_id' => $f->servicio_id, 'cliente_id' => $f->client_id, 'router_id' => $f->router_id,
                'bloque_cidr' => $bloque, 'activo' => true, 'automatica' => true,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            if ($creada === 0) {
                return false;
            }
            if ($ip !== null) {
                DB::table('servicio_monitoreo_ips')->insert([
                    'servicio_monitoreo_id' => DB::table('servicio_monitoreo')->where('servicio_id', $f->servicio_id)->value('id'),
                    'ip' => $ip, 'etiqueta' => 'IP registrada del servicio', 'monitorear' => true, 'orden' => 0,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }

            return true;
        });
    }

    /**
     * La IP registrada y un bloque que la contenga, para que aparezca en la ventana «Datos de red»: su
     * red si cabe en el máximo permitido; si no, la IP sola (/32). Sin IP, el bloque del pool si cabe.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function ipYBloque(?string $ipv4, ?int $pool): array
    {
        $registrada = $ipv4 ? DB::table('network_ips')->where('id', $ipv4)->first(['ip', 'network_id']) : null;
        $ip = $registrada?->ip;
        $red = $registrada?->network_id ?? $pool;
        $fila = $red ? DB::table('networks')->where('id', $red)->first(['network', 'bm']) : null;
        $bloque = $fila ? BloqueIpv4::desde("{$fila->network}/{$fila->bm}") : null;
        if ($bloque !== null && $bloque->prefijo < (int) config('metricas.bloque_prefijo_minimo', 24)) {
            $bloque = null;
        }
        if ($ip !== null && ($bloque === null || ! $bloque->esHost($ip))) {
            $bloque = BloqueIpv4::desde("{$ip}/32");
        }

        return [$ip, $bloque?->cidr()];
    }

    private function aplicar(int $servicioId, int $clienteId, ?string $motivo): void
    {
        $ahora = now();
        DB::transaction(function () use ($servicioId, $clienteId, $motivo, $ahora) {
            DB::table('servicio_monitoreo_pausas')->where('servicio_id', $servicioId)->whereNull('hasta')->update(['hasta' => $ahora]);
            if ($motivo !== null) {
                DB::table('servicio_monitoreo_pausas')->insert([
                    'servicio_id' => $servicioId, 'cliente_id' => $clienteId, 'motivo' => $motivo, 'desde' => $ahora,
                ]);
            }
            DB::table('servicio_monitoreo')->where('servicio_id', $servicioId)->update(['activo' => $motivo === null, 'motivo_pausa' => $motivo]);
        });
    }
}
