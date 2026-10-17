<?php

namespace App\Modules\Addons\PortalCliente\Models;

use App\Models\BaseModel;
use App\Models\User;

/**
 * Una versión de la presentación del Portal Cliente (Configuración → Portal Cliente).
 * estado: borrador (como mucho uno), publicada (como mucho una) o historial (publicadas anteriores).
 */
class AparienciaVersion extends BaseModel
{
    public const BORRADOR  = 'borrador';
    public const PUBLICADA = 'publicada';
    public const HISTORIAL = 'historial';

    protected $table = 'portal_cliente_apariencia_versiones';

    protected $guarded = ['id'];

    protected $casts = [
        'config'          => 'array',
        'resumen_cambios' => 'array',
        'published_at'    => 'datetime',
        'revision'        => 'integer',
        'numero'          => 'integer',
    ];

    public function publicador()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
