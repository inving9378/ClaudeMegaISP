<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Criterio único de «venta» para rankings (UNI-T I2, #894): un cliente con
 * activation_date dentro del periodo, atribuido a client_main_information.seller_id
 * (users.id), agrupado por id de usuario (no por nombre) y sin corte de fecha propio.
 */
class RankingVentasService
{
    public function ranking(?string $desde = null, ?string $hasta = null)
    {
        $query = DB::table('client_main_information')
            ->join('users', 'users.id', '=', 'client_main_information.seller_id')
            ->select('users.id as user_id', 'users.name', DB::raw('count(*) as sales'))
            ->whereNotNull('client_main_information.activation_date');

        if ($desde !== null && $hasta !== null) {
            $query->whereBetween('client_main_information.activation_date', [$desde, $hasta]);
        }

        return $query->groupBy('users.id', 'users.name')->orderByDesc('sales')->get();
    }
}
