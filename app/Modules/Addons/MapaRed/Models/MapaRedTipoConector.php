<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de conector/empalme (MR-08 Fase 1, item roadmap #9990503, sub-item de #944).
 *
 * Mismos valores D15 que `MapaRedEmpalme::PERDIDA_DB_DEFAULT` (fusión/mecánico/conectorizado),
 * aquí como catálogo en BD editable en vez de constante en código.
 */
class MapaRedTipoConector extends Model
{
    protected $table = 'mapared_tipo_conector';

    protected $fillable = [
        'nombre',
        'perdida_db',
    ];

    protected $casts = [
        'perdida_db' => 'float',
    ];
}
