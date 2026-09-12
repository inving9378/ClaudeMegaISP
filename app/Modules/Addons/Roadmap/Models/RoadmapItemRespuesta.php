<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CIRC-02b — una respuesta de Irving (o de la vía que sea) a un item `requiere_irving`.
 *
 * Base de la bandeja de decisiones: escribir aquí (en vez de sobre comentarios_claude, que se
 * pisa) es lo que le permite a una terminal detectar "ya me respondieron" y consumirlo una sola
 * vez (`consumida_at`/`consumida_por`).
 */
class RoadmapItemRespuesta extends Model
{
    protected $table = 'roadmap_item_respuestas';

    protected $fillable = [
        'item_id',
        'autor',
        'canal',
        'cuerpo',
        'ejecutar',
        'consumida_at',
        'consumida_por',
    ];

    protected $casts = [
        'ejecutar'     => 'bool',
        'consumida_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RoadmapItem::class, 'item_id');
    }

    public function scopeSinConsumir($query)
    {
        return $query->whereNull('consumida_at');
    }
}
