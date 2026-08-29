<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Solicitud de información corporativa — apartado XIV. Quién pidió, qué
 * apartados, con qué plazo. El armado real del paquete vive en `DcEntrega`.
 */
class DcSolicitud extends Model
{
    use SoftDeletes;

    protected $table = 'dc_solicitudes';

    protected $fillable = [
        'empresa_id', 'solicitante', 'caracter', 'fecha_recepcion', 'plazo_dias',
        'fecha_limite', 'apartados', 'estado', 'documento_uuid',
        'documento_nombre_original', 'documento_mime', 'documento_bytes',
        'creado_por_user_id', 'notas',
    ];

    protected $casts = [
        'fecha_recepcion' => 'date',
        'fecha_limite'    => 'date',
        'apartados'       => 'array',
        'plazo_dias'       => 'integer',
        'documento_bytes' => 'integer',
    ];

    public const ESTADOS = ['recibida', 'en_preparacion', 'entregada', 'rechazada'];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function entregas()
    {
        return $this->hasMany(DcEntrega::class, 'solicitud_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /** Deriva `fecha_limite` de `fecha_recepcion + plazo_dias` cuando no se fijó a mano. */
    public function calcularFechaLimite(): ?string
    {
        if ($this->fecha_limite) {
            return $this->fecha_limite->toDateString();
        }

        if (! $this->fecha_recepcion || ! $this->plazo_dias) {
            return null;
        }

        return $this->fecha_recepcion->copy()->addDays($this->plazo_dias)->toDateString();
    }
}
