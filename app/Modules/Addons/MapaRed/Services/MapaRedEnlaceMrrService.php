<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use App\Modules\Core\Clientes\Models\ClientBundleService;
use App\Modules\Core\Clientes\Models\ClientCustomService;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use Illuminate\Support\Collection;

/**
 * MR-17 Fase 2 (item roadmap #9990553) — dada una lista de mapared_enlaces_servicio (típicamente
 * la salida del fan-out de Fase 1/#9990552), resuelve el cliente REAL de facturación y calcula el
 * MRR agregado afectado.
 *
 * Por D19 (ver migración 2026_09_06_233000_create_mapared_enlaces_servicio_table) el enlace NUNCA
 * guarda client_id — solo cliente_nombre y, opcionalmente, cliente_numero_contrato — porque dev y
 * prod tienen ids distintos. Hay que resolver el cliente por nombre.
 *
 * Decisiones tomadas en esta fase (documentadas también vía circuito:reportar --tipo=decision):
 *
 *  - Resolución de cliente: EXACTA por nombre normalizado (mayúsculas, sin acentos, espacios
 *    colapsados) contra client_main_information.name+father_last_name+mother_last_name de
 *    clientes no borrados / no de prueba. NO se reusa SubscriberSearchService (Payments): ese
 *    servicio es fuzzy/best-effort con umbral de score bajo, diseñado para identificar pagos por
 *    WhatsApp — aceptable arriesgar un falso positivo ahí, NO aquí, donde el resultado es una
 *    cifra de MRR de un troncal completo. `cliente_numero_contrato` NO se usa para resolver (no
 *    existe en este sistema un campo de "número de contrato" cruzable de forma confiable contra
 *    `clients`/`client_main_information`); se expone en el detalle solo como referencia
 *    informativa cuando el enlace lo trae poblado. 0 o 2+ matches exactos de nombre => "cliente
 *    sin vincular", nunca se adivina por score bajo (tal como pide el spec del item).
 *
 *  - MRR = suma de `client_bundle_services.price` + `client_custom_services.price` (tarifa BASE,
 *    columna `price` directa — se ignoran `discount`/`discount_percent` a propósito) de los
 *    servicios del cliente resuelto en estado literal 'Activo'. Se incluyen los custom services
 *    porque en este sistema "Custom" es un tipo de plan facturable más (CLAUDE.md: "planes
 *    (Internet, VoIP, Custom, Bundle)"), no un cargo opcional aparte — excluirlo subestimaría el
 *    MRR real de un cliente con un servicio Custom activo. Estados 'Pendiente'/'Activado'
 *    (bundle) y 'Desactivado'/'Activado' (custom) se excluyen: no son la tarifa vigente cobrada.
 *
 *  - Solo cuentan en `total_clientes`/`mrr_total` los clientes cuyo `client_main_information.
 *    estado` esté en ESTADOS_CUENTAN (decisión ya tomada por Irving en el item padre #953, q2):
 *    Activo + Bloqueado (suspendido por cobranza) cuentan; Cancelado e Inactivo se excluyen. Los
 *    clientes excluidos por estado SÍ aparecen en `detalle` (con `cuenta_en_mrr=false`) para que
 *    se pueda auditar a simple vista que no se están sumando.
 */
class MapaRedEnlaceMrrService
{
    /** client_main_information.estado que cuentan como "cliente afectado" (decisión #953 q2). */
    private const ESTADOS_CUENTAN = [
        ClientMainInformation::STATE_ACTIVE,
        ClientMainInformation::STATE_BLOCKED,
    ];

    /** estado de client_bundle_services/client_custom_services que representa la tarifa vigente. */
    private const ESTADO_SERVICIO_ACTIVO = 'Activo';

    /** @var array<string,int[]> nombre normalizado => [client_id,...] */
    private array $indiceNombres = [];

    /** @var array<int,string> client_id => client_main_information.estado */
    private array $estadosClientes = [];

    private bool $indiceCargado = false;

