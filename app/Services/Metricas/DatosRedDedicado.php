<?php

namespace App\Services\Metricas;

use App\Models\ServicioMonitoreo;
use App\Services\Clientes\TipoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * «Datos de red del dedicado» de un servicio de internet (item #988): lo que muestra y guarda la
 * ventana del botón «Datos de red».
 *
 * Solo lee el servicio para proponer (router, bloque del pool) y guarda en servicio_monitoreo /
 * servicio_monitoreo_ips. Nunca guarda client_internet_services: eso dispara trabajos al router.
 * Reglas de Irving: las IPs a monitorear son SOLO del bloque, el bloque se puede capturar o
 * corregir a mano en CIDR (el inventario puede no coincidir con el real) y la IP del router no
 * se monitorea (#999). Si se mide o no lo decide PoliticaMonitoreo, no esta ventana; aquí se muestra.
 */
class DatosRedDedicado
{
    public function para(int $servicioId): array
    {
        $servicio = $this->servicio($servicioId);
        $config = ServicioMonitoreo::with('ips')->where('servicio_id', $servicioId)->first();

        $ipRegistrada = $servicio->ipv4 ? DB::table('network_ips')->where('id', $servicio->ipv4)->first(['ip', 'network_id']) : null;
        $redDeLaIp = $ipRegistrada ? DB::table('networks')->where('id', $ipRegistrada->network_id)->first(['network', 'bm', 'title']) : null;
        $redPool = ($servicio->ipv4_assignment === 'Pool IP' && $servicio->ipv4_pool)
            ? DB::table('networks')->where('id', $servicio->ipv4_pool)->first(['network', 'bm', 'title'])
            : null;

        $avisos = [];
        if ($config === null) {
            $avisos[] = 'Todavía no se ha guardado: lo que ves está propuesto desde el servicio. Revísalo y guarda.';
        } elseif ($config->automatica) {
            $avisos[] = 'Configuración automática, sin revisar: se tomó del servicio. Si la IP registrada es la del router, el ping mide al router y no al cliente; marca los equipos correctos y guarda.';
        }
        if ($ipRegistrada) {
            $avisos[] = sprintf('El servicio tiene registrada la IP %s%s. Si esa es la del router o el bloque real es otro, captura el bloque a mano: no se corrige el servicio desde aquí.',
                $ipRegistrada->ip, $redDeLaIp ? " (red {$redDeLaIp->network}/{$redDeLaIp->bm} «{$redDeLaIp->title}» del inventario)" : '');
        }

        $datos = $config
            ? [
                'router_id'     => $config->router_id,
                'interfaz_vlan' => $config->interfaz_vlan,
                'vlan_id'       => $config->vlan_id,
                'bloque_cidr'   => $config->bloque_cidr,
                'ip_router'     => $config->ip_router,
                'ips'           => $config->ips->map(fn ($i) => ['ip' => $i->ip, 'etiqueta' => $i->etiqueta, 'monitorear' => $i->monitorear])->values()->all(),
            ]
            : [
                'router_id'     => $servicio->router_id ? (int) $servicio->router_id : null,
                'interfaz_vlan' => null,
                'vlan_id'       => null,
                'bloque_cidr'   => $redPool ? "{$redPool->network}/{$redPool->bm}" : null,
                'ip_router'     => null,
                'ips'           => [],
            ];

        return [
            'servicio' => [
                'id'           => (int) $servicio->id,
                'cliente_id'   => (int) $servicio->client_id,
                'plan'         => $servicio->plan,
                'descripcion'  => $servicio->description,
                'dedicado'     => $this->esDedicado((int) $servicio->client_id),
                'ip_registrada' => $ipRegistrada?->ip,
                'bloque_pool'  => $redPool ? "{$redPool->network}/{$redPool->bm}" : null,
            ],
            'datos'          => $datos,
            'monitoreo'      => [
                'activo'     => $config ? (bool) $config->activo : null,
                'motivo'     => $config?->motivo_pausa ? PoliticaMonitoreo::texto($config->motivo_pausa) : null,
                'automatica' => (bool) $config?->automatica,
            ],
            'propuesto'      => $config === null,
            'avisos'         => $avisos,
            'routers'        => DB::table('routers')->orderBy('title')->get(['id', 'title'])->map(fn ($r) => ['id' => (int) $r->id, 'title' => $r->title])->all(),
            'prefijo_minimo' => (int) config('metricas.bloque_prefijo_minimo', 24),
        ];
    }

    /** @throws ValidationException */
    public function guardar(int $servicioId, array $entrada, ?int $usuarioId = null): array
    {
        $servicio = $this->servicio($servicioId);
        if (! $this->esDedicado((int) $servicio->client_id)) {
            throw ValidationException::withMessages(['servicio' => 'El cliente no es de tipo Dedicado.']);
        }

        $minimo = (int) config('metricas.bloque_prefijo_minimo', 24);
        $validador = Validator::make($entrada, [
            'router_id'        => ['required', 'integer', 'exists:routers,id'],
            'interfaz_vlan'    => ['nullable', 'string', 'max:191'],
            'vlan_id'          => ['nullable', 'integer', 'between:1,4094'],
            'bloque_cidr'      => ['nullable', 'string', 'max:43'],
            'ip_router'        => ['nullable', 'ipv4'],
            'ips'              => ['array', 'max:254'],
            'ips.*.ip'         => ['required', 'ipv4'],
            'ips.*.etiqueta'   => ['nullable', 'string', 'max:100'],
            'ips.*.monitorear' => ['boolean'],
        ], [], [
            'router_id' => 'router', 'interfaz_vlan' => 'interfaz VLAN', 'vlan_id' => 'VLAN ID',
            'bloque_cidr' => 'bloque', 'ip_router' => 'IP del router', 'ips.*.ip' => 'IP', 'ips.*.etiqueta' => 'etiqueta',
        ]);

        $validador->after(function ($v) use ($entrada, $servicioId, $minimo) {
            $cidr = trim((string) ($entrada['bloque_cidr'] ?? ''));
            $bloque = $cidr === '' ? null : BloqueIpv4::desde($cidr);
            $ips = (array) ($entrada['ips'] ?? []);

            if ($cidr !== '' && $bloque === null) {
                $v->errors()->add('bloque_cidr', 'El bloque debe ir en CIDR, por ejemplo 203.0.113.120/29.');
            } elseif ($bloque && $bloque->prefijo < $minimo) {
                $v->errors()->add('bloque_cidr', "El bloque es demasiado grande: como máximo /{$minimo}.");
            }
            if ($bloque === null && ($ips !== [] || ! empty($entrada['ip_router']))) {
                $v->errors()->add('bloque_cidr', 'Captura el bloque: las IPs del router y del cliente tienen que estar dentro de él.');
            }
            if ($bloque && ! empty($entrada['ip_router']) && ! $bloque->esHost($entrada['ip_router'])) {
                $v->errors()->add('ip_router', 'La IP del router no es una IP utilizable del bloque ' . $bloque->cidr() . '.');
            }

            $vistas = [];
            foreach ($ips as $i => $fila) {
                $ip = trim((string) ($fila['ip'] ?? ''));
                if ($bloque && $ip !== '' && ! $bloque->esHost($ip)) {
                    $v->errors()->add("ips.{$i}.ip", "{$ip} no es una IP utilizable del bloque " . $bloque->cidr() . ': solo se monitorean IPs del bloque.');
                }
                if (! empty($fila['monitorear']) && $ip !== '' && $ip === trim((string) ($entrada['ip_router'] ?? ''))) {
                    $v->errors()->add("ips.{$i}.ip", "{$ip} es la IP del router: no se monitorea (mediría al router, no al equipo del cliente).");
                }
                if (isset($vistas[$ip])) {
                    $v->errors()->add("ips.{$i}.ip", "{$ip} está repetida.");
                }
                $vistas[$ip] = true;
            }

            $otro = fn (string $columna, $valor) => DB::table('servicio_monitoreo')
                ->where('router_id', $entrada['router_id'] ?? null)->where($columna, $valor)
                ->where('servicio_id', '!=', $servicioId)->value('servicio_id');
            if (trim((string) ($entrada['interfaz_vlan'] ?? '')) !== '' && ($s = $otro('interfaz_vlan', $entrada['interfaz_vlan']))) {
                $v->errors()->add('interfaz_vlan', "Esa interfaz ya está asignada al servicio #{$s} en el mismo router.");
            }
            if (! empty($entrada['vlan_id']) && ($s = $otro('vlan_id', (int) $entrada['vlan_id']))) {
                $v->errors()->add('vlan_id', "Ese VLAN ID ya está asignado al servicio #{$s} en el mismo router.");
            }
        });

        $datos = $validador->validate();
        $bloque = BloqueIpv4::desde((string) ($datos['bloque_cidr'] ?? ''));

        DB::transaction(function () use ($servicioId, $servicio, $datos, $bloque, $usuarioId) {
            $config = ServicioMonitoreo::updateOrCreate(['servicio_id' => $servicioId], [
                'cliente_id'    => (int) $servicio->client_id,
                'router_id'     => (int) $datos['router_id'],
                'interfaz_vlan' => trim((string) ($datos['interfaz_vlan'] ?? '')) !== '' ? $datos['interfaz_vlan'] : null,
                'vlan_id'       => $datos['vlan_id'] ?? null,
                'bloque_cidr'   => $bloque?->cidr(),
                'ip_router'     => ! empty($datos['ip_router']) ? $datos['ip_router'] : null,
                'automatica'    => false,
                'revisado_por'  => $usuarioId,
                'revisado_at'   => now(),
            ]);

            // Se guardan las IPs marcadas o con etiqueta; las demás del bloque no necesitan fila.
            $guardar = array_values(array_filter((array) ($datos['ips'] ?? []),
                fn ($f) => ! empty($f['monitorear']) || trim((string) ($f['etiqueta'] ?? '')) !== ''));
            $config->ips()->whereNotIn('ip', array_column($guardar, 'ip'))->get()->each->delete();
            foreach ($guardar as $orden => $f) {
                $config->ips()->updateOrCreate(['ip' => $f['ip']], [
                    'etiqueta'   => trim((string) ($f['etiqueta'] ?? '')) ?: null,
                    'monitorear' => ! empty($f['monitorear']),
                    'orden'      => $orden,
                ]);
            }
        });

        app(AsegurarMonitoreoDedicados::class)->cliente((int) $servicio->client_id);

        return $this->para($servicioId);
    }

    private function servicio(int $servicioId): object
    {
        $servicio = DB::table('client_internet_services as s')
            ->leftJoin('internets as i', 'i.id', '=', 's.internet_id')
            ->where('s.id', $servicioId)
            ->first(['s.id', 's.client_id', 's.router_id', 's.ipv4_assignment', 's.ipv4', 's.ipv4_pool', 's.description', 'i.title as plan']);
        abort_if($servicio === null, 404, 'Servicio no encontrado.');

        return $servicio;
    }

    private function esDedicado(int $clienteId): bool
    {
        return DB::table('client_additional_information')->where('client_id', $clienteId)
            ->whereNull('deleted_at')->value('tipo_cliente') === TipoCliente::DEDICADO;
    }
}
