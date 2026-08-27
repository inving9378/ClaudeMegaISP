<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un término de una categoría de frontera dura.
 *
 * `palabra_completa` es el MISMO grado de libertad que `DetectorTerminos::apariciones()`: los
 * términos cortos o ambiguos ('rol' dentro de 'control', 'prod' dentro de 'producto') exigen
 * palabra completa; los largos e inequívocos admiten flexión ('factura' → 'facturación').
 * Se guarda por término y no por lista porque el criterio es del término, no del grupo.
 */
class CircuitoFronteraTermino extends Model
{
    protected $table = 'circuito_frontera_terminos';

    protected $fillable = ['categoria', 'termino', 'palabra_completa', 'activo'];

    protected $casts = [
        'palabra_completa' => 'boolean',
        'activo'           => 'boolean',
    ];
}
