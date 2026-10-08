<?php

namespace App\Models\Asistente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un turno de la conversación: user | assistant | herramienta. */
class Mensaje extends Model
{
    protected $table = 'asistente_mensajes';

    protected $guarded = [];

    protected $casts = [
        'llamadas'   => 'array',
        'compactado' => 'boolean',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class, 'conversacion_id');
    }
}
