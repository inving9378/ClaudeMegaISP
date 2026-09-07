<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de splitter (MR-13, item roadmap #949).
 *
 * Porción mínima del catálogo completo de MR-08 (item #944) necesaria para que
 * `mapared_splitters.tipo_splitter_id` tenga a qué apuntar. Semilla D13 (splitters balanceados).
 * MR-08 Fase 1 (#9990503) amplió la tabla con `tipo_conector_id`/`perdida_paso_db`/
 * `perdida_derivacion_db` para D14 (desbalanceados) — `perdida_db` NO se tocó, sigue siendo la
 * de balanceados (`MapaRedSplitter::getPerdidaEfectivaDbAttribute()` la sigue leyendo igual).
 */
class MapaRedTipoSplitter extends Model
{
    protected $table = 'mapared_tipo_splitter';

    protected $fillable = [
        'nombre',
        'fabricante',
        'balanceado',
        'ratio',
        'numero_puertos',
        'perdida_db',
        'precio',
        'tipo_conector_id',
        'perdida_paso_db',
        'perdida_derivacion_db',
    ];

    protected $casts = [
        'balanceado' => 'boolean',
        'numero_puertos' => 'integer',
        'perdida_db' => 'float',
        'precio' => 'float',
        'perdida_paso_db' => 'float',
        'perdida_derivacion_db' => 'float',
    ];

    public function splitters()
    {
        return $this->hasMany(MapaRedSplitter::class, 'tipo_splitter_id');
    }
}
