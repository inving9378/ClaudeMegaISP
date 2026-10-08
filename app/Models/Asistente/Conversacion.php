<?php

namespace App\Models\Asistente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hilo continuo de JARVIS con un usuario (ver migración create_asistente_jarvis_tables). */
class Conversacion extends Model
{
    protected $table = 'asistente_conversaciones';

    protected $guarded = [];

    protected $casts = [
        'activa'            => 'boolean',
        'ultimo_mensaje_at' => 'datetime',
    ];

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class, 'conversacion_id');
    }
}
