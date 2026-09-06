<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hilo (strand) de un cable, MR-11 (item #947). Fila propia con estado operativo,
 * distinta del hilo-como-campo del mirror `mapared_fibers` (MR-04, atado a una ruta/layer).
 */
class MapaRedHilo extends Model
{
    protected $table = 'mapared_hilos';

    public const ESTADOS = ['libre', 'asignado', 'dañado', 'reservado'];

    protected $fillable = [
        'cable_id',
        'buffer',
        'numero',
        'color',
        'estado',
        'observaciones',
    ];
}
