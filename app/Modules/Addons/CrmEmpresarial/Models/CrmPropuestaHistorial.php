<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Bitácora append-only de transiciones de una versión de propuesta. */
class CrmPropuestaHistorial extends Model
{
    public $timestamps = false;

    protected $table = 'crm_propuesta_historial';
    protected $guarded = ['id'];
    protected $casts = ['at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de propuestas es inmutable.'));
        static::deleting(fn () => throw new LogicException('El historial de propuestas es inmutable.'));
    }
}
