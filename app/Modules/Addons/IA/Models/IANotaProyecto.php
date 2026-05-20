<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;

use App\Modules\Addons\IA\Services\ContextoProyectoService;

class IANotaProyecto extends BaseModel
{
    protected $table = 'ia_notas_proyecto';

    protected $fillable = [
        'titulo',
        'contenido',
        'categoria',
        'importante',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'importante' => 'boolean',
    ];

    protected static function booted(): void
    {
        $invalidar = function () {
            try {
                // Una nota afecta a la sección "Notas importantes" del system prompt
                // de todos los usuarios, no solo del creador.
                app(ContextoProyectoService::class)->invalidarTodos();
            } catch (\Throwable $e) {
                // No bloquear escrituras si el contexto no puede resolverse
            }
        };
        static::saved($invalidar);
        static::deleted($invalidar);
    }

    public function scopeImportantes($query)
    {
        return $query->where('importante', true);
    }
}
