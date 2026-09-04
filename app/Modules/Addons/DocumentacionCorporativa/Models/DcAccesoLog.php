<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de accesos al expediente corporativo. APPEND-ONLY.
 *
 * Regla dura: se crea y se lee. No hay update, no hay delete, no hay soft delete
 * y no hay flag para desactivar la escritura. Es la respuesta viva al concepto
 * 122 del apartado XII, y una bitácora editable no es una bitácora.
 */
class DcAccesoLog extends Model
{
    protected $table = 'dc_accesos_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id', 'user_id', 'apartado_id', 'concepto_id', 'documento_id',
        'accion', 'ip', 'user_agent', 'contexto',
    ];

    protected $casts = [
        'contexto'   => 'array',
        'created_at' => 'datetime',
    ];

    public const ACCION_VER       = 'ver';
    public const ACCION_DESCARGAR = 'descargar';
    public const ACCION_EXPORTAR  = 'exportar';
    public const ACCION_IMPRIMIR  = 'imprimir';

    public const ACCIONES = [
        self::ACCION_VER, self::ACCION_DESCARGAR,
        self::ACCION_EXPORTAR, self::ACCION_IMPRIMIR,
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeRecientes($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
