<?php

namespace App\Modules\Addons\IA\Models;

use App\Models\BaseModel;
use App\Models\Core\ApiIntegration;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qué IA usa cada módulo: clave del catálogo config('ia_modulos') →
 * integración del Integration Hub (llave) + modelo.
 * ia_proveedor_id queda sin uso (diseño previo; el Hub es el almacén de llaves).
 */
class IAAsignacion extends BaseModel
{
    protected $table = 'ia_asignaciones';

    protected $fillable = ['clave', 'api_integration_id', 'ia_proveedor_id', 'modelo', 'created_by', 'updated_by'];

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(ApiIntegration::class, 'api_integration_id');
    }
}
