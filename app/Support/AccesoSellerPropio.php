<?php

namespace App\Support;

use App\Models\Seller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;

/**
 * UNI-T B5b: estado de cuenta, deuda, recibos y caja de un vendedor solo para la propia persona
 * o para quien tenga un alcance explícito (admin, cortes o ver pagos/transacciones de vendedores).
 * Detrás de `unificacion.interruptores.comisiones_acceso_b5b`; apagado = comportamiento anterior.
 */
class AccesoSellerPropio
{
    public const ALCANCE_CAJA = ['seller_cuts'];
    public const ALCANCE_COMISIONES = [
        'seller_view_all_payments_for_seller', 'seller_view_all_transactions_for_seller', 'seller_cuts',
    ];

    public static function activo(): bool
    {
        return (bool) config('unificacion.interruptores.comisiones_acceso_b5b');
    }

    /** $ownerUserId = users.id dueño del dato. Un dato inexistente da 404. */
    public static function usuario(?int $ownerUserId, array $alcance = self::ALCANCE_COMISIONES): void
    {
        if (! self::activo()) {
            return;
        }
        abort_if($ownerUserId === null, 404);
        $actor = auth()->user();
        abort_if(! $actor, 401);
        if ((int) $actor->id === $ownerUserId || $actor->isAdmin()) {
            return;
        }
        foreach ($alcance as $permiso) {
            if ($actor->can($permiso)) {
                return;
            }
        }
        abort(403, 'No tienes autorización para consultar los datos de otra persona.');
    }

    public static function vendedor(?int $sellerId, array $alcance = self::ALCANCE_COMISIONES): void
    {
        if (self::activo()) {
            self::usuario($sellerId ? Seller::whereKey($sellerId)->value('user_id') : null, $alcance);
        }
    }

    public static function colaborador(int $colaboradorId, array $alcance = self::ALCANCE_COMISIONES): void
    {
        if (self::activo()) {
            self::usuario(TalentoColaborador::whereKey($colaboradorId)->value('user_id'), $alcance);
        }
    }
}
