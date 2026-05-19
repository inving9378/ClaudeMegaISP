<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;

use Illuminate\Database\Eloquent\Relations\HasMany;

class IAProveedor extends BaseModel
{
    protected $table = 'ia_proveedores';

    protected $fillable = [
        'nombre',
        'driver',
        'api_key',
        'endpoint_url',
        'modelo_default',
        'soporta_imagenes',
        'headers_personalizados',
        'config_extra',
        'activo',
        'estado',
        'ultimo_error',
        'probado_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'soporta_imagenes' => 'boolean',
        'activo' => 'boolean',
        'headers_personalizados' => 'array',
        'config_extra' => 'array',
        'probado_at' => 'datetime',
        'api_key' => 'encrypted',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function conversaciones(): HasMany
    {
        return $this->hasMany(IAConversacion::class, 'ia_proveedor_id');
    }
}
