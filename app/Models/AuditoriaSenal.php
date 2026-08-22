<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Señal minada de la bitácora del sistema (item #1016). Modelo plano, SIN
 * LogsActivity a propósito (ver migración de creación de la tabla).
 */
class AuditoriaSenal extends Model
{
    protected $table = 'auditoria_senales';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'payload'     => 'array',
        'ocurrido_en' => 'datetime',
        'created_at'  => 'datetime',
        'revisado'    => 'boolean',
    ];
}
