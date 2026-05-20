<?php

namespace App\Modules\Addons\IA\Models;

use Illuminate\Database\Eloquent\Model;

class IAMemoriaProyecto extends Model
{
    protected $table = 'ia_memoria_proyecto';

    protected $fillable = [
        'tipo',
        'contenido',
        'modulo_relacionado',
        'relevancia',
        'obsoleto',
        'ia_conversacion_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'obsoleto' => 'boolean',
        'relevancia' => 'integer',
    ];

    public const TIPOS = [
        'hecho',
        'avance',
        'decision',
        'pendiente',
        'error_resuelto',
    ];

    public function scopeVigentes($q)
    {
        return $q->where('obsoleto', false);
    }

    public function scopePorRelevancia($q)
    {
        return $q->orderByDesc('relevancia')->orderByDesc('updated_at');
    }

    public function conversacion()
    {
        return $this->belongsTo(IAConversacion::class, 'ia_conversacion_id');
    }
}
