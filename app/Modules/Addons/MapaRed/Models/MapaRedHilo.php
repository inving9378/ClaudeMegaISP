<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hilo (strand) de un cable. Fila propia auto-instanciada por MapaRedCable al guardar
 * (MR-09, item #945: buffer/buffer_color/numero/numero_global/color) y con estado operativo
 * de ciclo de vida (MR-11, item #947: estado/observaciones) — ambas fases construidas en
 * paralelo sobre la misma tabla `mapared_hilos`.
 */
class MapaRedHilo extends Model
{
    protected $table = 'mapared_hilos';

    public const ESTADOS = ['libre', 'asignado', 'dañado', 'reservado'];

    protected $fillable = [
        'cable_id',
        'buffer',
        'buffer_color',
        'numero',
        'numero_global',
        'color',
        'estado',
        'observaciones',
    ];

    public function cable(): BelongsTo
    {
        return $this->belongsTo(MapaRedCable::class, 'cable_id');
    }
}
