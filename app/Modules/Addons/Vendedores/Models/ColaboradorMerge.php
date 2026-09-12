<?php

namespace App\Modules\Addons\Vendedores\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Auditoría de fusiones/bajas de identidad de vendedores/colaboradores duplicados
 * (metodología aprobada por Irving en #9990877 q4). Los registros espejo/duplicados
 * se marcan aquí, NUNCA se borran de sus tablas originales. `to_id` null = baja sin
 * fusión real (no había cartera/comisión que reasignar).
 */
class ColaboradorMerge extends Model
{
    protected $table = 'colaboradores_merges';

    protected $fillable = ['from_id', 'to_id', 'fecha', 'usuario', 'snapshot'];

    protected $casts = [
        'fecha'    => 'datetime',
        'snapshot' => 'array',
    ];
}
