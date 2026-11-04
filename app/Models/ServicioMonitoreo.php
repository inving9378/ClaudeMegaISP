<?php

namespace App\Models;

use App\Modules\Core\Clientes\Models\ClientInternetService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * «Datos de red del dedicado» de un servicio de internet (item #988): router, interfaz VLAN,
 * VLAN ID, bloque entregado e IP del router. Tabla propia: guardar esto NUNCA guarda el servicio
 * (ClientInternetServiceObserver mandaría trabajos al router).
 */
class ServicioMonitoreo extends BaseModel
{
    protected $table = 'servicio_monitoreo';

    protected $guarded = [];

    protected $casts = [
        'activo'  => 'boolean',
        'vlan_id' => 'integer',
    ];

    public function ips(): HasMany
    {
        return $this->hasMany(ServicioMonitoreoIp::class, 'servicio_monitoreo_id')->orderBy('orden')->orderBy('id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ClientInternetService::class, 'servicio_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
