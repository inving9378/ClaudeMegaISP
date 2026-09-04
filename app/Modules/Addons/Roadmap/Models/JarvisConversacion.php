<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item #806 — hilo de chat sobre una sugerencia de Jarvis (#805) camino a convertirse en item
 * de la Hoja de Ruta. Ver la migración `2026_08_29_220000_crea_jarvis_chat_tablas` para el
 * porqué del diseño.
 */
class JarvisConversacion extends Model
{
    protected $table = 'jarvis_conversaciones';

    protected $fillable = [
        'sugerencia_clave',
        'categoria',
        'texto_sugerencia',
        'citas',
        'item_id',
        'estado',
        'created_by',
    ];

    protected $casts = [
        'citas' => 'array',
    ];

    public function mensajes(): HasMany
    {
        return $this->hasMany(JarvisMensaje::class, 'jarvis_conversacion_id')->orderBy('created_at');
    }

    /** Clave estable de una sugerencia (no tiene id propio: se recalcula cada corrida del detector). */
    public static function claveSugerencia(string $categoria, string $texto): string
    {
        return substr(sha1($categoria . '|' . trim(preg_replace('/\s+/', ' ', $texto))), 0, 32);
    }
}
