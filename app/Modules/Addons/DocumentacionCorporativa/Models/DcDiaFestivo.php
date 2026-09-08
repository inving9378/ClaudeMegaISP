<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Día festivo oficial de México, catálogo editable para el cómputo de días
 * hábiles del plazo maestro de 180 días (ver `dc_empresas.fecha_inicio_plazo`).
 *
 * Modelo plano (no BaseModel): esta tabla no lleva `created_by`/`updated_by`,
 * y BaseModel los estampa siempre.
 */
class DcDiaFestivo extends Model
{
    protected $table = 'dc_dias_festivos';

    protected $fillable = [
        'fecha', 'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}
