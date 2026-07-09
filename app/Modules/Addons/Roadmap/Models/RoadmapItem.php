<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapItem extends Model
{
    protected $table = 'roadmap_items';

    protected $fillable = [
        'title', 'description', 'status', 'priority',
        'target_version', 'prompt', 'position',
        'started_at', 'completed_at',
        'subtasks', 'log',
        // Circuito de mejora continua (Parte 1.1)
        'modulo', 'nivel_riesgo', 'estado_aprobacion',
        'comentarios_claude', 'revisado_at', 'aprobado_por',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'revisado_at'  => 'datetime',
        'position'     => 'integer',
        'subtasks'     => 'array',
        'log'          => 'array',
    ];

    // Enums del circuito (fuente de verdad para validación en el endpoint externo)
    public const NIVELES_RIESGO = ['A', 'B', 'C'];

    public const ESTADOS_APROBACION = [
        'pendiente_revision',
        'aprobado_claude',
        'requiere_irving',
        'rechazado',
        'en_progreso',
        'completado',
    ];

    protected $attributes = [
        'subtasks' => '[]',
        'log'      => '[]',
    ];

    public static function currentInProgress(): ?self
    {
        return static::where('status', 'in_progress')->first();
    }

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("FIELD(status,'in_progress','pending','done','cancelled')")
                     ->orderBy('position')
                     ->orderBy('id');
    }
}