    /**
     * @param iterable<MapaRedEnlaceServicio|int> $enlaces Modelos o ids de mapared_enlaces_servicio.
     * @return array{total_clientes:int,clientes_sin_vincular:int,mrr_total:float,detalle:array}
     */
    public function calcular(iterable $enlaces): array
    {
        $enlaces = $this->normalizarEnlaces($enlaces);

        if ($enlaces->isEmpty()) {
            return $this->resultadoVacio();
        }

        $this->cargarIndiceClientes();

        $resolucion = $this->resolverClientesPorEnlace($enlaces);

        $clientIds = collect($resolucion)->pluck('cliente_id')->filter()->unique()->values();
        $serviciosPorCliente = $this->serviciosActivosPorCliente($clientIds);
        $labelsPuertos = $this->labelsDePuertos($enlaces->pluck('puerto_nap_id')->filter()->unique());

        $detalle = [];
        $clientesContadosIds = [];
        $mrrTotal = 0.0;
        $sinVincular = 0;

        foreach ($enlaces as $enlace) {
            $res = $resolucion[$enlace->id];
            $napPuerto = $labelsPuertos[$enlace->puerto_nap_id] ?? null;

            if ($res['cliente_id'] === null) {
                $sinVincular++;
                $detalle[] = [
                    'enlace_id' => $enlace->id,
                    'cliente_nombre' => $enlace->cliente_nombre,
                    'cliente_numero_contrato' => $enlace->cliente_numero_contrato,
                    'cliente_id' => null,
                    'vinculado' => false,
                    'motivo_sin_vincular' => $res['motivo'],
                    'plan' => null,
                    'monto' => null,
                    'estado_cliente' => null,
                    'cuenta_en_mrr' => false,
                    'nap_puerto' => $napPuerto,
                ];
                continue;
            }

            $clientId = $res['cliente_id'];
            $estadoCliente = $this->estadosClientes[$clientId] ?? null;
            $cuenta = in_array($estadoCliente, self::ESTADOS_CUENTAN, true);
            $servicios = $serviciosPorCliente[$clientId] ?? [];
            $montoCliente = array_sum(array_column($servicios, 'monto'));

            if ($cuenta) {
                $clientesContadosIds[] = $clientId;
                $mrrTotal += $montoCliente;
            }

            if (empty($servicios)) {
                $detalle[] = [
                    'enlace_id' => $enlace->id,
                    'cliente_nombre' => $enlace->cliente_nombre,
                    'cliente_numero_contrato' => $enlace->cliente_numero_contrato,
                    'cliente_id' => $clientId,
                    'vinculado' => true,
                    'motivo_sin_vincular' => null,
                    'plan' => null,
                    'monto' => 0.0,
                    'estado_cliente' => $estadoCliente,
                    'cuenta_en_mrr' => $cuenta,
                    'nap_puerto' => $napPuerto,
                ];
                continue;
            }

            foreach ($servicios as $servicio) {
                $detalle[] = [
                    'enlace_id' => $enlace->id,
                    'cliente_nombre' => $enlace->cliente_nombre,
                    'cliente_numero_contrato' => $enlace->cliente_numero_contrato,
                    'cliente_id' => $clientId,
                    'vinculado' => true,
                    'motivo_sin_vincular' => null,
                    'plan' => $servicio['plan'],
                    'monto' => $servicio['monto'],
                    'estado_cliente' => $estadoCliente,
                    'cuenta_en_mrr' => $cuenta,
                    'nap_puerto' => $napPuerto,
                ];
            }
        }

        return [
            'total_clientes' => count(array_unique($clientesContadosIds)),
            'clientes_sin_vincular' => $sinVincular,
            'mrr_total' => round($mrrTotal, 2),
            'detalle' => $detalle,
        ];
    }

    private function resultadoVacio(): array
    {
        return ['total_clientes' => 0, 'clientes_sin_vincular' => 0, 'mrr_total' => 0.0, 'detalle' => []];
    }

    /** @return Collection<int,MapaRedEnlaceServicio> */
    private function normalizarEnlaces(iterable $enlaces): Collection
    {
        $enlaces = collect($enlaces);
        if ($enlaces->isEmpty()) {
            return $enlaces;
        }

        if ($enlaces->first() instanceof MapaRedEnlaceServicio) {
            return $enlaces->values();
        }

        return MapaRedEnlaceServicio::query()->whereIn('id', $enlaces->all())->get();
    }

