<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/** #1110 — borrador de items que Jarvis propone en la conversación; ver la migración jarvis_propuestas. */
class JarvisPropuesta extends Model
{
    protected $table = 'jarvis_propuestas';

    protected $fillable = [
        'user_id', 'conversacion_id', 'mensaje_id', 'borrador', 'pregunta', 'huella',
        'version', 'estado', 'item_ids', 'historial',
    ];

    protected $casts = [
        'borrador'  => 'array',
        'item_ids'  => 'array',
        'historial' => 'array',
    ];
}
