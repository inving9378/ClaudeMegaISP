<?php

namespace App\Models\Asistente;

use Illuminate\Database\Eloquent\Model;

/** Memoria larga de JARVIS por usuario: preferencias y contexto, nunca datos que cambian. */
class Recuerdo extends Model
{
    protected $table = 'asistente_recuerdos';

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
