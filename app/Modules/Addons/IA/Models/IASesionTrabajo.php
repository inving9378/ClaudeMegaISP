<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;

class IASesionTrabajo extends BaseModel
{
    protected $table = 'ia_sesiones_trabajo';

    protected $fillable = [
        'resumen',
        'archivos_modificados',
        'prompts_destacados',
        'proveedor_ia_usado',
        'inicio_sesion',
        'fin_sesion',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'archivos_modificados' => 'array',
        'prompts_destacados' => 'array',
        'inicio_sesion' => 'datetime',
        'fin_sesion' => 'datetime',
    ];
}
