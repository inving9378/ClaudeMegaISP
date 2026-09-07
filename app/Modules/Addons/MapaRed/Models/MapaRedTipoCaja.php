<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de caja/NAP/mufa (MR-08 Fase 1, item roadmap #9990503, sub-item de #944).
 */
class MapaRedTipoCaja extends Model
{
    protected $table = 'mapared_tipo_caja';

    protected $fillable = [
        'nombre',
        'capacidad_puertos',
        'capacidad_fusiones',
        'ip_rating',
        'ik_rating',
    ];

    protected $casts = [
        'capacidad_puertos' => 'integer',
        'capacidad_fusiones' => 'integer',
    ];
}
