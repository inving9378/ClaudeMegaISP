<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un disparo de frontera dura (`valvula_contexto`/`valvula_nacimiento`) ya proyectado desde
 * `roadmap_items.log` a una fila consultable. Ver el docblock de la migración
 * `2026_08_29_120000_crea_torre_frontera_dura_eventos` para el porqué.
 *
 * Solo lectura desde el punto de vista de los consumidores de Torre: se escribe únicamente desde
 * `circuito:backfill-frontera-dura-eventos` (histórico) o la captura en vivo de la Pieza 1b.
 */
class TorreFronteraDuraEvento extends Model
{
    protected $table = 'torre_frontera_dura_eventos';

    public $timestamps = false;

    protected $fillable = [
        'roadmap_item_id',
        'categoria',
        'termino',
        'veredicto',
        'razon',
        'ocurrido_at',
        'origen',
    ];

    protected $casts = [
        'ocurrido_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RoadmapItem::class, 'roadmap_item_id');
    }
}