    /**
     * @return array<int,array{cliente_id:?int,motivo:?string}> keyed por enlace->id
     */
    private function resolverClientesPorEnlace(Collection $enlaces): array
    {
        $resultado = [];

        foreach ($enlaces as $enlace) {
            $normalizado = $this->normalizarNombre($enlace->cliente_nombre);
            $candidatos = $this->indiceNombres[$normalizado] ?? [];

            if (count($candidatos) === 1) {
                $resultado[$enlace->id] = ['cliente_id' => $candidatos[0], 'motivo' => null];
            } elseif (count($candidatos) === 0) {
                $resultado[$enlace->id] = ['cliente_id' => null, 'motivo' => 'sin_match'];
            } else {
                $resultado[$enlace->id] = ['cliente_id' => null, 'motivo' => 'nombre_ambiguo'];
            }
        }

        return $resultado;
    }

    private function cargarIndiceClientes(): void
    {
        if ($this->indiceCargado) {
            return;
        }

        $rows = ClientMainInformation::query()
            ->join('clients', 'clients.id', '=', 'client_main_information.client_id')
            ->whereNull('clients.deleted_at')
            ->whereNull('client_main_information.deleted_at')
            ->where(function ($q) {
                $q->whereNull('clients.is_test_data')->orWhere('clients.is_test_data', 0);
            })
            ->get([
                'client_main_information.client_id',
                'client_main_information.name',
                'client_main_information.father_last_name',
                'client_main_information.mother_last_name',
                'client_main_information.estado',
            ]);

        foreach ($rows as $r) {
            $nombreCompleto = trim(preg_replace('/\s+/', ' ', "{$r->name} {$r->father_last_name} {$r->mother_last_name}"));
            $normalizado = $this->normalizarNombre($nombreCompleto);
            $clientId = (int) $r->client_id;

            $this->indiceNombres[$normalizado][] = $clientId;
            $this->estadosClientes[$clientId] = $r->estado;
        }

        $this->indiceCargado = true;
    }

    /**
     * @param Collection<int,int> $clientIds
     * @return array<int,array<int,array{plan:?string,monto:float}>> keyed por client_id
     */
    private function serviciosActivosPorCliente(Collection $clientIds): array
    {
        if ($clientIds->isEmpty()) {
            return [];
        }

        $porCliente = [];

        $bundles = ClientBundleService::query()
            ->with('bundle:id,title')
            ->whereIn('client_id', $clientIds)
            ->where('estado', self::ESTADO_SERVICIO_ACTIVO)
            ->get(['id', 'client_id', 'bundle_id', 'price']);

        foreach ($bundles as $b) {
            $porCliente[$b->client_id][] = [
                'plan' => $b->bundle->title ?? null,
                'monto' => (float) $b->price,
            ];
        }

        $customs = ClientCustomService::query()
            ->with('custom:id,title')
            ->whereIn('client_id', $clientIds)
            ->where('estado', self::ESTADO_SERVICIO_ACTIVO)
            ->get(['id', 'client_id', 'custom_id', 'price']);

        foreach ($customs as $c) {
            $porCliente[$c->client_id][] = [
                'plan' => $c->custom->title ?? null,
                'monto' => (float) $c->price,
            ];
        }

        return $porCliente;
    }

    /**
     * @param Collection<int,int> $puertoIds
     * @return array<int,string> keyed por puerto_nap_id
     */
    private function labelsDePuertos(Collection $puertoIds): array
    {
        if ($puertoIds->isEmpty()) {
            return [];
        }

        $puertos = MapaRedPuerto::query()->with('puertable')->whereIn('id', $puertoIds)->get();

        $labels = [];
        foreach ($puertos as $puerto) {
            $dueno = $puerto->puertable;
            $nombreDueno = $dueno?->text_node ?? $dueno?->label ?? null;
            if (!$nombreDueno && $dueno) {
                $nombreDueno = sprintf('%s #%d', class_basename($dueno), $dueno->getKey());
            }

            $labels[$puerto->id] = trim(sprintf(
                'Puerto %s%s',
                $puerto->numero,
                $nombreDueno ? " — {$nombreDueno}" : ''
            ));
        }

        return $labels;
    }

    private function normalizarNombre(?string $s): string
    {
        if (!$s) {
            return '';
        }

        $s = mb_strtoupper($s, 'UTF-8');
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U', 'Ñ' => 'N',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
