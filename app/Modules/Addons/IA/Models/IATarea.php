<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;

use App\Modules\Addons\IA\Services\ContextoProyectoService;

class IATarea extends BaseModel
{
    protected $table = 'ia_tareas';

    protected $fillable = [
        'titulo',
        'descripcion',
        'estado',
        'prioridad',
        'modulo_relacionado',
        'completada_en',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'completada_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        $invalidar = function () {
            try {
                // Una tarea afecta a la sección "Tareas pendientes" del system prompt
                // de todos los usuarios, no solo del creador, por eso invalidamos todos.
                app(ContextoProyectoService::class)->invalidarTodos();
            } catch (\Throwable $e) {
                // No bloquear escrituras si el contexto no puede resolverse
            }
        };
        static::saved($invalidar);
        static::deleted($invalidar);
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'en_progreso']);
    }
}
