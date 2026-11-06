<?php

namespace App\Models\Ventas;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentaParametroReglamento extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ventas_parametros_reglamento';

    protected $fillable = [
        'clave',
        'nombre',
        'tipo',
        'valor',
        'descripcion',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
