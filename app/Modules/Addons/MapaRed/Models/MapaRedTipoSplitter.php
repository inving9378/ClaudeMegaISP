<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de splitter (MR-13, item roadmap #949).
 *
 * Porción mínima del catálogo completo de MR-08 (item #944, aún sin aterrizar) necesaria para
 * que `mapared_splitters.tipo_splitter_id` tenga a qué apuntar. Semilla D13 (splitters
 * balanceados). MR-08 puede ampliar esta tabla (fabricante ya existe, faltaría por ejemplo
 * `perdida_derivacion_db` para D14 desbalanceados) pero no debe recrearla.
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
    ];

    protected $casts = [
        'balanceado' => 'boolean',
        'numero_puertos' => 'integer',
        'perdida_db' => 'float',
        'precio' => 'float',
    ];

    public function splitters()
    {
        return $this->hasMany(MapaRedSplitter::class, 'tipo_splitter_id');
    }
}
