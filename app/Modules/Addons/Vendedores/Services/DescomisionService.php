<?php

namespace App\Modules\Addons\Vendedores\Services;

use App\Models\ClientMainInformation;
use App\Models\Seller;
use App\Models\SellerStatus;
use App\Models\User;
use App\Modules\Addons\Vendedores\Models\SellerDecommissionEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de descomisión (#1222 / #1216 F1c). Política: docs/unificacion/12-decisiones-economicas.md §6.
 *
 * - Con config('descomision.enabled') = false NO hace nada (ni lee ni escribe).
 * - Solo comisiones PENDIENTES (venta aún no incluida en un pago `additional_sales_commissions`);
 *   jamás toca lo ya pagado (sin clawback). No borra nada: deja un evento auditable (soft).
 * - El vendedor pasa al status «Bloqueado» (por nombre) guardando su status previo en
 *   `sellers.status_id_antes_de_inactivar`.
 * - Idempotente: una venta ya descomisionada no se vuelve a procesar.
 */
class DescomisionService
{
    public const TIPO_COMISION = 'additional_sales_commissions';
    public const ESTADO_DESCOMISIONADA = 'descomisionada';
    public const ESTADO_DESBLOQUEADA = 'desbloqueada';
    public const PERMISO_DESBLOQUEO = 'seller_unlock_descomision';

    public function enabled(): bool
    {
        return (bool) config('descomision.enabled', false);
    }

    /**
     * @return array{enabled:bool,dry_run:bool,descomisionadas:int,omitidas_pagadas:int,vendedores_bloqueados:int,detalle:array}
     */
    public function ejecutar(bool $dryRun = false): array
    {
        $res = ['enabled' => $this->enabled(), 'dry_run' => $dryRun, 'descomisionadas' => 0,
            'omitidas_pagadas' => 0, 'vendedores_bloqueados' => 0, 'detalle' => []];
        if (! $res['enabled']) {
            return $res;
        }

        $ventana = (int) config('descomision.ventana_dias', 30);
        $bloqueadoId = SellerStatus::where('name', config('descomision.status_bloqueado', 'Bloqueado'))->value('id');
        $bloqueados = [];

        foreach ($this->candidatas($ventana) as $venta) {
            $seller = Seller::where('user_id', $venta->seller_id)->first();
            if (! $seller) {
                continue;
            }
            if (! $seller->hasNotBeenPaid((object) ['client_id' => $venta->client_id])) {
                $res['omitidas_pagadas']++;
                continue;
            }
            $yaExiste = SellerDecommissionEvent::where('comision_tipo', self::TIPO_COMISION)
                ->where('comision_id', $venta->client_id)->exists();
            if ($yaExiste) {
                continue;
            }

            $res['descomisionadas']++;
            $res['detalle'][] = ['seller_id' => $seller->id, 'client_id' => $venta->client_id];
            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($seller, $venta, $ventana, $bloqueadoId, &$bloqueados) {
                SellerDecommissionEvent::create([
                    'seller_id' => $seller->id,
                    'comision_tipo' => self::TIPO_COMISION,
                    'comision_id' => $venta->client_id,
                    'motivo' => "Cliente canceló dentro de la ventana de {$ventana} días",
                    'estado' => self::ESTADO_DESCOMISIONADA,
                ]);
                if ($bloqueadoId && (int) $seller->status_id !== (int) $bloqueadoId && empty($bloqueados[$seller->id])) {
                    $seller->status_id_antes_de_inactivar = $seller->status_id;
                    $seller->status_id = $bloqueadoId;
                    $seller->save();
                    $bloqueados[$seller->id] = true;
                }
            });
        }
        $res['vendedores_bloqueados'] = count($bloqueados);

        return $res;
    }

    /**
     * Ventas con cliente Cancelado cuya cancelación (aprox. updated_at) cayó dentro de la ventana
     * de permanencia desde la activación.
     */
    protected function candidatas(int $ventana)
    {
        return ClientMainInformation::where('estado', 'Cancelado')
            ->whereNotNull('seller_id')
            ->whereNotNull('activation_date')
            ->whereDate('activation_date', '>=', '2024-06-01')
            ->get()
            ->filter(fn ($v) => Carbon::parse($v->updated_at)
                ->lte(Carbon::parse($v->activation_date)->addDays($ventana)->endOfDay()));
    }

    /**
     * Desbloqueo: solo con permiso Spatie, motivo obligatorio y bitácora (activity_log).
     */
    public function desbloquear(Seller $seller, User $actor, string $motivo): void
    {
        if (! $actor->can(self::PERMISO_DESBLOQUEO)) {
            throw new \DomainException('Sin permiso para desbloquear vendedores descomisionados.');
        }
        if (trim($motivo) === '') {
            throw new \InvalidArgumentException('El motivo del desbloqueo es obligatorio.');
        }
        $antes = $seller->status_id_antes_de_inactivar;
        $bloqueadoId = SellerStatus::where('name', config('descomision.status_bloqueado', 'Bloqueado'))->value('id');
        if (! $antes || (int) $seller->status_id !== (int) $bloqueadoId) {
            throw new \DomainException('El vendedor no está bloqueado por descomisión.');
        }

        DB::transaction(function () use ($seller, $actor, $motivo, $antes) {
            $seller->status_id = $antes;
            $seller->status_id_antes_de_inactivar = null;
            $seller->save();
            SellerDecommissionEvent::where('seller_id', $seller->id)
                ->where('estado', self::ESTADO_DESCOMISIONADA)
                ->update(['desbloqueado_por' => $actor->id]);
            activity('descomision')->performedOn($seller)->causedBy($actor)
                ->withProperties(['motivo' => $motivo, 'status_restaurado' => $antes])
                ->log('Vendedor desbloqueado tras descomisión: ' . $motivo);
        });
    }
}
