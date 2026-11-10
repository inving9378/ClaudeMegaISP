<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/** Item #1270 (MR-33) — una lectura de potencia (mínima/promedio) de una caja en un momento. */
class MapaRedPotenciaLectura extends Model
{
    public $timestamps = false;

    protected $table = 'mapared_potencia_lecturas';

    protected $fillable = ['layer_id', 'potencia_minima_dbm', 'potencia_promedio_dbm', 'total_onus', 'capturada_en'];

    protected $casts = [
        'potencia_minima_dbm' => 'float',
        'potencia_promedio_dbm' => 'float',
        'capturada_en' => 'datetime',
    ];
}
