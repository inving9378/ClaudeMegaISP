<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hilo (strand) auto-instanciado por MapaRedCable (MR-09, item #945).
 *
 * MR-11 (#947) extenderá esta tabla con `estado`/`observaciones` (ALTER) para convertirla en
 * entidad de primera clase con ciclo de vida — no se anticipan esas columnas aquí.
 */
class MapaRedHilo extends Model
{
    protected $table = 'mapared_hilos';

    protected $fillable = [
        'cable_id',
        'buffer',
        'buffer_color',
        'numero',
        'numero_global',
        'color',
    ];

    public function cable(): BelongsTo
    {
        return $this->belongsTo(MapaRedCable::class, 'cable_id');
    }
}
