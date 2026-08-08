<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TORRE V2 — un reporte del historial de un item. APPEND-ONLY.
 *
 * Regla dura: estas filas se CREAN y se LEEN, nunca se actualizan ni se borran. Es el rastro de
 * quién decidió qué y por qué, con seis terminales y Thomas escribiendo sobre los mismos items.
 * Si un reporte quedó mal, se agrega otro que lo corrija — no se edita el anterior.
 *
 * Los campos resumen de `roadmap_items` (comentarios_claude / reporte_tecnico / reporte_coloquial)
 * siguen existiendo y los alimenta `RoadmapReportService`: son la vista corta; esto es el detalle.
 */
class RoadmapItemReport extends Model
{
    protected $table = 'roadmap_item_reports';

    /** Append-only: solo se sella created_at; no hay updated_at que mantener. */
    public const UPDATED_AT = null;

    protected $fillable = ['roadmap_item_id', 'autor', 'tipo', 'resumen', 'cuerpo', 'meta'];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Clases de evento. Se valida en la aplicación (no como enum de MySQL) para poder sumar tipos
     * sin ALTER sobre una tabla que crece.
     *
     *  - avance       → la terminal reporta progreso parcial.
     *  - decision     → se tomó una decisión sin consultar (la regla de oro: opción recomendada).
     *  - consulta     → la terminal le PREGUNTA a Thomas y se detiene.
     *  - respuesta    → Thomas (o Irving, si se escaló) resuelve la consulta.
     *  - verificacion → resultado de verificar contra los criterios de aceptación.
     *  - escalacion   → salió del circuito hacia Irving (conjunto de escalamiento).
     *  - cierre       → el item quedó terminado.
     *  - nota         → cualquier otra cosa que valga la pena dejar por escrito.
     */
    public const TIPOS = [
        'avance', 'decision', 'consulta', 'respuesta',
        'verificacion', 'escalacion', 'cierre', 'nota',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RoadmapItem::class, 'roadmap_item_id');
    }

    /** Del más nuevo al más viejo — el orden en que se lee un historial. */
    public function scopeRecientes($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Serialización estable para la API externa y la Torre. */
    public function toApi(): array
    {
        return [
            'id'      => (int) $this->id,
            'autor'   => $this->autor,
            'tipo'    => $this->tipo,
            'resumen' => $this->resumen,
            'cuerpo'  => $this->cuerpo,
            'meta'    => $this->meta,
            'at'      => optional($this->created_at)->toIso8601String(),
        ];
    }
}
