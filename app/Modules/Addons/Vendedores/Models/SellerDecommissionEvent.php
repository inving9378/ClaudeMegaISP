<?php

namespace App\Modules\Addons\Vendedores\Models;

use App\Models\BaseModel;

/**
 * Evento de descomisión (#1221 / #1216 F1b). Solo bitácora: no afecta saldos
 * ni comisiones existentes. La comisión se referencia por tipo + id (sin FK).
 */
class SellerDecommissionEvent extends BaseModel
{
    protected $table = 'seller_decommission_events';

    protected $fillable = [
        'seller_id', 'comision_tipo', 'comision_id', 'monto',
        'motivo', 'estado', 'desbloqueado_por',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];
}
