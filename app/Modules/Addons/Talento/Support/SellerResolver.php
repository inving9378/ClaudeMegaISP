<?php

namespace App\Modules\Addons\Talento\Support;

use App\Models\Seller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;

/**
 * Puente de identidad Talento→Vendedores para la Fase D (comisiones). Mismo
 * mecanismo ya usado por Actor::seller() y TalentoEmbajadoresController::
 * sellerData(): sellers.user_id == talento_colaboradores.user_id.
 *
 * No todo colaborador tiene fila en `sellers` — decisión de Irving (plan
 * Vendedores→Talento): bloquear con mensaje claro en vez de crear el Seller
 * al vuelo.
 */
class SellerResolver
{
    public static function forColaborador(int $colaboradorId): ?Seller
    {
        $col = TalentoColaborador::findOrFail($colaboradorId);
        return Seller::where('user_id', $col->user_id)->first();
    }
}
