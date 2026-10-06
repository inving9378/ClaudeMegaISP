<?php

namespace App\Modules\Addons\Hub\Models;

use App\Models\BaseModel;
use App\Models\Core\ApiIntegration;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qué usa cada módulo de servicios: clave del catálogo config('servicios_modulos')
 * → una línea de WhatsApp o una integración del Hub. Sin fila = como siempre.
 */
class ServicioAsignacion extends BaseModel
{
    protected $table = 'servicio_asignaciones';

    protected $fillable = ['clave', 'whatsapp_instance_id', 'api_integration_id', 'created_by', 'updated_by'];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'whatsapp_instance_id');
    }

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(ApiIntegration::class, 'api_integration_id');
    }
}
