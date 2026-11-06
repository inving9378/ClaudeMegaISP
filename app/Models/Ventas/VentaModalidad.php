<?php

namespace App\Models\Ventas;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentaModalidad extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ventas_modalidades';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
        'orden',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];
}
