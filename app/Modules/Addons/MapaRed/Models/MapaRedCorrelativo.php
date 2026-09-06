<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MR-24 (item #960, Fase 1) — contador atómico por zona+tipo. Ver `NomenclaturaService`.
 */
class MapaRedCorrelativo extends Model
{
    protected $table = 'mapared_correlativos';

    protected $fillable = [
        'zona',
        'tipo',
        'ultimo',
    ];
}
