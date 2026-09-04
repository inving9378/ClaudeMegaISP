<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/** Item #806 — un mensaje del hilo `JarvisConversacion`. Inmutable, sin updated_at. */
class JarvisMensaje extends Model
{
    protected $table = 'jarvis_mensajes';

    public $timestamps = false;

    protected $fillable = [
        'jarvis_conversacion_id',
        'rol',
        'contenido',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
