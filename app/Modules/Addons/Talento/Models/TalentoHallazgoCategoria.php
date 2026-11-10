<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

/** Catálogo editable de categorías de hallazgo (item #1311). Nunca se borran: activo=0. */
class TalentoHallazgoCategoria extends BaseModel
{
    protected $table = 'talento_hallazgo_categorias';

    protected $fillable = ['nombre', 'descripcion', 'severidad_sugerida', 'activo', 'orden', 'created_by', 'updated_by'];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];
}
