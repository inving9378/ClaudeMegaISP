<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un paso de una corrida de provisión.
 *
 * `version_provisionador` es obligatoria en cada registro: es lo que permite
 * decidir, al reintentar, si los pasos ya completados siguen siendo válidos para
 * la versión que va a continuar (ver la migración).
 */
class ProvisionEstado extends Model
{
    protected $table = 'voip_provision_estado';

    protected $fillable = [
        'ejecucion_uuid', 'paso', 'estado',
        'version_provisionador', 'version_asterisk', 'esquema_revision',
        'intentos', 'detalle', 'error', 'iniciado_at', 'terminado_at',
    ];

    protected $casts = [
        'detalle'      => 'array',
        'iniciado_at'  => 'datetime',
        'terminado_at' => 'datetime',
    ];

    public const PASOS = [
        'verificar', 'descargar', 'dependencias', 'compilar', 'esquema',
        'config', 'credenciales', 'siembra', 'arrancar', 'validar',
    ];

    /**
     * ¿Se puede dar por bueno este paso al reintentar?
     *
     * Completado NO basta: si lo hizo una versión anterior del provisionador, su
     * criterio de "completado" pudo cambiar, y darlo por bueno sería confiar en un
     * estado que no corresponde a la lógica actual.
     */
    public function reutilizablePor(string $versionActual): bool
    {
        return $this->estado === 'completado'
            && version_compare($this->version_provisionador, $versionActual, '>=');
    }

    public function scopeDeEjecucion($q, string $uuid)
    {
        return $q->where('ejecucion_uuid', $uuid);
    }
}
